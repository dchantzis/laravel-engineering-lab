<?php

namespace App\Services\Security;

use JsonException;
use Symfony\Component\HttpFoundation\HeaderBag;

class SensitiveDataSanitizer
{
    public const REDACTED = '********';

    public function isEnabled(): bool
    {
        return isEnvironmentProduction()
            || (bool) config('engineering.sensitive_data.enabled', true);
    }

    public function sanitizeIfEnabled(mixed $value): mixed
    {
        return $this->isEnabled()
            ? $this->sanitize($value)
            : $value;
    }

    public function sanitize(
        mixed $value,
        ?array $sensitiveFields = null
    ): mixed {
        if ($value instanceof HeaderBag) {
            return $this->sanitize($value->all(), $sensitiveFields);
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $sensitiveFields);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value, $sensitiveFields);
        }

        return $value;
    }

    public function sanitizeJson(string $json): string
    {
        try {
            $decoded = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $json;
        }

        return json_encode(
            $this->sanitize($decoded),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    public function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $parameters);

        $sanitized = $this->sanitizeArray($parameters);

        $query = http_build_query($sanitized);

        return str_replace(
            '?' . $parts['query'],
            '?' . $query,
            $url
        );
    }

    private function sanitizeArray(
        array $data,
        ?array $sensitiveFields = null
    ): array {
        $fields = array_map(
            'strtolower',
            $sensitiveFields ?? config('engineering.sensitive_data.fields', [])
        );

        $fields = array_unique(array_merge($fields, [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
        ]));

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $fields, true)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            $data[$key] = $this->sanitize($value, $sensitiveFields);
        }

        return $data;
    }

    private function sanitizeString(
        string $value,
        ?array $sensitiveFields = null
    ): mixed
    {
        // Only interpret strings as structured data when they are JSON.
        if (! str_starts_with(ltrim($value), '{')
            && ! str_starts_with(ltrim($value), '[')) {
            return $value;
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $value;
        }

        return is_array($decoded)
            ? $this->sanitizeArray($decoded, $sensitiveFields)
            : $value;
    }

    public function sanitizeFormData(
        string $body,
        ?array $sensitiveFields = null
    ): array {
        parse_str($body, $parameters);

        return $this->sanitizeArray($parameters, $sensitiveFields);
    }

    public function sanitizeAuditContent(mixed $content): mixed
    {
        if (! $this->isEnabled()) {
            return $content;
        }

        if (is_array($content)) {
            return $this->sanitize($content);
        }

        if (is_string($content) && json_validate($content)) {
            return $this->sanitizeJson($content);
        }

        return '[Content omitted: unsupported or unstructured format]';
    }
}
