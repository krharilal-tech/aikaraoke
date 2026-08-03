<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $url): never
    {
        Response::redirect($url);
    }

    protected function requireCsrf(Request $request): void
    {
        $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            $this->json(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
        }
    }

    protected function rateLimit(Request $request, string $bucket, int $maxAttempts, int $decaySeconds): void
    {
        $key = $bucket . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            $this->json(['success' => false, 'message' => 'Too many requests. Please slow down and try again shortly.'], 429);
        }
    }
}
