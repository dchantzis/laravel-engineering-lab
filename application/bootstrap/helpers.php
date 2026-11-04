<?php

use App\Enums\Environment;
use App\Http\Middleware\PersistOauthClientId;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Constants\Format;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\Enums\DatabaseConnection;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Laravel\Passport\Passport;
use Ramsey\Uuid\UuidInterface;
use Illuminate\Support\Stringable;

if (! function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 3, bool $toHuman = false): string
    {
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        if (! $toHuman) {
            return (string) round($bytes, $precision);
        }

        return sprintf('%.2f %s', round($bytes, $precision), $units[(int) $pow]);
    }
}

if (! function_exists('formatBytesToHuman')) {
    function formatBytesToHuman(int $bytes, int $precision = 3): string
    {
        return formatBytes($bytes, $precision, true);
    }
}

if (! function_exists('convertToBytes')) {
    function convertToBytes(string $from): ?int
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = substr($from, 0, -2);
        $suffix = strtoupper(substr($from, -2));

        // B or no suffix
        if (is_numeric(substr($suffix, 0, 1))) {
            return preg_replace('/[^\d]/', '', $from);
        }

        $exponent = array_flip($units)[$suffix] ?? null;
        if ($exponent === null) {
            return null;
        }

        return (int) ($number * (1000 ** $exponent));
    }
}

if (! function_exists('strToNumber')) {
    function strToNumber(string $value): string
    {
        return preg_replace('/[^0-9,.]/', '', $value);
    }
}

if (!function_exists('isDebugModeEnabled'))
{
    function isDebugModeEnabled(): bool
    {
        return (bool)config('app.debug');
    }
}

if (!function_exists('isDebugModeQueriesEnabled'))
{
    function isDebugModeQueriesEnabled(): bool
    {
        return (bool)config('app.debug_queries');
    }
}

if (!function_exists('getDatabaseColumnEncryptionKey'))
{
    function getDatabaseColumnEncryptionKey(
        string $databaseConnectionName,
    ): ?string {
        return config(sprintf(
            'database.connections.%s.encryption_key',
            $databaseConnectionName
        ));
    }
}

if (!function_exists('resolveDatabaseColumnEncryptionKey'))
{
    function resolveDatabaseColumnEncryptionKey(
        string|null $databaseConnectionName = null,
    ): ?string {
        try {
            return getDatabaseColumnEncryptionKey(
                $databaseConnectionName ?: DatabaseConnection::APPLICATION
            );
        } catch (Exception|Error $e) {
            Log::warning(
                $e->getMessage()
            );

            return null;
        }
    }
}

