<?php

declare(strict_types=1);

namespace Cliniva\Core;

use Cliniva\Middleware\RbacMiddleware;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable|array, roles:array<int, string>}> > */
    private $routes = [];

    /** @param array<int, string> $roles */
    public function get(string $path, $handler, array $roles = []): void
    {
        $this->add('GET', $path, $handler, $roles);
    }

    /** @param array<int, string> $roles */
    public function post(string $path, $handler, array $roles = []): void
    {
        $this->add('POST', $path, $handler, $roles);
    }

    /** @param array<int, string> $roles */
    private function add(string $method, string $path, $handler, array $roles = []): void
    {
        $normalizedPath = $path === '/' ? '/' : rtrim($path, '/');
        $this->routes[$method][] = [
            'pattern' => '#^' . $normalizedPath . '$#',
            'handler' => $handler,
            'roles' => $roles,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $basePath = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        if ($basePath === '/' || $basePath === '.') {
            $basePath = '';
        }

        $uriPath = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($basePath !== '' && ($uriPath === $basePath || strpos($uriPath, $basePath . '/') === 0)) {
            $uriPath = substr($uriPath, strlen($basePath));
        }

        $path = '/' . trim($uriPath, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            (new RbacMiddleware())->handle($route['roles']);
            array_shift($matches);
            $this->call($route['handler'], $matches);
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Page introuvable']);
    }

    /** @param array<int, string> $parameters */
    private function call($handler, array $parameters): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler[0] = new $handler[0]();
        }

        call_user_func_array($handler, $parameters);
    }
}
