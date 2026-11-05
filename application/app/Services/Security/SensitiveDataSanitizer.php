<?php

namespace App\Services\Security;

use JsonException;
use Symfony\Component\HttpFoundation\HeaderBag;

class SensitiveDataSanitizer
{
    public const string REDACTED = '********';

    private const int MAX_RECURSION_DEPTH = 10;

    public function isEnabled(): bool
    {
        return isEnvironmentProduction()
            || (bool) config('engineering.sensitive_data.enabled', true);
    }

    public function sanitize(
        mixed $value,
        ?array $sensitiveFields = null,
        bool $force = false,
        int $depth = 0
    ): mixed {
        if (! $force && ! $this->isEnabled()) {
            return $value;
        }

        if ($depth >= self::MAX_RECURSION_DEPTH) {
            return '[Content omitted: maximum depth exceeded]';
        }

        if ($value instanceof HeaderBag) {
            return $this->sanitize(
                $value->all(),
                $sensitiveFields,
                force: true,
                depth: $depth
            );
        }

        if (is_array($value)) {
            return $this->sanitizeArray(
                $value,
                $sensitiveFields,
                $depth
            );
        }

        if (is_string($value)) {
            return $this->sanitizeString(
                value: $value,
                sensitiveFields: $sensitiveFields,
                depth: $depth
            );
        }

        return $value;
    }

    public function sanitizeJson(
        string $json,
        bool $force = false
    ): string
    {
        if (! $force && ! $this->isEnabled()) {
            return $json;
        }

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
            $this->sanitize(
                value: $decoded,
                force: true
            ),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    public function sanitizeUrl(
        string $url,
        bool $force = false
    ): string
    {
        if (! $force && ! $this->isEnabled()) {
            return $url;
        }

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
        ?array $sensitiveFields = null,
        int $depth = 0
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

            $data[$key] = $this->sanitize(
                $value,
                $sensitiveFields,
                force: true,
                depth: $depth + 1
            );
        }

        return $data;
    }

    private function sanitizeString(
        string $value,
        ?array $sensitiveFields = null,
        int $depth = 0
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
            ? $this->sanitizeArray(
                data: $decoded,
                sensitiveFields: $sensitiveFields,
                depth: $depth
            )
            : $value;
    }

    public function sanitizeFormData(
        string $body,
        ?array $sensitiveFields = null,
        bool $force = false
    ): string|array {
        if (! $force && ! $this->isEnabled()) {
            return $body;
        }

        parse_str($body, $parameters);

        return $this->sanitizeArray($parameters, $sensitiveFields);
    }

    public function sanitizeContent(
        mixed $content,
        bool $force = false
    ): mixed
    {
        if (! $force && ! $this->isEnabled()) {
            return $content;
        }

        if (is_array($content)) {
            return $this->sanitize(
                value: $content,
                force: true
            );
        }

        if (is_string($content) && json_validate($content)) {
            return $this->sanitizeJson(
                json: $content,
                force: true
            );
        }

        return '[Content omitted: unsupported or unstructured format]';
    }
}
