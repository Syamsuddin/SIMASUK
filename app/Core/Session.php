<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembungkus $_SESSION (opsi cookie diatur di bootstrap, docs/21). Di tes memakai array biasa.
 */
final class Session
{
    /** @var array<string,mixed> */
    private array $store;

    /** @param array<string,mixed> $store */
    public function __construct(array &$store, private readonly bool $native = false)
    {
        $this->store = &$store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->store[$key] ?? $default;
        unset($this->store[$key]);
        return $value;
    }

    /** Data untuk request berikutnya saja (toast, error validasi, isian lama). */
    public function flash(string $key, mixed $value): void
    {
        $this->store['_flash'][$key] = $value;
    }

    public function regenerate(): void
    {
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Kosongkan sesi dan ganti id (logout, sesi kedaluwarsa). */
    public function invalidate(): void
    {
        foreach (array_keys($this->store) as $key) {
            unset($this->store[$key]);
        }
        $this->regenerate();
    }
}
