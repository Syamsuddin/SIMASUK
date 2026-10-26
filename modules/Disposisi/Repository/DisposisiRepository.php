<?php

declare(strict_types=1);

namespace Modules\Disposisi\Repository;

use App\Core\Database;

class DisposisiRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{surat_id:int,parent_id:?int,unit_tujuan_id:int,tgl_disposisi:string,instruksi:string,
     *   catatan:?string,tenggat:?string,status:string,selesai_at:?string,catatan_selesai:?string,created_by:?int} $data
     */
    public function insert(array $data, string $now): int
    {
        $this->db->execute(
            'INSERT INTO disposisi (surat_id, parent_id, unit_tujuan_id, tgl_disposisi, instruksi, catatan, tenggat,
                status, selesai_at, catatan_selesai, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['surat_id'], $data['parent_id'], $data['unit_tujuan_id'], $data['tgl_disposisi'],
                $data['instruksi'], $data['catatan'], $data['tenggat'], $data['status'], $data['selesai_at'],
                $data['catatan_selesai'], $data['created_by'], $now, $now,
            ]
        );
        return $this->db->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT d.id, d.surat_id, d.parent_id, d.unit_tujuan_id, d.tgl_disposisi, d.instruksi, d.catatan,
                d.tenggat, d.status, d.selesai_at, d.catatan_selesai,
                u.nama AS unit_nama, u.jabatan_pimpinan AS unit_jabatan
             FROM disposisi d
             JOIN unit_kerja u ON u.id = d.unit_tujuan_id
             WHERE d.id = ?',
            [$id]
        );
    }

    /**
     * Seluruh disposisi satu surat, urut pencatatan; induk selalu tercatat sebelum lanjutannya.
     *
     * @return list<array<string,mixed>>
     */
    public function listBySurat(int $suratId): array
    {
        return $this->db->fetchAll(
            'SELECT d.id, d.surat_id, d.parent_id, d.unit_tujuan_id, d.tgl_disposisi, d.instruksi, d.catatan,
                d.tenggat, d.status, d.selesai_at, d.catatan_selesai, d.created_at,
                u.nama AS unit_nama, u.jabatan_pimpinan AS unit_jabatan, us.nama AS created_by_nama
             FROM disposisi d
             JOIN unit_kerja u ON u.id = d.unit_tujuan_id
             LEFT JOIN users us ON us.id = d.created_by
             WHERE d.surat_id = ?
             ORDER BY d.id',
            [$suratId]
        );
    }

    /** @return list<int> */
    public function openIdsBySurat(int $suratId): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['id'],
            $this->db->fetchAll('SELECT id FROM disposisi WHERE surat_id = ? AND status = ? ORDER BY id', [$suratId, 'dikirim'])
        );
    }

    /** Mengembalikan jumlah baris yang berubah; 0 berarti disposisi sudah selesai lebih dulu. */
    public function markDone(int $id, ?string $catatan, string $now): int
    {
        return $this->db->execute(
            'UPDATE disposisi SET status = ?, selesai_at = ?, catatan_selesai = ?, updated_at = ?
             WHERE id = ? AND status = ?',
            ['selesai', $now, $catatan, $now, $id, 'dikirim']
        );
    }

    /** Menutup semua disposisi yang masih dikirim pada satu surat; disposisi yang sudah selesai tidak disentuh. */
    public function closeOpenBySurat(int $suratId, string $catatan, string $now): int
    {
        return $this->db->execute(
            'UPDATE disposisi SET status = ?, selesai_at = ?, catatan_selesai = ?, updated_at = ?
             WHERE surat_id = ? AND status = ?',
            ['selesai', $now, $catatan, $now, $suratId, 'dikirim']
        );
    }
}
