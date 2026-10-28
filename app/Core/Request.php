<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request representation.
 */
class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $postParams;
    private array $serverParams;
    private array $attributes = [];

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $postParams = [],
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $this->normalizePath($path);
        $this->queryParams = $queryParams;
        $this->postParams = $postParams;
        $this->serverParams = $serverParams;
    }

    /**
     * Create a Request instance from PHP globals.
     */
    public static function createFromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Support method override via _method field or X-HTTP-Method-Override header
        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = strtoupper((string) $_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        $post = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            $json = json_decode($rawBody, true);
            if (is_array($json)) {
                $post = array_merge($post, $json);
            }
        }

        return new self($method, $path, $_GET, $post, $_SERVER);
    }

    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }
        $normalized = '/' . trim($trimmed, '/');
        return $normalized;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postParams;
        }
        return $this->postParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->postParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->postParams);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->serverParams[$serverKey] ?? $default;
    }

    public function isAjax(): bool
    {
        return ($this->serverParams['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($this->serverParams['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function getIp(): string
    {
        return (string) ($this->serverParams['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
