<?php

declare(strict_types=1);

namespace Modules\Audit\Repository;

use App\Core\Database;

/** log_audit bersifat append-only: repository ini sengaja tidak punya UPDATE maupun DELETE (docs/07). */
class AuditRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function insert(
        ?int $userId,
        string $aksi,
        ?string $entitas,
        ?int $entitasId,
        ?string $dataJson,
        ?string $ip,
        string $now
    ): void {
        $this->db->execute(
            'INSERT INTO log_audit (user_id, aksi, entitas, entitas_id, data_json, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $aksi, $entitas, $entitasId, $dataJson, $ip, $now]
        );
    }
}
