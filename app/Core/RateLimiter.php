<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-backed sliding-window rate limiter. Good enough for a single-server
 * WAMP/Apache deployment; swap for Redis if this is ever load-balanced
 * (see docs/DEPLOYMENT_UBUNTU.md).
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $file = self::fileFor($key);
        $now = time();

        $attempts = self::read($file);
        $attempts = array_values(array_filter($attempts, static fn (int $ts): bool => $ts > $now - $decaySeconds));

        if (count($attempts) >= $maxAttempts) {
            self::write($file, $attempts);

            return true;
        }

        $attempts[] = $now;
        self::write($file, $attempts);

        return false;
    }

    public static function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        $file = self::fileFor($key);
        $now = time();

        $attempts = self::read($file);
        $attempts = array_filter($attempts, static fn (int $ts): bool => $ts > $now - $decaySeconds);

        return max(0, $maxAttempts - count($attempts));
    }

    private static function fileFor(string $key): string
    {
        $dir = Config::get('paths.tmp') . '/rate_limits';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * @return array<int, int>
     */
    private static function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            return [];
        }

        flock($handle, LOCK_SH);
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $decoded = json_decode((string) $contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, int> $attempts
     */
    private static function write(string $file, array $attempts): void
    {
        $handle = fopen($file, 'c');

        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($attempts)));
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
