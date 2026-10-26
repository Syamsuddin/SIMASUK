<?php

declare(strict_types=1);

namespace Modules\Master\Repository;

use App\Core\Database;

class KlasifikasiRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, kode, uraian, is_active, created_at, updated_at FROM klasifikasi_arsip WHERE id = ?',
            [$id]
        );
    }

    public function kodeExists(string $kode, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            return $this->db->fetchValue('SELECT 1 FROM klasifikasi_arsip WHERE kode = ?', [$kode]) !== null;
        }
        return $this->db->fetchValue(
            'SELECT 1 FROM klasifikasi_arsip WHERE kode = ? AND id <> ?',
            [$kode, $exceptId]
        ) !== null;
    }

    /**
     * @param array{q?:string,status?:string} $filter
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filter, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if (($filter['q'] ?? '') !== '') {
            $where[] = '(kode LIKE ? OR uraian LIKE ?)';
            $like = '%' . addcslashes($filter['q'], '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (($filter['status'] ?? '') !== '') {
            $where[] = 'is_active = ?';
            $params[] = $filter['status'] === 'aktif' ? 1 : 0;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM klasifikasi_arsip' . $whereSql, $params);
        $rows = $this->db->fetchAll(
            'SELECT id, kode, uraian, is_active FROM klasifikasi_arsip' . $whereSql
            . ' ORDER BY kode ASC LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<array<string,mixed>> */
    public function activeOptions(): array
    {
        return $this->db->fetchAll(
            'SELECT id, kode, uraian FROM klasifikasi_arsip WHERE is_active = 1 ORDER BY kode ASC'
        );
    }

    public function insert(string $kode, string $uraian, string $now): int
    {
        $this->db->execute(
            'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            [$kode, $uraian, $now, $now]
        );
        return $this->db->lastInsertId();
    }

    public function update(int $id, string $kode, string $uraian, string $now): void
    {
        $this->db->execute(
            'UPDATE klasifikasi_arsip SET kode = ?, uraian = ?, updated_at = ? WHERE id = ?',
            [$kode, $uraian, $now, $id]
        );
    }

    public function setActive(int $id, bool $active, string $now): void
    {
        $this->db->execute(
            'UPDATE klasifikasi_arsip SET is_active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, $now, $id]
        );
    }
}
