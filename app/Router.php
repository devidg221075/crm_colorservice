<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $handler = $this->routes[strtoupper($method)][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo 'Pagina non trovata';
            return;
        }
        try {
            $handler();
        } catch (HttpException $e) {
            if ($e->status === 401) {
                header('Location: /login');
                return;
            }
            http_response_code($e->status);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
