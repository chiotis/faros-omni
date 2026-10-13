<?php

declare(strict_types=1);

namespace Omni\Core;

/** Matches the method and path of a request to a handler; `{id}` in a path is passed to the handler. */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /** Runs the handler and returns true, or returns false when nothing matches. */
    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('#\{[a-z_]+\}#', '([A-Za-z0-9_-]+)', $route) . '$#';
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                $handler(...$matches);
                return true;
            }
        }
        return false;
    }
}