if (!function_exists('customEncrypter'))
{
    function customEncrypter(
        mixed $value,
        ?string $encryptKey = null,
    ): string
    {
        $key = $encryptKey ?? config('app.private_key');

        if ($key === null || $key === '') {
            throw new RuntimeException('Encryption key is not configured');
        }

        $decodedKey = base64_decode($key, true);

        if ($decodedKey === false || strlen($decodedKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Encryption key is invalid');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $cypher = sodium_crypto_secretbox(
            $value,
            $nonce,
            $decodedKey
        );

        return bin2hex(
            $nonce . $cypher
        );
    }
}

if (!function_exists('customDecrypter'))
{
    function customDecrypter(
        mixed $value,
        ?string $decryptKey = null,
    ): string|false
    {
        $key = $decryptKey ?? config('app.private_key');

        if ($key === null || $key === '') {
            throw new RuntimeException('Decryption key is not configured');
        }

        $decodedKey = base64_decode($key, true);

        if ($decodedKey === false || strlen($decodedKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Decryption key is invalid');
        }

        $decoded = hex2bin($value);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $cypher = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        return sodium_crypto_secretbox_open(
            $cypher,
            $nonce,
            $decodedKey
        );
    }
}

if (!function_exists('includeDebugQueriesInformation')) {
    function includeDebugQueriesInformation(): bool
    {
        return isAdminRequest()
            || (isEnvironmentPreProduction()
                && isDebugModeEnabled()
                && isDebugModeQueriesEnabled()
            );
    }
}

if (!function_exists('includeDebugInformation')) {
    function includeDebugInformation(): bool
    {
        return isAdminRequest()
            || (isEnvironmentPreProduction()
                && isDebugModeEnabled());
    }
}

if (!function_exists('constructQueryWithBindings'))
{
    function constructQueryWithBindings(string $query, array $bindings): string
    {
        $bindings = collect($bindings)->map(function ($element) {
            return str_replace('"', '', $element);
        })->toArray();

        return vsprintf(
            str_replace('?', "'%s'", $query),
            $bindings
        );
    }
}

if (! function_exists('getOauthClientId')) {
    /**
     * @throws AuthenticationException
     */
    function getOauthClientId(Request $request): string
    {
        if ($oauthClientId = $request->attributes->get(PersistOauthClientId::OAUTH_CLIENT_ID_SLUG)) {
            return $oauthClientId;
        }

        if (! $request->bearerToken()) {
            throw new AuthenticationException;
        }

        $oauthAccessTokenId = (new Parser(new JoseEncoder))
            ->parse($request->bearerToken())
            ->claims()
            ->get('jti');

        if (! $oauthAccessTokenId || ! Passport::token()->find($oauthAccessTokenId)) {
            throw new AuthenticationException;
        }

        return Passport::token()
            ->find($oauthAccessTokenId)
            ->client
            ->id;
    }
}

if (! function_exists('limitStringLength')) {
    function limitStringLength(string $value, int $limit): string
    {
        return (strlen($value) > $limit)
            ? substr($value, 0, $limit)
            : $limit;
    }
}

if (! function_exists('convertPrices')) {
    function convertPrices($value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $precision = (int) config('app.round_precision', 3);

        // For extremely small values (smaller than precision), return 0
        if (abs((float) $value) < pow(10, -$precision)) {
            return 0.0;
        }

        // Check if the value might be in scientific notation when converted to string
        $numericValue = (stripos((string) $value, 'e') !== false)
            ? sprintf('%.15f', $value)
            : (string) $value;

        $result = (float) bcdiv(
            $numericValue,
            1,
            $precision
        );

        return $result == 0.0 ? 0.0 : $result;
    }
}

if (! function_exists('customRound')) {
    function customRound(int|float $num, int $mode = PHP_ROUND_HALF_DOWN): float
    {
        return round(
            $num,
            (int) config('app.round_precision', 3),
            $mode
        );
    }
}

if (! function_exists('isAdminRequest')) {
    function isAdminRequest(): bool
    {
        $user = request()->user('api');

        return $user instanceof User
            && $user->is_admin === true;
    }
}

if (! function_exists('isEnvironmentLocal')) {
    function isEnvironmentLocal(): bool
    {
        return strtolower(config('app.env')) !== Environment::PRODUCTION->value
            && strtolower(config('app.env')) !== Environment::STAGING->value;
    }
}

if (! function_exists('isEnvironmentPreProduction')) {
    function isEnvironmentPreProduction(): bool
    {
        return strtolower(config('app.env')) !== Environment::PRODUCTION->value;
    }
}

if (! function_exists('isEnvironmentProduction')) {
    function isEnvironmentProduction(): bool
    {
        return strtolower(config('app.env')) === Environment::PRODUCTION->value;
    }
}

if (! function_exists('appTimezone')) {
    function appTimezone(): string
    {
        return config('app.timezone');
    }
}

if (! function_exists('dateTimeBetweenTime')) {
    function dateTimeBetweenTime(Carbon $datetime, string $rangeStartTime, string $rangeEndTime): bool
    {
        if ($datetime->between(
            $datetime->clone()->setTimeFromTimeString($rangeStartTime),
            $datetime->clone()->setTimeFromTimeString($rangeEndTime)
        )) {
            return true;
        }

        return false;
    }
}

if (! function_exists('formatExceptionApiWebhookResponse')) {
    function formatExceptionApiWebhookResponse(
        Throwable $exception,
        $message,
        $statusCode = null,
        $errors = []
    ): array {
        if (! $errors) {
            $errors = (method_exists($exception, 'errors')) ? json_encode($exception->errors()) : [];
        }

        if (! $statusCode) {
            $statusCode = (method_exists($exception, 'getCode')) ? $exception->getCode() : null;
        }

        return [
            'message' => $message,
            'status_code' => $statusCode,
            'errors' => ($errors) ? json_encode($errors) : [],
        ];
    }
}

if (! function_exists('formatExceptionPayload')) {
    function formatExceptionPayload(
        Throwable $exception,
        $message,
        $statusCode = null,
        $errors = []
    ): array {
        if (! $errors) {
            $errors = (method_exists($exception, 'errors')) ? json_encode($exception->errors()) : [];
        }

        if (! $statusCode) {
            $statusCode = (method_exists($exception, 'getCode')) ? $exception->getCode() : null;
        }

        return [
            'message' => $message,
            'status_code' => $statusCode,
            'errors' => ($errors) ? json_encode($errors) : [],
            'exception' => sprintf('[Code:%s] %s.', $exception->getCode(), $exception->getMessage()),
            'file_and_line' => sprintf('%s:%s.', $exception->getFile(), $exception->getLine()),
            'exception_type' => get_class($exception),
        ];
    }
}

if (! function_exists('sanitizeSensitiveData')) {
    function sanitizeSensitiveData(
        string|array $value,
        array $ignoredFields
    ): string|array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                if (! str_contains($value, '=')) {
                    return $value;
                }

                parse_str($value, $parsed);

                if (empty($parsed)) {
                    return $value;
                }

                $value = $parsed;
            } else {
                $value = $decoded;
            }

        }

        $ignoredFields = array_map('strtolower', $ignoredFields);

        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $ignoredFields, true)) {
                $value[$key] = '********';
            } elseif (is_array($item)) {
                $value[$key] = sanitizeSensitiveData($item, $ignoredFields);
            }
        }

        return $value;
    }
}

