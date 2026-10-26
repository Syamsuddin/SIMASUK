<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Parser .env minimal (tanpa pustaka, docs/09): KEY=VALUE per baris, # komentar,
 * nilai boleh diapit tanda kutip. Variabel lingkungan proses tidak ditimpa.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Berkas .env tidak ditemukan.');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines === false ? [] : $lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $fromProcess = getenv($key);
            self::$values[$key] = $fromProcess === false ? $value : $fromProcess;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }
        $fromProcess = getenv($key);
        return $fromProcess === false ? $default : $fromProcess;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
