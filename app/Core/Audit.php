<?php

declare(strict_types=1);

namespace App\Core;

use Modules\Audit\Repository\AuditRepository;

/**
 * Pencatat log audit untuk Service. Konteks (pengguna + IP) diisi sekali per request oleh App.
 */
final class Audit
{
    private ?int $userId = null;
    private ?string $ip = null;

    public function __construct(private readonly AuditRepository $repo)
    {
    }

    public function setContext(?int $userId, ?string $ip): void
    {
        $this->userId = $userId;
        $this->ip = $ip;
    }

    /**
     * @param array<string,mixed>|null $data nilai sebelum/sesudah; kunci rahasia dibuang
     * @param bool $anonymous true untuk aksi tanpa pelaku terautentikasi (login gagal)
     */
    public function record(
        string $aksi,
        ?string $entitas = null,
        ?int $entitasId = null,
        ?array $data = null,
        ?int $userId = null,
        bool $anonymous = false
    ): void {
        $json = $data === null ? null : json_encode(
            self::sanitize($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $this->repo->insert(
            $anonymous ? null : ($userId ?? $this->userId),
            $aksi,
            $entitas,
            $entitasId,
            $json,
            $this->ip,
            date('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string|int,mixed> $data
     * @return array<string|int,mixed>
     */
    public static function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/password|token|csrf|secret/i', $key) === 1) {
                continue;
            }
            $out[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return $out;
    }
}
