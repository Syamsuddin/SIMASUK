<?php

declare(strict_types=1);

namespace Modules\Agenda\Repository;

use App\Core\Database;

class SuratMasukRepository
{
    private const LIST_COLUMNS = 's.id, s.tahun, s.no_agenda, s.tgl_terima, s.no_surat, s.tgl_surat, s.asal, s.perihal,
        s.sifat, s.status, k.kode AS klasifikasi_kode';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{tahun:int,no_agenda:int,tgl_terima:string,no_surat:string,tgl_surat:string,asal:string,
     *   perihal:string,sifat:string,klasifikasi_id:int,no_srikandi:?string,jumlah_lampiran:?string,status:string,
     *   cancelled_at:?string,cancel_reason:?string,created_by:?int} $data
     */
    public function insert(array $data, string $now): int
    {
        $this->db->execute(
            'INSERT INTO surat_masuk (tahun, no_agenda, tgl_terima, no_surat, tgl_surat, asal, perihal, sifat,
                klasifikasi_id, no_srikandi, jumlah_lampiran, status, cancelled_at, cancel_reason,
                created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['tahun'], $data['no_agenda'], $data['tgl_terima'], $data['no_surat'], $data['tgl_surat'],
                $data['asal'], $data['perihal'], $data['sifat'], $data['klasifikasi_id'], $data['no_srikandi'],
                $data['jumlah_lampiran'], $data['status'], $data['cancelled_at'], $data['cancel_reason'],
                $data['created_by'], $data['created_by'], $now, $now,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM surat_masuk');
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT s.id, s.tahun, s.no_agenda, s.tgl_terima, s.no_surat, s.tgl_surat, s.asal, s.perihal, s.sifat,
                s.klasifikasi_id, s.no_srikandi, s.jumlah_lampiran, s.status, s.cancelled_at, s.cancel_reason,
                s.created_by, s.created_at, s.updated_at,
                k.kode AS klasifikasi_kode, k.uraian AS klasifikasi_uraian, u.nama AS created_by_nama
             FROM surat_masuk s
             JOIN klasifikasi_arsip k ON k.id = s.klasifikasi_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * Dugaan surat ganda: nomor surat + asal + tanggal surat sama pada surat yang tidak dibatalkan (docs/06 P1).
     *
     * @return list<array<string,mixed>>
     */
    public function findDuplicates(string $noSurat, string $asal, string $tglSurat, ?int $exceptId = null): array
    {
        return $this->db->fetchAll(
            'SELECT id, tahun, no_agenda, perihal FROM surat_masuk
             WHERE no_surat = ? AND asal = ? AND tgl_surat = ? AND status <> ? AND id <> ?
             ORDER BY tahun DESC, no_agenda DESC LIMIT 5',
            [$noSurat, $asal, $tglSurat, 'dibatalkan', $exceptId ?? 0]
        );
    }

    /**
     * @param array{q?:string,tahun?:string,status?:string,sifat?:string} $filter
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filter, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        $q = $filter['q'] ?? '';
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $clauses = ['s.no_surat LIKE ?', 's.asal LIKE ?', 's.perihal LIKE ?', 's.no_srikandi LIKE ?'];
            $params = [$like, $like, $like, $like];
            // Nomor agenda boleh dicari sebagai "127" atau "0127/2026".
            if (preg_match('/^(\d{1,6})(?:\/(\d{4}))?$/', $q, $m) === 1) {
                if (isset($m[2])) {
                    $clauses[] = '(s.no_agenda = ? AND s.tahun = ?)';
                    $params[] = (int) $m[1];
                    $params[] = (int) $m[2];
                } else {
                    $clauses[] = 's.no_agenda = ?';
                    $params[] = (int) $m[1];
                }
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
        foreach (['tahun' => 's.tahun', 'status' => 's.status', 'sifat' => 's.sifat'] as $key => $column) {
            if (($filter[$key] ?? '') !== '') {
                $where[] = $column . ' = ?';
                $params[] = $key === 'tahun' ? (int) $filter[$key] : $filter[$key];
            }
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM surat_masuk s' . $whereSql, $params);
        $rows = $this->db->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . ' FROM surat_masuk s
             JOIN klasifikasi_arsip k ON k.id = s.klasifikasi_id' . $whereSql
            . ' ORDER BY s.tahun DESC, s.no_agenda DESC LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<int> tahun yang punya surat, terbaru dulu */
    public function years(): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['tahun'],
            $this->db->fetchAll('SELECT DISTINCT tahun FROM surat_masuk ORDER BY tahun DESC')
        );
    }

    /**
     * Nomor agenda dan tahun sengaja tidak ikut diubah (docs/05, docs/20).
     *
     * @param array{tgl_terima:string,no_surat:string,tgl_surat:string,asal:string,perihal:string,sifat:string,
     *   klasifikasi_id:int,no_srikandi:?string,jumlah_lampiran:?string} $data
     */
    public function update(int $id, array $data, ?int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE surat_masuk SET tgl_terima = ?, no_surat = ?, tgl_surat = ?, asal = ?, perihal = ?, sifat = ?,
                klasifikasi_id = ?, no_srikandi = ?, jumlah_lampiran = ?, updated_by = ?, updated_at = ?
             WHERE id = ?',
            [
                $data['tgl_terima'], $data['no_surat'], $data['tgl_surat'], $data['asal'], $data['perihal'],
                $data['sifat'], $data['klasifikasi_id'], $data['no_srikandi'], $data['jumlah_lampiran'],
                $userId, $now, $id,
            ]
        );
    }

    /**
     * Mengunci baris surat sampai transaksi selesai, agar perubahan status (disposisi, selesai, batal)
     * tidak saling menimpa.
     *
     * @return array{id:int,tahun:int,no_agenda:int,tgl_terima:string,status:string}|null
     */
    public function lockForUpdate(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, tahun, no_agenda, tgl_terima, status FROM surat_masuk WHERE id = ? FOR UPDATE',
            [$id]
        );
    }

    public function updateStatus(int $id, string $status, ?int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE surat_masuk SET status = ?, updated_by = ?, updated_at = ? WHERE id = ?',
            [$status, $userId, $now, $id]
        );
    }

    public function cancel(int $id, string $reason, ?int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE surat_masuk SET status = ?, cancelled_at = ?, cancel_reason = ?, updated_by = ?, updated_at = ?
             WHERE id = ?',
            ['dibatalkan', $now, $reason, $userId, $now, $id]
        );
    }
}
