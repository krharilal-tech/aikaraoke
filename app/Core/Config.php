<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads /app/Config/*.php files (each returning an array) and exposes
 * dot-notation lookups, e.g. Config::get('database.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureLoaded();

        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private static function ensureLoaded(): void
    {
        if (self::$loaded) {
            return;
        }

        $configDir = __DIR__ . '/../Config';

        foreach (glob($configDir . '/*.php') as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }

        self::$loaded = true;
    }
}
