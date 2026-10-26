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

    /**
     * @param array{user_id?:string,aksi?:string,dari?:string,sampai?:string} $filter dari/sampai Y-m-d, inklusif
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filter, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if (($filter['user_id'] ?? '') !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filter['user_id'];
        }
        if (($filter['aksi'] ?? '') !== '') {
            $where[] = 'a.aksi = ?';
            $params[] = $filter['aksi'];
        }
        // Batas atas eksklusif hari berikutnya agar indeks created_at tetap terpakai.
        if (($filter['dari'] ?? '') !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $filter['dari'] . ' 00:00:00';
        }
        if (($filter['sampai'] ?? '') !== '') {
            $where[] = 'a.created_at < ?';
            $params[] = date('Y-m-d', (int) strtotime($filter['sampai'] . ' +1 day')) . ' 00:00:00';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM log_audit a' . $whereSql, $params);
        $rows = $this->db->fetchAll(
            'SELECT a.id, a.user_id, a.aksi, a.entitas, a.entitas_id, a.data_json, a.ip, a.created_at,
                u.nama AS user_nama, u.username AS user_username
             FROM log_audit a LEFT JOIN users u ON u.id = a.user_id' . $whereSql
            . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<string> aksi yang pernah tercatat, urut abjad */
    public function actions(): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['aksi'],
            $this->db->fetchAll('SELECT DISTINCT aksi FROM log_audit ORDER BY aksi')
        );
    }
}
