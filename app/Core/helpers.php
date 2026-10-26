<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\View;

/** Escape keluaran HTML. Semua data dinamis di view wajib lewat sini (docs/21). */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string,scalar|null> $query */
function url(string $path, array $query = []): string
{
    $query = array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '');
    return $path . ($query === [] ? '' : '?' . http_build_query($query));
}

function asset(string $path): string
{
    $file = dirname(__DIR__, 2) . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

function old(string $field, mixed $default = ''): string
{
    $old = View::shared('old', []);
    $value = is_array($old) && array_key_exists($field, $old) ? $old[$field] : $default;
    return is_scalar($value) ? (string) $value : '';
}

function error(string $field): ?string
{
    $errors = View::shared('errors', []);
    return is_array($errors) && isset($errors[$field]) ? (string) $errors[$field] : null;
}

/** @return array<string,string> */
function errors(): array
{
    $errors = View::shared('errors', []);
    return is_array($errors) ? $errors : [];
}

function csrf_token(): string
{
    $session = View::shared('session');
    return $session === null ? '' : Csrf::token($session);
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(csrf_token()) . '">';
}

/** @param array<string,mixed> $data */
function component(string $name, array $data = []): string
{
    return View::partial(View::component($name), $data);
}

function icon(string $name, string $class = 'size-5'): string
{
    return View::partial(View::component('icons/' . $name), ['class' => $class]);
}

/** Tanggal tampilan "26 Okt 2026" (docs/26). */
function tgl(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function tgl_waktu(?string $datetime): string
{
    $date = tgl($datetime);
    return $date === '' ? '' : $date . ' ' . date('H.i', (int) strtotime((string) $datetime));
}

/** Penanda nav aktif: path sama persis, atau berada di bawah prefix. */
function nav_active(string $prefix): bool
{
    $path = (string) View::shared('path', '/');
    return $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, $prefix . '/'));
}
