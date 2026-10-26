<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Disposisi\Repository\DisposisiRepository;

final class DisposisiRepositoryTest extends IntegrationTestCase
{
    private DisposisiRepository $repo;
    private SuratMasukRepository $suratRepo;
    private int $klasifikasiId;
    private int $unitId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DisposisiRepository($this->db);
        $this->suratRepo = new SuratMasukRepository($this->db);
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['800', 'Kepegawaian', $now, $now]
        );
        $this->klasifikasiId = $this->db->lastInsertId();
        $this->db->execute(
            'INSERT INTO unit_kerja (parent_id, nama, jabatan_pimpinan, urutan, is_active, created_at, updated_at)
             VALUES (NULL, ?, ?, 0, 1, ?, ?)',
            ['Bidang PSDM', 'Kepala Bidang PSDM', $now, $now]
        );
        $this->unitId = $this->db->lastInsertId();
        $this->userId = $this->makeUser('operator1');
    }

    private function surat(int $no): int
    {
        return $this->suratRepo->insert([
            'tahun' => 2026, 'no_agenda' => $no, 'tgl_terima' => '2026-03-02', 'no_surat' => 'NS-' . $no,
            'tgl_surat' => '2026-03-01', 'asal' => 'BKN', 'perihal' => 'Perihal ' . $no, 'sifat' => 'biasa',
            'klasifikasi_id' => $this->klasifikasiId, 'no_srikandi' => null, 'jumlah_lampiran' => null,
            'status' => 'didisposisi', 'cancelled_at' => null, 'cancel_reason' => null, 'created_by' => null,
        ], date('Y-m-d H:i:s'));
    }

    private function dispo(int $suratId, ?int $parentId = null, string $status = 'dikirim', ?string $catatanSelesai = null): int
    {
        return $this->repo->insert([
            'surat_id' => $suratId,
            'parent_id' => $parentId,
            'unit_tujuan_id' => $this->unitId,
            'tgl_disposisi' => '2026-03-03',
            'instruksi' => 'Tindak lanjuti',
            'catatan' => null,
            'tenggat' => '2026-03-10',
            'status' => $status,
            'selesai_at' => $status === 'selesai' ? '2026-03-05 09:00:00' : null,
            'catatan_selesai' => $catatanSelesai,
            'created_by' => $this->userId,
        ], date('Y-m-d H:i:s'));
    }

    public function test_list_by_surat_hanya_surat_itu_urut_pencatatan_dengan_nama_unit_dan_pencatat(): void
    {
        $a = $this->surat(1);
        $b = $this->surat(2);
        $induk = $this->dispo($a);
        $this->dispo($b);
        $lanjutan = $this->dispo($a, $induk);

        $rows = $this->repo->listBySurat($a);

        self::assertSame([$induk, $lanjutan], array_map(static fn (array $r): int => (int) $r['id'], $rows));
        self::assertNull($rows[0]['parent_id']);
        self::assertSame($induk, (int) $rows[1]['parent_id']);
        self::assertSame('Bidang PSDM', $rows[0]['unit_nama']);
        self::assertSame('Kepala Bidang PSDM', $rows[0]['unit_jabatan']);
        self::assertSame('Operator1', $rows[0]['created_by_nama']);
        self::assertSame([], $this->repo->listBySurat(999999));
    }

    public function test_find_by_id_membawa_surat_dan_unit(): void
    {
        $suratId = $this->surat(1);
        $id = $this->dispo($suratId);

        $row = $this->repo->findById($id);

        self::assertNotNull($row);
        self::assertSame($suratId, (int) $row['surat_id']);
        self::assertSame('Bidang PSDM', $row['unit_nama']);
        self::assertSame('dikirim', $row['status']);
        self::assertNull($this->repo->findById(999999));
    }

    public function test_mark_done_hanya_mengubah_disposisi_yang_masih_dikirim(): void
    {
        $id = $this->dispo($this->surat(1));

        self::assertSame(1, $this->repo->markDone($id, 'Laporan diterima', '2026-03-06 10:00:00'));
        self::assertSame(0, $this->repo->markDone($id, 'Ditimpa', '2026-03-07 10:00:00'));

        $row = $this->repo->findById($id);
        self::assertNotNull($row);
        self::assertSame('selesai', $row['status']);
        self::assertSame('Laporan diterima', $row['catatan_selesai']);
        self::assertSame('2026-03-06 10:00:00', $row['selesai_at']);
    }

    public function test_close_open_by_surat_tidak_menyentuh_surat_lain_dan_yang_sudah_selesai(): void
    {
        $a = $this->surat(1);
        $b = $this->surat(2);
        $satu = $this->dispo($a);
        $dua = $this->dispo($a, $satu);
        $sudah = $this->dispo($a, null, 'selesai', 'Sudah dijawab');
        $lain = $this->dispo($b);

        self::assertSame([$satu, $dua], $this->repo->openIdsBySurat($a));
        self::assertSame(2, $this->repo->closeOpenBySurat($a, 'Ditutup bersama surat', '2026-03-08 08:00:00'));

        self::assertSame([], $this->repo->openIdsBySurat($a));
        self::assertSame('Ditutup bersama surat', $this->repo->findById($dua)['catatan_selesai'] ?? null);
        self::assertSame('Sudah dijawab', $this->repo->findById($sudah)['catatan_selesai'] ?? null);
        self::assertSame('2026-03-05 09:00:00', $this->repo->findById($sudah)['selesai_at'] ?? null);
        self::assertSame('dikirim', $this->repo->findById($lain)['status'] ?? null);
    }

    public function test_lock_dan_update_status_surat(): void
    {
        $suratId = $this->surat(1);

        $this->db->transaction(function () use ($suratId): void {
            $locked = $this->suratRepo->lockForUpdate($suratId);
            self::assertNotNull($locked);
            self::assertSame('didisposisi', $locked['status']);
            self::assertSame('2026-03-02', $locked['tgl_terima']);
            $this->suratRepo->updateStatus($suratId, 'selesai', $this->userId, '2026-03-09 08:00:00');
        });

        $surat = $this->suratRepo->findById($suratId);
        self::assertNotNull($surat);
        self::assertSame('selesai', $surat['status']);
        self::assertSame('2026-03-09 08:00:00', $surat['updated_at']);
        self::assertNull($this->suratRepo->lockForUpdate(999999));
    }
}
