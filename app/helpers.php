<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = config('app.url', '');

        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $value = Session::flash('_old_' . $key);

        return $value !== null ? (string) $value : $default;
    }
}

if (!function_exists('partial')) {
    function partial(string $view, array $data = []): string
    {
        return View::render($view, $data, null);
    }
}

if (!function_exists('app_is_installed')) {
    function app_is_installed(): bool
    {
        static $installed = null;

        if ($installed !== null) {
            return $installed;
        }

        if (env('DB_DATABASE') === null || env('APP_KEY') === null || env('APP_KEY') === '') {
            return $installed = false;
        }

        try {
            Database::instance()->fetchOne('SELECT 1 FROM settings LIMIT 1');
            $installed = true;
        } catch (Throwable) {
            $installed = false;
        }

        return $installed;
    }
}

if (!function_exists('flash_message')) {
    function flash_message(string $key = 'status'): ?array
    {
        $value = Session::flash($key);

        return is_array($value) ? $value : null;
    }
}