if (! function_exists('jsonEncode')) {
    function jsonEncode(string|array|object $value): string
    {
        return is_string($value)
            ? $value
            : json_encode(
                $value,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            );
    }
}

if (!function_exists('getServerHostname')) {
    function getServerHostname(): string
    {
        return getenv('HOSTNAME') ?: php_uname('n');
    }
}

if (!function_exists('forceWriteln')) {
    function forceWriteln(string|null $message): void
    {
        if (defined('STDOUT') && is_resource(STDOUT)) {
            fwrite(STDOUT, $message . PHP_EOL);
            fflush(STDOUT);
            return;
        }
        echo $message . PHP_EOL;
    }
}

if (!function_exists('forceWritelnError')) {
    function forceWritelnError(string|null $message): void
    {
        if (defined('STDERR') && is_resource(STDERR)) {
            fwrite(STDERR, $message . PHP_EOL);
            fflush(STDERR);
            return;
        }
        error_log($message);
    }
}

if (!function_exists('stringifyUuid')) {
    function stringifyUuid(string|UuidInterface|Stringable|null $uuid): ?string
    {
        return match (true) {
            $uuid === null => null,
            $uuid instanceof UuidInterface => $uuid->toString(),
            $uuid instanceof Stringable
            || (is_object($uuid) && method_exists($uuid, '__toString')) => (string) $uuid,
            default => is_scalar($uuid) ? (string) $uuid : null,
        };
    }
}


if (!function_exists('dateFormat')) {
    function dateFormat(): string
    {
        return sprintf(
            '%s e',
            Format::DEFAULT_TO_STRING_FORMAT
        );
    }
}

if (!function_exists('prepareColumnForQuery')) {
    function prepareColumnForQuery(
        string $column,
        bool $includeBackticks = false
    ): string
    {
        $column = Str::remove('`', $column);

        if (!$includeBackticks) {
            return $column;
        }

        if (!Str::contains($column, '.')) {
            return Str::wrap($column, '`', '`');
        }

        return implode(
            '.',
            array_map(function ($value) {
                return Str::wrap($value, '`', '`');
            }, explode('.', $column))
        );
    }
}

if (!function_exists('isScheduledCommandQuietRunEnabled')) {
    function isScheduledCommandQuietRunEnabled(): bool
    {
        return (bool) config('app.console_scheduled_command_quiet_run_enabled', false);
    }
}
