<?php
declare(strict_types=1);

namespace Natillera\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $this->normalizePath($path) : '/';
        $method = strtoupper($method);

        if (isset($this->routes[$path][$method])) {
            ($this->routes[$path][$method])();
            return;
        }

        if (isset($this->routes[$path])) {
            header('Allow: ' . implode(', ', array_keys($this->routes[$path])));
            http_response_code(405);
            echo 'Método no permitido';
            return;
        }

        http_response_code(404);
        echo 'Página no encontrada';
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $path = $this->normalizePath($path);
        $this->routes[$path][$method] = $handler;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
