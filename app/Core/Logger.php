<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Log aplikasi: satu baris JSON per kejadian di LOG_PATH/app-YYYY-MM-DD.log (docs/15).
 * Jangan pernah mengirim password, token, isi .env, isi berkas, atau perihal surat rahasia ke sini.
 */
final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5];

    /** @var array<string,mixed> */
    private array $request = [];

    public function __construct(private readonly string $dir, private readonly string $minLevel = 'info')
    {
    }

    /** @param array<string,mixed> $request user_id, method, path untuk request berjalan */
    public function setRequestContext(array $request): void
    {
        $this->request = $request;
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $msg, array $context = [], ?string $errorId = null): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 1)) {
            return;
        }
        $line = array_filter([
            'ts' => date('c'),
            'level' => $level,
            'error_id' => $errorId,
            'user_id' => $this->request['user_id'] ?? null,
            'method' => $this->request['method'] ?? null,
            'path' => $this->request['path'] ?? null,
            'msg' => $msg,
            'context' => $context === [] ? null : Audit::sanitize($context),
        ], static fn (mixed $v): bool => $v !== null);

        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            error_log('SIMASUK: folder log tidak bisa dibuat');
            return;
        }
        $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        file_put_contents($this->dir . '/app-' . date('Y-m-d') . '.log', $json . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $context */
    public function info(string $msg, array $context = []): void
    {
        $this->log('info', $msg, $context);
    }

    /** @param array<string,mixed> $context */
    public function notice(string $msg, array $context = []): void
    {
        $this->log('notice', $msg, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $msg, array $context = []): void
    {
        $this->log('warning', $msg, $context);
    }
}
