<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\Middleware;
use App\Middleware\RoleMiddleware;

/**
 * Container sederhana + kernel HTTP (docs/08). Kelas tanpa factory dirakit otomatis dari tipe
 * parameter konstruktornya; Database, Logger, Session, dan ErrorHandler didaftarkan di bootstrap.
 */
final class App
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'",
        'Cache-Control' => 'no-store',
    ];

    /** @var array<string,callable(self):object> */
    private array $factories = [];

    /** @var array<string,object> */
    private array $instances = [];

    public function __construct(private readonly Router $router)
    {
    }

    /** @param callable(self):object $factory */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        $object = isset($this->factories[$id]) ? ($this->factories[$id])($this) : $this->build($id);
        return $this->instances[$id] = $object;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        $started = microtime(true);
        $session = $this->get(Session::class);
        $logger = $this->get(Logger::class);
        $logger->setRequestContext(['method' => $request->method, 'path' => $request->path]);

        $flash = $session->pull('_flash', []);
        View::reset();
        View::share('session', $session);
        View::share('path', $request->path);
        View::share('toast', $flash['toast'] ?? null);
        View::share('errors', $flash['errors'] ?? []);
        View::share('old', $flash['old'] ?? []);

        try {
            $response = (new CsrfMiddleware($session))->handle($request, function (Request $request): Response {
                $route = $this->router->match($request->method, $request->path);
                return $this->pipeline($route['middleware'], function (Request $request) use ($route): Response {
                    [$class, $action] = $route['handler'];
                    return $this->get($class)->{$action}($request, ...$route['params']);
                })($request);
            });
        } catch (ValidationException $e) {
            $session->flash('errors', $e->errors());
            $session->flash('old', self::oldInput($request->post));
            $response = Response::redirect($request->backUrl());
        } catch (DomainRuleException $e) {
            $session->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
            $response = Response::redirect($request->backUrl());
        } catch (\Throwable $e) {
            $response = $this->get(ErrorHandler::class)->render($e);
        }

        foreach (self::HEADERS as $name => $value) {
            $response->headers[$name] ??= $value;
        }

        $elapsed = microtime(true) - $started;
        if ($elapsed > 2.0) {
            $logger->notice('Request lambat', ['detik' => round($elapsed, 2)]);
        }
        return $response;
    }

    /**
     * @param list<string> $names
     * @param \Closure(Request):Response $core
     * @return \Closure(Request):Response
     */
    private function pipeline(array $names, \Closure $core): \Closure
    {
        $next = $core;
        foreach (array_reverse($names) as $name) {
            $middleware = $this->middleware($name);
            $inner = $next;
            $next = static fn (Request $request): Response => $middleware->handle($request, $inner);
        }
        return $next;
    }

    private function middleware(string $name): Middleware
    {
        [$key, $param] = array_pad(explode(':', $name, 2), 2, null);
        return match ($key) {
            'auth' => $this->get(AuthMiddleware::class),
            'guest' => $this->get(GuestMiddleware::class),
            'role' => new RoleMiddleware(explode(',', (string) $param)),
            default => throw new \LogicException("Middleware tidak dikenal: {$name}"),
        };
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed> isian lama tanpa password/token
     */
    private static function oldInput(array $post): array
    {
        return array_filter(
            $post,
            static fn (mixed $v, string|int $k): bool => is_scalar($v)
                && preg_match('/password|_token/i', (string) $k) !== 1,
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new \LogicException("Kelas tidak ditemukan: {$class}");
        }
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return new $class();
        }
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $name = $type->getName();
                if ($name !== \Closure::class) {
                    $args[] = $this->get($name);
                    continue;
                }
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new \LogicException("Parameter {$param->getName()} pada {$class} tidak bisa dirakit.");
        }
        return new $class(...$args);
    }
}
