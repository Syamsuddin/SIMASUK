<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Agenda\Repository\SuratMasukRepository;

final class SuratMasukRepositoryTest extends IntegrationTestCase
{
    private SuratMasukRepository $repo;
    private int $klasifikasiId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuratMasukRepository($this->db);
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['800', 'Kepegawaian', $now, $now]
        );
        $this->klasifikasiId = $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $over */
    private function surat(int $tahun, int $no, array $over = []): int
    {
        return $this->repo->insert([
            'tahun' => $tahun,
            'no_agenda' => $no,
            'tgl_terima' => $tahun . '-03-02',
            'no_surat' => 'NS-' . $tahun . '-' . $no,
            'tgl_surat' => $tahun . '-03-01',
            'asal' => 'BKN Kanreg VIII',
            'perihal' => 'Perihal ' . $no,
            'sifat' => 'biasa',
            'klasifikasi_id' => $this->klasifikasiId,
            'no_srikandi' => null,
            'jumlah_lampiran' => null,
            'status' => 'diterima',
            'cancelled_at' => null,
            'cancel_reason' => null,
            'created_by' => null,
            ...$over,
        ], date('Y-m-d H:i:s'));
    }

    /** @param array<string,string> $filter @return list<int> */
    private function nomor(array $filter, int $page = 1): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['no_agenda'],
            $this->repo->paginate($filter, $page, 25)['rows']
        );
    }

    public function test_filter_rahasia_dan_kata_hcdp_hanya_memuat_yang_cocok(): void
    {
        $this->surat(2026, 1, ['sifat' => 'rahasia', 'perihal' => 'Usulan peserta HCDP angkatan II']);
        $this->surat(2026, 2, ['sifat' => 'biasa', 'perihal' => 'Undangan sosialisasi HCDP']);
        $this->surat(2026, 3, ['sifat' => 'rahasia', 'perihal' => 'Hasil asesmen kompetensi']);
        $this->surat(2026, 4, ['sifat' => 'rahasia', 'asal' => 'Panitia HCDP Provinsi']);

        self::assertSame([4, 1], $this->nomor(['sifat' => 'rahasia', 'q' => 'HCDP']));
        self::assertSame([4, 3, 1], $this->nomor(['sifat' => 'rahasia']));
        self::assertSame([4, 2, 1], $this->nomor(['q' => 'hcdp']));
    }

    public function test_paginasi_25_urut_nomor_terbaru(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->surat(2026, $i);
        }
        $first = $this->repo->paginate([], 1, 25);
        self::assertSame(30, $first['total']);
        self::assertCount(25, $first['rows']);
        self::assertSame(30, (int) $first['rows'][0]['no_agenda']);
        self::assertSame([5, 4, 3, 2, 1], $this->nomor([], 2));
    }

    public function test_cari_nomor_agenda_dengan_atau_tanpa_tahun(): void
    {
        $tanpaAngka = ['no_surat' => 'ND/BKPSDM', 'perihal' => 'Undangan rapat'];
        $this->surat(2025, 3, $tanpaAngka);
        $this->surat(2026, 3, $tanpaAngka);
        $this->surat(2026, 31, $tanpaAngka);

        $byFormat = $this->repo->paginate(['q' => '0003/2026'], 1, 25)['rows'];
        self::assertCount(1, $byFormat);
        self::assertSame(2026, (int) $byFormat[0]['tahun']);
        self::assertSame([3, 3], $this->nomor(['q' => '3']));
    }

    public function test_filter_tahun_dan_status(): void
    {
        $this->surat(2025, 7);
        $this->surat(2026, 1, ['status' => 'dibatalkan']);
        $this->surat(2026, 2);

        self::assertSame([2, 1], $this->nomor(['tahun' => '2026']));
        self::assertSame([1], $this->nomor(['status' => 'dibatalkan']));
        self::assertSame([2026, 2025], $this->repo->years());
    }

    public function test_karakter_wildcard_dicari_harfiah(): void
    {
        $this->surat(2026, 1, ['perihal' => 'Kenaikan 100% tunjangan']);
        $this->surat(2026, 2, ['perihal' => 'Lain-lain']);
        self::assertSame([1], $this->nomor(['q' => '100%']));
        self::assertSame([], $this->nomor(['q' => '_']));
    }

    public function test_dugaan_ganda_mengabaikan_surat_dibatalkan_dan_dirinya_sendiri(): void
    {
        $sama = ['no_surat' => '800/12/BKN', 'asal' => 'BKN', 'tgl_surat' => '2026-03-01'];
        $a = $this->surat(2026, 1, $sama);
        $this->surat(2026, 2, [...$sama, 'status' => 'dibatalkan']);
        $this->surat(2026, 3, [...$sama, 'tgl_surat' => '2026-02-28']);

        $dupes = $this->repo->findDuplicates('800/12/BKN', 'BKN', '2026-03-01');
        self::assertSame([$a], array_map(static fn (array $r): int => (int) $r['id'], $dupes));
        self::assertSame([], $this->repo->findDuplicates('800/12/BKN', 'BKN', '2026-03-01', $a));
    }
}
