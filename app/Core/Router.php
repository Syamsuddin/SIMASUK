<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pencocok rute dari config/routes.php: [method, path, [Controller::class, 'aksi'], middleware[]].
 * Segmen {nama} hanya cocok dengan angka.
 */
final class Router
{
    /** @param list<array{0:string,1:string,2:array{0:class-string,1:string},3:list<string>}> $routes */
    public function __construct(private readonly array $routes)
    {
    }

    /** @return list<array{0:string,1:string,2:array{0:class-string,1:string},3:list<string>}> */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @return array{handler:array{0:class-string,1:string},middleware:list<string>,params:list<int>}
     * @throws HttpException 404 bila tak ada rute yang cocok
     */
    public function match(string $method, string $path): array
    {
        $path = Request::normalizePath($path);
        foreach ($this->routes as [$routeMethod, $routePath, $handler, $middleware]) {
            if ($routeMethod !== $method) {
                continue;
            }
            $pattern = '#^' . preg_replace('#\\\{[a-z_]+\\\}#', '(\d{1,10})', preg_quote($routePath, '#')) . '$#';
            if (preg_match($pattern, $path, $m) === 1) {
                return [
                    'handler' => $handler,
                    'middleware' => $middleware,
                    'params' => array_map('intval', array_slice($m, 1)),
                ];
            }
        }
        throw new HttpException(404);
    }
}
