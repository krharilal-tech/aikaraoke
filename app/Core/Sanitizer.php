<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small collection of input validation/sanitization helpers used at the
 * boundary (controllers) before data touches the database or filesystem.
 */
final class Sanitizer
{
    public static function string(mixed $value, int $maxLength = 1000): string
    {
        $value = trim((string) $value);
        $value = strip_tags($value);

        return mb_substr($value, 0, $maxLength);
    }

    public static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function isValidUrl(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function isYoutubeUrl(mixed $value): bool
    {
        if (!self::isValidUrl($value)) {
            return false;
        }

        $host = parse_url((string) $value, PHP_URL_HOST) ?? '';
        $host = strtolower($host);

        $allowedHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'music.youtube.com'];

        return in_array($host, $allowedHosts, true);
    }

    public static function email(mixed $value): ?string
    {
        $filtered = filter_var((string) $value, FILTER_VALIDATE_EMAIL);

        return $filtered === false ? null : $filtered;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function filePathSegment(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $value) ?? '';

        return $value;
    }
}
