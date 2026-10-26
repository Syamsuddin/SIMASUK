<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Templating PHP polos (docs/08). Nama view:
 *   'Pengguna::index'  -> modules/Pengguna/views/index.php
 *   'errors/403'       -> resources/views/errors/403.php
 */
final class View
{
    /** @var array<string,mixed> data bersama satu request: pengguna, token CSRF, flash */
    private static array $shared = [];

    private static ?string $root = null;

    public static function setRoot(string $root): void
    {
        self::$root = rtrim($root, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    public static function reset(): void
    {
        self::$shared = [];
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layout/app'): string
    {
        $content = self::partial(self::path($view), $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial(self::path($layout), [...$data, 'content' => $content]);
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $file, array $data = []): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . basename($file));
        }
        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    public static function path(string $view): string
    {
        $root = self::$root ?? dirname(__DIR__, 2);
        if (str_contains($view, '::')) {
            [$module, $name] = explode('::', $view, 2);
            return $root . '/modules/' . $module . '/views/' . $name . '.php';
        }
        return $root . '/resources/views/' . $view . '.php';
    }

    public static function component(string $name): string
    {
        $root = self::$root ?? dirname(__DIR__, 2);
        return $root . '/resources/views/components/' . $name . '.php';
    }
}
