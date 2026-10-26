<?php

declare(strict_types=1);

namespace Modules\Dasbor\Repository;

use App\Core\Database;

/** Kueri baca ringkasan dasbor (docs/01 F-DASBOR). Tidak ada penulisan di sini. */
class DasborRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Surat yang diterima dalam rentang tanggal, tanpa surat yang dibatalkan. */
    public function countSuratDiterimaAntara(string $dari, string $sampai): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM surat_masuk WHERE tgl_terima BETWEEN ? AND ? AND status <> ?',
            [$dari, $sampai, 'dibatalkan']
        );
    }

    public function countBelumDidisposisi(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM surat_masuk WHERE status = ?', ['diterima']);
    }

    /**
     * Surat Diterima (belum punya disposisi), yang paling lama menunggu lebih dulu.
     *
     * @return list<array<string,mixed>>
     */
    public function listBelumDidisposisi(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT id, tahun, no_agenda, tgl_terima, asal, perihal, sifat FROM surat_masuk
             WHERE status = ? ORDER BY tgl_terima ASC, tahun ASC, no_agenda ASC LIMIT ?',
            ['diterima', $limit]
        );
    }

    /** Disposisi Dikirim yang tenggatnya sebelum $hari; surat Dibatalkan tidak dihitung. */
    public function countLewatTenggat(string $hari): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM disposisi d JOIN surat_masuk s ON s.id = d.surat_id
             WHERE d.status = ? AND d.tenggat < ? AND s.status <> ?',
            ['dikirim', $hari, 'dibatalkan']
        );
    }

    /**
     * @return list<array<string,mixed>> tenggat terlama lebih dulu
     */
    public function listLewatTenggat(string $hari, int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT d.id, d.surat_id, d.instruksi, d.tenggat, u.nama AS unit_nama,
                s.tahun, s.no_agenda, s.perihal, s.sifat
             FROM disposisi d
             JOIN surat_masuk s ON s.id = d.surat_id
             JOIN unit_kerja u ON u.id = d.unit_tujuan_id
             WHERE d.status = ? AND d.tenggat < ? AND s.status <> ?
             ORDER BY d.tenggat ASC, d.id ASC LIMIT ?',
            ['dikirim', $hari, 'dibatalkan', $limit]
        );
    }
}
