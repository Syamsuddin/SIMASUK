<?php

declare(strict_types=1);

namespace Modules\Master\Repository;

use App\Core\Database;

class UnitKerjaRepository
{
    private const KOLOM = 'id, parent_id, nama, jabatan_pimpinan, urutan, is_active, created_at, updated_at';

    public function __construct(private readonly Database $db)
    {
    }

    /** Mengembalikan unit apa pun statusnya, agar disposisi lama tetap bisa menampilkan nama unit nonaktif. */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT ' . self::KOLOM . ' FROM unit_kerja WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT ' . self::KOLOM . ' FROM unit_kerja ORDER BY urutan ASC, nama ASC, id ASC');
    }

    /** @return list<array<string,mixed>> */
    public function allActive(): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::KOLOM . ' FROM unit_kerja WHERE is_active = 1 ORDER BY urutan ASC, nama ASC, id ASC'
        );
    }

    /** @param array{parent_id:?int,nama:string,jabatan_pimpinan:?string,urutan:int} $data */
    public function insert(array $data, string $now): int
    {
        $this->db->execute(
            'INSERT INTO unit_kerja (parent_id, nama, jabatan_pimpinan, urutan, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$data['parent_id'], $data['nama'], $data['jabatan_pimpinan'], $data['urutan'], $now, $now]
        );
        return $this->db->lastInsertId();
    }

    /** @param array{parent_id:?int,nama:string,jabatan_pimpinan:?string,urutan:int} $data */
    public function update(int $id, array $data, string $now): void
    {
        $this->db->execute(
            'UPDATE unit_kerja SET parent_id = ?, nama = ?, jabatan_pimpinan = ?, urutan = ?, updated_at = ? WHERE id = ?',
            [$data['parent_id'], $data['nama'], $data['jabatan_pimpinan'], $data['urutan'], $now, $id]
        );
    }

    public function setActive(int $id, bool $active, string $now): void
    {
        $this->db->execute(
            'UPDATE unit_kerja SET is_active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, $now, $id]
        );
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM unit_kerja');
    }
}
