<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, string> route parameters resolved by the Router */
    private array $routeParams = [];

    private string $rawBody = '';

    public function __construct()
    {
        $this->query = $_GET;
        $this->server = $_SERVER;
        $this->body = $this->parseBody();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBody(): array
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            // Cached rather than re-read: php://input is only reliably
            // readable once, and a webhook signature check (e.g.
            // PaymentController's Cashfree verification) needs these exact
            // bytes — re-serializing the decoded array below isn't
            // guaranteed byte-identical to what the sender actually signed
            // (key order/whitespace can differ).
            $this->rawBody = file_get_contents('php://input') ?: '';
            $decoded = json_decode($this->rawBody ?: '{}', true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    /**
     * The exact raw request body bytes, for callers that need to verify a
     * signature computed over the original payload rather than the
     * JSON-decoded (and PHP-re-encodable, but not byte-identical) array.
     * Empty for non-JSON requests, since parseBody() never populates it.
     */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return rawurldecode($path);
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
