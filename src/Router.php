<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<string, callable> */
    private array $getRoutes = [];

    /** @var array<string, callable> */
    private array $postRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->getRoutes[$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->postRoutes[$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $routes = strtoupper($method) === 'POST' ? $this->postRoutes : $this->getRoutes;

        if (!isset($routes[$path])) {
            http_response_code(404);
            echo '<h1>404</h1><p>Page not found.</p>';
            return;
        }

        $routes[$path]();
    }
}
