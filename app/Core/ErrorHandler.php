<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Handler global (docs/14): warning menjadi ErrorException, exception menjadi halaman errors/*,
 * detail hanya ke log. Pesan exception, SQL, path, dan trace tidak pernah dikirim ke peramban.
 */
final class ErrorHandler
{
    private const PESAN = [
        403 => 'Anda tidak memiliki akses ke halaman ini.',
        404 => 'Data tidak ditemukan.',
        419 => 'Sesi berakhir. Muat ulang halaman lalu coba lagi.',
        500 => 'Terjadi kesalahan.',
    ];

    public function __construct(private readonly Logger $logger)
    {
    }

    public function register(): void
    {
        ini_set('display_errors', '0');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        set_exception_handler(function (\Throwable $e): void {
            $this->render($e)->send();
        });
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->render(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']))
                    ->send();
            }
        });
    }

    public function render(\Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->status() : 500;
        if (!isset(self::PESAN[$status])) {
            $status = 500;
        }
        $errorId = null;

        if ($status === 403) {
            $this->logger->warning('Akses ditolak');
        } elseif ($status === 419) {
            $this->logger->notice('Token CSRF tidak valid atau sesi kedaluwarsa');
        } elseif ($status === 500) {
            $errorId = bin2hex(random_bytes(4));
            $this->logger->log($e instanceof \PDOException ? 'error' : 'critical', $e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], $errorId);
        }

        try {
            $body = View::render('errors/' . $status, [
                'title' => self::PESAN[$status],
                'pesan' => self::PESAN[$status],
                'errorId' => $errorId,
            ], 'layout/auth');
        } catch (\Throwable) {
            $body = '<!doctype html><meta charset="utf-8"><title>Kesalahan</title><p>'
                . e(self::PESAN[$status]) . ($errorId !== null ? ' Kode: ' . e($errorId) : '') . '</p>';
        }
        return Response::html($body, $status);
    }
}
