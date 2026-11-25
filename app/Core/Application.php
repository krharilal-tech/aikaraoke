<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    public static function boot(string $rootPath): void
    {
        Env::load($rootPath . '/.env');

        date_default_timezone_set(Config::get('app.timezone', 'UTC'));

        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('app.debug', false) ? '1' : '0');

        set_exception_handler(static function (Throwable $e): void {
            Logger::error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            http_response_code(500);

            if (Config::get('app.debug', false)) {
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo 'Something went wrong. Please try again later.';
            }
        });

        Session::start();

        self::sendSecurityHeaders();
    }

    private static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Cashfree's checkout (pricing page) loads its script from
        // sdk.cashfree.com and talks to *.cashfree.com; without these the
        // browser blocks it and "Continue to Payment" never gets past
        // "Please wait…" ("Cashfree is not defined").
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' https://sdk.cashfree.com; connect-src 'self' https://*.cashfree.com; "
            . "frame-src https://*.cashfree.com; media-src 'self' blob:;");
    }

    public static function run(Router $router): void
    {
        $request = new Request();
        Auth::enforceNotBlocked($request);
        $router->dispatch($request);
    }
}
