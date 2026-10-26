<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Core\View;
use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Dasbor\Repository\DasborRepository;
use Modules\Dasbor\Service\DasborService;
use Modules\Disposisi\Repository\DisposisiRepository;

/** Ringkasan dasbor: surat bulan ini, belum didisposisi, lewat tenggat (docs/23 F-DASBOR). */
final class DasborServiceTest extends IntegrationTestCase
{
    private const HARI = '2026-10-26';
    private const KEMARIN = '2026-10-25';

    private DasborService $service;
    private SuratMasukRepository $suratRepo;
    private DisposisiRepository $dispoRepo;
    private int $klasifikasiId;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
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

        $this->suratRepo = new SuratMasukRepository($this->db);
        $this->dispoRepo = new DisposisiRepository($this->db);
        $this->service = new DasborService(new DasborRepository($this->db));
    }

    protected function tearDown(): void
    {
        View::reset();
        parent::tearDown();
    }

    private function surat(int $no, string $status = 'diterima', string $tglTerima = '2026-10-01', string $sifat = 'biasa'): int
    {
        return $this->suratRepo->insert([
            'tahun' => (int) substr($tglTerima, 0, 4),
            'no_agenda' => $no,
            'tgl_terima' => $tglTerima,
            'no_surat' => 'NS-' . $no,
            'tgl_surat' => $tglTerima,
            'asal' => 'BKN Kanreg VIII',
            'perihal' => 'Perihal ' . $no,
            'sifat' => $sifat,
            'klasifikasi_id' => $this->klasifikasiId,
            'no_srikandi' => null,
            'jumlah_lampiran' => null,
            'status' => $status,
            'cancelled_at' => $status === 'dibatalkan' ? date('Y-m-d H:i:s') : null,
            'cancel_reason' => $status === 'dibatalkan' ? 'Salah catat' : null,
            'created_by' => null,
        ], date('Y-m-d H:i:s'));
    }

    private function disposisi(int $suratId, ?string $tenggat, string $status = 'dikirim'): int
    {
        return $this->dispoRepo->insert([
            'surat_id' => $suratId,
            'parent_id' => null,
            'unit_tujuan_id' => $this->unitId,
            'tgl_disposisi' => '2026-10-02',
            'instruksi' => 'Tindak lanjuti',
            'catatan' => null,
            'tenggat' => $tenggat,
            'status' => $status,
            'selesai_at' => $status === 'selesai' ? date('Y-m-d H:i:s') : null,
            'catatan_selesai' => null,
            'created_by' => null,
        ], date('Y-m-d H:i:s'));
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    public function test_tiga_disposisi_dikirim_tenggat_kemarin_terhitung_lewat_tenggat(): void
    {
        $surat = $this->surat(1, 'didisposisi');
        $a = $this->disposisi($surat, self::KEMARIN);
        $b = $this->disposisi($surat, self::KEMARIN);
        $c = $this->disposisi($this->surat(2, 'didisposisi'), self::KEMARIN);

        $hasil = $this->service->ringkasan(self::HARI);

        self::assertSame(3, $hasil['lewat']['total']);
        self::assertSame([$a, $b, $c], self::ids($hasil['lewat']['rows']));
        self::assertSame('Bidang PSDM', $hasil['lewat']['rows'][0]['unit_nama']);
        self::assertSame(1, DasborService::hariLewat(self::KEMARIN, self::HARI));
    }

    public function test_tenggat_hari_ini_tanpa_tenggat_selesai_dan_surat_batal_tidak_lewat_tenggat(): void
    {
        $surat = $this->surat(1, 'didisposisi');
        $this->disposisi($surat, self::HARI);
        $this->disposisi($surat, null);
        $this->disposisi($surat, self::KEMARIN, 'selesai');
        $this->disposisi($this->surat(2, 'dibatalkan'), self::KEMARIN);
        $lewat = $this->disposisi($surat, '2026-10-20');

        $hasil = $this->service->ringkasan(self::HARI);

        self::assertSame(1, $hasil['lewat']['total']);
        self::assertSame([$lewat], self::ids($hasil['lewat']['rows']));
        self::assertSame(6, DasborService::hariLewat('2026-10-20', self::HARI));
    }

    public function test_surat_diterima_tanpa_disposisi_muncul_di_belum_didisposisi(): void
    {
        $diterima = $this->surat(1, 'diterima', '2026-10-05');
        $lamaDiterima = $this->surat(2, 'diterima', '2026-09-30');
        $this->disposisi($this->surat(3, 'didisposisi'), null);
        $this->surat(4, 'selesai');
        $this->surat(5, 'dibatalkan');

        $hasil = $this->service->ringkasan(self::HARI);

        self::assertSame(2, $hasil['belum']['total']);
        self::assertSame([$lamaDiterima, $diterima], self::ids($hasil['belum']['rows']), 'Yang paling lama menunggu lebih dulu');
    }

    public function test_surat_bulan_ini_menurut_tgl_terima_tanpa_yang_dibatalkan(): void
    {
        $this->surat(1, 'diterima', '2026-10-01');
        $this->surat(2, 'selesai', '2026-10-26');
        $this->surat(3, 'didisposisi', '2026-10-31');
        $this->surat(4, 'dibatalkan', '2026-10-10');
        $this->surat(5, 'diterima', '2026-09-30');
        $this->surat(6, 'diterima', '2026-11-01');

        self::assertSame(3, $this->service->ringkasan(self::HARI)['bulan_ini']);
        self::assertSame(1, $this->service->ringkasan('2026-09-15')['bulan_ini']);
    }

    public function test_tabel_dibatasi_25_baris_tetapi_total_tetap_penuh(): void
    {
        for ($i = 1; $i <= 27; $i++) {
            $this->surat($i, 'diterima', '2026-10-01');
        }

        $hasil = $this->service->ringkasan(self::HARI);

        self::assertSame(27, $hasil['belum']['total']);
        self::assertCount(DasborService::BATAS_TABEL, $hasil['belum']['rows']);
        self::assertSame(1, (int) $hasil['belum']['rows'][0]['no_agenda']);
    }

    public function test_halaman_dasbor_menampilkan_kartu_tabel_dan_state_kosong(): void
    {
        $store = [];
        View::share('session', new Session($store));
        $user = ['nama' => 'Siti', 'role' => 'operator'];

        $kosong = View::render('Dasbor::index', ['user' => $user, 'ringkasan' => $this->service->ringkasan(self::HARI)], null);
        self::assertStringContainsString('Tidak ada surat yang menunggu disposisi.', $kosong);
        self::assertStringContainsString('Tidak ada disposisi yang lewat tenggat.', $kosong);

        $surat = $this->surat(7, 'didisposisi', '2026-10-01', 'sangat_segera');
        $this->disposisi($surat, self::KEMARIN);
        $belum = $this->surat(8, 'diterima', '2026-10-02');

        $isi = View::render('Dasbor::index', ['user' => $user, 'ringkasan' => $this->service->ringkasan(self::HARI)], null);
        self::assertStringContainsString('Surat masuk bulan ini', $isi);
        self::assertStringContainsString('0007/2026', $isi);
        self::assertStringContainsString('Lewat 1 hari', $isi);
        self::assertStringContainsString('href="/surat-masuk/' . $belum . '/disposisi/baru"', $isi);
        self::assertStringContainsString('href="/surat-masuk?status=diterima"', $isi);
        self::assertStringNotContainsString('Tidak ada disposisi yang lewat tenggat.', $isi);
    }
}
