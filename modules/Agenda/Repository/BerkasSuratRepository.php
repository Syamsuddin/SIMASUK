<?php

declare(strict_types=1);

namespace Modules\Agenda\Repository;

use App\Core\Database;

/** Metadata berkas scan; isi berkasnya hanya disentuh Core\FileStore (docs/12). */
class BerkasSuratRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function insert(int $suratId, string $path, string $namaAsli, int $ukuran, string $sha256, ?int $userId, string $now): int
    {
        $this->db->execute(
            'INSERT INTO berkas_surat (surat_id, path, nama_asli, ukuran, sha256, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$suratId, $path, $namaAsli, $ukuran, $sha256, $userId, $now]
        );
        return $this->db->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, surat_id, path, nama_asli, ukuran, sha256, deleted_at, created_at FROM berkas_surat WHERE id = ?',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> berkas aktif (belum dilepas), urut unggah */
    public function listActive(int $suratId): array
    {
        return $this->db->fetchAll(
            'SELECT id, nama_asli, ukuran, created_at FROM berkas_surat
             WHERE surat_id = ? AND deleted_at IS NULL ORDER BY id ASC',
            [$suratId]
        );
    }
}
