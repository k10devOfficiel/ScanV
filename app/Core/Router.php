<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;
use App\Middleware\MiddlewareInterface;
use RuntimeException;

/**
 * Lightweight HTTP Router supporting GET/POST, URL parameters, and middlewares.
 */
class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, regex: string, handler: mixed, middlewares: array<int, string|MiddlewareInterface>}>
     */
    private array $routes = [];

    /**
     * @var array<int, string|MiddlewareInterface>
     */
    private array $globalMiddlewares = [];

    /**
     * @var mixed
     */
    private $notFoundHandler = [ErrorController::class, 'notFound'];

    public function use(string|MiddlewareInterface $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function get(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function addRoute(string $method, string $path, mixed $handler, array $middlewares = []): self
    {
        $normalizedPath = '/' . trim($path, '/');
        if ($path === '/') {
            $normalizedPath = '/';
        }

        // Convert {param} placeholders to named regex groups: (?P<param>[^/]+)
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $normalizedPath);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalizedPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];

        return $this;
    }

    public function setNotFoundHandler(mixed $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Dispatch the incoming request through matching route and middlewares.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                // Extract named parameters
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                // Combine global and route middlewares
                $allMiddlewares = array_merge($this->globalMiddlewares, $route['middlewares']);

                return $this->runPipeline($request, $allMiddlewares, function (Request $req) use ($route, $params): Response {
                    return $this->executeHandler($route['handler'], $req, $params);
                });
            }
        }

        // No matching route found -> 404
        return $this->executeHandler($this->notFoundHandler, $request, []);
    }

    /**
     * Execute middleware pipeline recursively.
     *
     * @param array<int, string|MiddlewareInterface> $middlewares
     * @param callable(Request): Response $target
     */
    private function runPipeline(Request $request, array $middlewares, callable $target): Response
    {
        if (empty($middlewares)) {
            return $target($request);
        }

        $current = array_shift($middlewares);
        $middlewareInstance = is_string($current) ? new $current() : $current;

        if (!$middlewareInstance instanceof MiddlewareInterface) {
            throw new RuntimeException("Middleware must implement MiddlewareInterface.");
        }

        return $middlewareInstance->handle($request, function (Request $req) use ($middlewares, $target): Response {
            return $this->runPipeline($req, $middlewares, $target);
        });
    }

    /**
     * Invoke controller action or closure.
     *
     * @param mixed $handler
     * @param Request $request
     * @param array<string, string> $params
     */
    private function executeHandler(mixed $handler, Request $request, array $params = []): Response
    {
        $result = null;

        if (is_callable($handler)) {
            $result = call_user_func($handler, $request, ...array_values($params));
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            $result = $instance->$method($request, ...array_values($params));
        } else {
            throw new RuntimeException("Invalid route handler configured.");
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html('', 204);
    }
}
