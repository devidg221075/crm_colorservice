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
        $params = [];
        $handler = $this->routes[strtoupper($method)][$path] ?? null;
        if ($handler === null) {
            [$handler, $params] = $this->matchParametrico(strtoupper($method), $path);
        }
        if ($handler === null) {
            http_response_code(404);
            echo 'Pagina non trovata';
            return;
        }
        try {
            $handler(...$params);
        } catch (HttpException $e) {
            if ($e->status === 401) {
                header('Location: /login');
                return;
            }
            http_response_code($e->status);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        } catch (\Throwable $e) {
            Errore500::mostra($e);
        }
    }

    /** Rotte con segnaposto {nome}: ogni segnaposto corrisponde a un segmento e arriva al handler come stringa. */
    private function matchParametrico(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            if (!str_contains($pattern, '{')) {
                continue;
            }
            $regex = '#^' . preg_replace('#\\\{[a-z_]+\\\}#i', '([^/]+)', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path, $m) === 1) {
                return [$handler, array_slice($m, 1)];
            }
        }
        return [null, []];
    }
}
