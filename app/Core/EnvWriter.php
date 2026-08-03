<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rewrites/creates keys in a .env file while preserving every other line
 * (comments, blank lines, unrelated keys) untouched.
 */
final class EnvWriter
{
    /**
     * @param array<string, string> $values
     */
    public static function set(string $path, array $values): void
    {
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $remaining = $values;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key] = explode('=', $trimmed, 2);
            $key = trim($key);

            if (array_key_exists($key, $remaining)) {
                $lines[$index] = self::formatLine($key, $remaining[$key]);
                unset($remaining[$key]);
            }
        }

        foreach ($remaining as $key => $value) {
            $lines[] = self::formatLine($key, $value);
        }

        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }

    private static function formatLine(string $key, string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\-\/:]*$/', $value) === 1) {
            return "{$key}={$value}";
        }

        $escaped = str_replace('"', '\\"', $value);

        return "{$key}=\"{$escaped}\"";
    }
}
