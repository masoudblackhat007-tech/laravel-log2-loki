<?php

namespace App\Logging;

use Illuminate\Support\Str;

final class SensitiveDataRedactor
{
    private const MAX_STRING_LENGTH = 500;

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'set-cookie',
        'api_key',
        'apikey',
        'secret',
        'client_secret',
        'session',
        'session_id',
        'csrf',
        '_token',
    ];

    public static function hashIdentifier(null|string|int $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = (string) config('logging.hash_key', config('app.key', 'change-me'));

        return substr(hash_hmac('sha256', (string) $value, $key), 0, 32);
    }

    public static function maskIp(?string $ip): ?string
    {
        if (! is_string($ip) || trim($ip) === '') {
            return null;
        }

        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);

            if ($packed === false) {
                return Str::limit($ip, 40, '');
            }

            $hex = bin2hex($packed);
            $masked = substr($hex, 0, 16).str_repeat('0', 16);
            $unpacked = @inet_ntop(hex2bin($masked));

            return $unpacked !== false ? $unpacked : Str::limit($ip, 40, '');
        }

        return Str::limit($ip, 40, '');
    }

    public static function sanitizeArray(array $data, int $depth = 0): array
    {
        if ($depth > 4) {
            return ['_truncated' => true];
        }

        $clean = [];

        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if (self::isSensitiveKey($keyString)) {
                $clean[$keyString] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $clean[$keyString] = self::sanitizeArray($value, $depth + 1);
                continue;
            }

            if (is_string($value)) {
                $clean[$keyString] = Str::limit($value, self::MAX_STRING_LENGTH, '...');
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$keyString] = $value;
                continue;
            }

            $clean[$keyString] = '[UNSERIALIZABLE]';
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
