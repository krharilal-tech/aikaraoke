<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, regex: string, handler: array{0: string, 1: string}, auth: bool}>
     */
    private array $routes = [];

    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $pattern, array $handler, bool $auth = true): void
    {
        $this->add('GET', $pattern, $handler, $auth);
    }

    public function post(string $pattern, array $handler, bool $auth = true): void
    {
        $this->add('POST', $pattern, $handler, $auth);
    }

    /**
     * @param array{0: string, 1: string} $handler
     */
    private function add(string $method, string $pattern, array $handler, bool $auth): void
    {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'auth' => $auth,
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }

        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                if ($route['auth'] && !Auth::check()) {
                    $this->denyUnauthenticated($request, $path);

                    return;
                }

                $params = array_filter(
                    $matches,
                    static fn (int|string $key): bool => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                $request->setRouteParams($params);

                [$controllerClass, $method] = $route['handler'];
                $controller = new $controllerClass();
                $controller->$method($request);

                return;
            }
        }

        Response::notFound('404 - Page not found');
    }

    private function denyUnauthenticated(Request $request, string $strippedPath): void
    {
        if ($request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Please sign in.'], 401);
        }

        $next = urlencode($strippedPath);
        Response::redirect(base_url('login') . '?next=' . $next);
    }
}
