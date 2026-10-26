<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DomainRuleException;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use Modules\Agenda\Repository\AgendaCounterRepository;
use Modules\Agenda\Repository\BerkasSuratRepository;
use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Agenda\Service\AgendaNumberService;
use Modules\Agenda\Service\SuratMasukService;
use Modules\Disposisi\Controller\DisposisiController;
use Modules\Disposisi\Repository\DisposisiRepository;
use Modules\Disposisi\Service\DisposisiService;
use Modules\Master\Repository\KlasifikasiRepository;
use Modules\Master\Repository\UnitKerjaRepository;
use Modules\Master\Service\KlasifikasiService;
use Modules\Master\Service\UnitKerjaService;

/** Disposisi berjenjang, penandaan selesai, penutupan surat, dan lembar cetak (docs/23 F-DISPO & F-LEMBAR, docs/06 P2). */
final class DisposisiServiceTest extends IntegrationTestCase
{
    private DisposisiService $service;
    private SuratMasukRepository $suratRepo;
    private int $klasifikasiId;
    private int $operatorId;
    private int $psdm;
    private int $subbidang;
    private int $sekretariat;
    private int $nonaktif;

    protected function setUp(): void
    {
        parent::setUp();
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['800', 'Kepegawaian', $now, $now]
        );
        $this->klasifikasiId = $this->db->lastInsertId();
        $this->operatorId = $this->makeUser('operator1');
        $this->audit->setContext($this->operatorId, '127.0.0.1');

        $this->psdm = $this->unit('Bidang PSDM', null, 'Kepala Bidang PSDM');
        $this->subbidang = $this->unit('Subbidang Pengembangan Kompetensi', $this->psdm);
        $this->sekretariat = $this->unit('Sekretariat', null, 'Sekretaris');
        $this->nonaktif = $this->unit('Bidang Lama', null, null, false);

        $this->suratRepo = new SuratMasukRepository($this->db);
        $this->service = new DisposisiService(
            new DisposisiRepository($this->db),
            $this->suratRepo,
            new UnitKerjaService(new UnitKerjaRepository($this->db), $this->audit, $this->db),
            $this->audit,
            $this->db
        );
    }

    protected function tearDown(): void
    {
        View::reset();
        parent::tearDown();
    }

    private function unit(string $nama, ?int $parentId = null, ?string $jabatan = null, bool $active = true): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO unit_kerja (parent_id, nama, jabatan_pimpinan, urutan, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?, ?)',
            [$parentId, $nama, $jabatan, $active ? 1 : 0, $now, $now]
        );
        return $this->db->lastInsertId();
    }

    private function surat(int $no = 1, string $status = 'diterima'): int
    {
        return $this->suratRepo->insert([
            'tahun' => 2026,
            'no_agenda' => $no,
            'tgl_terima' => '2026-03-02',
            'no_surat' => 'NS-' . $no,
            'tgl_surat' => '2026-02-27',
            'asal' => 'BKN Kanreg VIII Banjarmasin',
            'perihal' => 'Undangan sosialisasi HCDP',
            'sifat' => 'biasa',
            'klasifikasi_id' => $this->klasifikasiId,
            'no_srikandi' => null,
            'jumlah_lampiran' => null,
            'status' => $status,
            'cancelled_at' => $status === 'dibatalkan' ? date('Y-m-d H:i:s') : null,
            'cancel_reason' => $status === 'dibatalkan' ? 'Salah catat' : null,
            'created_by' => $this->operatorId,
        ], date('Y-m-d H:i:s'));
    }

    /**
     * @param list<int> $units
     * @param array<string,mixed> $over
     * @return array<string,mixed>
     */
    private function input(array $units, array $over = []): array
    {
        $input = [
            'tgl_disposisi' => '2026-03-03',
            'instruksi' => 'Pelajari dan tindak lanjuti',
            'catatan' => '',
            'tenggat' => '2026-03-10',
            ...$over,
        ];
        foreach ($units as $unitId) {
            $input[DisposisiService::PREFIX_TUJUAN . $unitId] = '1';
        }
        return $input;
    }

    /** @return array<string,string> */
    private function errorsOf(callable $fn): array
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        self::fail('Seharusnya ValidationException');
    }

    private function statusSurat(int $id): string
    {
        return (string) $this->db->fetchValue('SELECT status FROM surat_masuk WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    private function rows(int $suratId): array
    {
        return $this->db->fetchAll('SELECT * FROM disposisi WHERE surat_id = ? ORDER BY id', [$suratId]);
    }

    /** @param array<string,mixed> $data */
    private function render(string $view, array $data, array $old = []): string
    {
        $store = [];
        View::share('session', new Session($store));
        View::share('errors', []);
        View::share('old', $old);
        return View::render($view, $data, null);
    }

    /** @return array<string,mixed> */
    private function detailData(int $suratId): array
    {
        $surat = $this->suratRepo->findById($suratId);
        self::assertNotNull($surat);
        return ['surat' => $surat, 'berkas' => [], 'disposisi' => $this->service->tree($suratId), 'isAdmin' => false];
    }

    public function test_disposisi_ke_dua_unit_membuat_dua_baris_dikirim_dan_surat_didisposisi(): void
    {
        $suratId = $this->surat();

        $ids = $this->service->create($suratId, $this->input([$this->psdm, $this->sekretariat]), $this->operatorId);

        $rows = $this->rows($suratId);
        self::assertCount(2, $ids);
        self::assertSame($ids, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        self::assertSame([$this->psdm, $this->sekretariat], array_map(static fn (array $r): int => (int) $r['unit_tujuan_id'], $rows));
        foreach ($rows as $row) {
            self::assertSame('dikirim', $row['status']);
            self::assertNull($row['parent_id']);
            self::assertSame('Pelajari dan tindak lanjuti', $row['instruksi']);
            self::assertSame('2026-03-10', $row['tenggat']);
            self::assertNull($row['catatan']);
            self::assertSame($this->operatorId, (int) $row['created_by']);
        }
        self::assertSame('didisposisi', $this->statusSurat($suratId));
        self::assertCount(2, $this->auditRows('disposisi.create'));
        $statusAudit = $this->auditRows('surat.update');
        self::assertCount(1, $statusAudit);
        self::assertSame(['status' => 'didisposisi'], json_decode((string) $statusAudit[0]['data_json'], true)['sesudah']);
    }

    public function test_disposisi_lanjutan_mengisi_parent_id_dan_pohon_berindentasi(): void
    {
        $suratId = $this->surat();
        [$induk] = $this->service->create($suratId, $this->input([$this->psdm]), $this->operatorId);

        [$lanjutan] = $this->service->create(
            $suratId,
            $this->input([$this->subbidang], ['parent_id' => (string) $induk, 'instruksi' => 'Siapkan bahan HCDP']),
            $this->operatorId
        );

        self::assertSame($induk, (int) $this->rows($suratId)[1]['parent_id']);
        self::assertCount(1, $this->auditRows('surat.update'), 'Surat yang sudah Didisposisi tidak berubah status lagi');

        $tree = $this->service->tree($suratId);
        self::assertCount(1, $tree);
        self::assertSame($induk, (int) $tree[0]['id']);
        self::assertSame('Bidang PSDM', $tree[0]['unit_nama']);
        self::assertCount(1, $tree[0]['children']);
        self::assertSame($lanjutan, (int) $tree[0]['children'][0]['id']);
        self::assertSame([], $tree[0]['children'][0]['children']);

        $html = $this->render('Agenda::detail', $this->detailData($suratId));
        $posInduk = strpos($html, 'Bidang PSDM');
        $posIndent = strpos($html, 'ml-6 border-l border-line');
        $posAnak = strpos($html, 'Subbidang Pengembangan Kompetensi');
        self::assertNotFalse($posInduk);
        self::assertNotFalse($posIndent);
        self::assertNotFalse($posAnak);
        self::assertTrue($posInduk < $posIndent && $posIndent < $posAnak, 'Lanjutan dirender di dalam blok berindentasi setelah induknya');
        self::assertStringContainsString('/surat-masuk/' . $suratId . '/disposisi/baru?induk=' . $lanjutan, $html);
    }

    public function test_parent_id_milik_surat_lain_ditolak_422(): void
    {
        $suratA = $this->surat(1);
        $suratB = $this->surat(2);
        [$dispoB] = $this->service->create($suratB, $this->input([$this->psdm]), $this->operatorId);

        foreach ([(string) $dispoB, '999999'] as $parentId) {
            $errors = $this->errorsOf(fn () => $this->service->create(
                $suratA,
                $this->input([$this->subbidang], ['parent_id' => $parentId]),
                $this->operatorId
            ));
            self::assertSame(['parent_id' => DisposisiService::PESAN_INDUK], $errors);
        }
        self::assertSame([], $this->rows($suratA));
        self::assertSame('diterima', $this->statusSurat($suratA));

        $this->expectException(HttpException::class);
        $this->service->formContext($suratA, $dispoB);
    }

    public function test_tenggat_sebelum_tgl_terima_ditolak_di_field_tenggat(): void
    {
        $suratId = $this->surat();

        $errors = $this->errorsOf(fn () => $this->service->create(
            $suratId,
            $this->input([$this->psdm], ['tenggat' => '2026-03-01']),
            $this->operatorId
        ));

        self::assertSame(['tenggat'], array_keys($errors));
        self::assertStringContainsString('Tenggat tidak boleh sebelum tanggal terima surat', $errors['tenggat']);
        self::assertSame([], $this->rows($suratId));

        $this->service->create($suratId, $this->input([$this->psdm], ['tenggat' => '2026-03-02']), $this->operatorId);
        $this->service->create($suratId, $this->input([$this->sekretariat], ['tenggat' => '']), $this->operatorId);
        $rows = $this->rows($suratId);
        self::assertSame('2026-03-02', $rows[0]['tenggat']);
        self::assertNull($rows[1]['tenggat']);
    }

    public function test_surat_selesai_atau_dibatalkan_tanpa_tombol_disposisi_dan_post_ditolak(): void
    {
        foreach (['selesai' => 1, 'dibatalkan' => 2] as $status => $no) {
            $suratId = $this->surat($no, $status);

            try {
                $this->service->create($suratId, $this->input([$this->psdm]), $this->operatorId);
                self::fail('Disposisi pada surat ' . $status . ' seharusnya ditolak');
            } catch (DomainRuleException $e) {
                self::assertSame(DisposisiService::PESAN_SURAT_TERTUTUP, $e->getMessage());
            }
            try {
                $this->service->create($suratId, [], $this->operatorId);
                self::fail('Surat ' . $status . ' seharusnya ditolak sebelum isian divalidasi');
            } catch (DomainRuleException $e) {
                self::assertSame(DisposisiService::PESAN_SURAT_TERTUTUP, $e->getMessage());
            }
            try {
                $this->service->formContext($suratId, null);
                self::fail('Formulir disposisi surat ' . $status . ' seharusnya ditolak');
            } catch (DomainRuleException $e) {
                self::assertSame(DisposisiService::PESAN_SURAT_TERTUTUP, $e->getMessage());
            }
            self::assertSame([], $this->rows($suratId));
            self::assertSame($status, $this->statusSurat($suratId));

            $html = $this->render('Agenda::detail', $this->detailData($suratId));
            self::assertStringNotContainsString('Catat Disposisi', $html);
            self::assertStringNotContainsString('/disposisi/baru', $html);
            self::assertStringNotContainsString('Tandai Selesai', $html);
            self::assertStringContainsString('tanpa disposisi', $html);
        }
    }

    public function test_disposisi_lama_pada_surat_selesai_tak_bisa_ditandai_atau_dilanjutkan(): void
    {
        $suratId = $this->surat();
        [$dispo] = $this->service->create($suratId, $this->input([$this->psdm]), $this->operatorId);
        $this->suratRepo->updateStatus($suratId, 'dibatalkan', null, date('Y-m-d H:i:s'));

        try {
            $this->service->markDone($dispo, []);
            self::fail('Tandai selesai pada surat dibatalkan seharusnya ditolak');
        } catch (DomainRuleException $e) {
            self::assertSame(DisposisiService::PESAN_SURAT_TERTUTUP, $e->getMessage());
        }
        self::assertSame('dikirim', $this->rows($suratId)[0]['status']);

        $html = $this->render('Agenda::detail', $this->detailData($suratId));
        self::assertStringContainsString('Bidang PSDM', $html);
        self::assertStringNotContainsString('Disposisi Lanjutan', $html);
        self::assertStringNotContainsString('/disposisi/' . $dispo . '/selesai', $html);
    }

    public function test_surat_ditandai_selesai_menutup_disposisi_dikirim_dengan_catatan_otomatis(): void
    {
        $suratId = $this->surat();
        [$satu, $dua] = $this->service->create($suratId, $this->input([$this->psdm, $this->sekretariat]), $this->operatorId);
        [$tiga] = $this->service->create($suratId, $this->input([$this->subbidang], ['parent_id' => (string) $satu]), $this->operatorId);
        $this->service->markDone($dua, ['catatan_selesai' => 'Sudah dijawab lewat SRIKANDI']);

        $ditutup = $this->service->closeSurat($suratId, $this->operatorId);

        self::assertSame(2, $ditutup);
        self::assertSame('selesai', $this->statusSurat($suratId));
        $byId = array_column($this->rows($suratId), null, 'id');
        foreach ([$satu, $tiga] as $id) {
            self::assertSame('selesai', $byId[$id]['status']);
            self::assertSame(DisposisiService::CATATAN_TUTUP_SURAT, $byId[$id]['catatan_selesai']);
            self::assertSame('Ditutup bersama surat', $byId[$id]['catatan_selesai']);
            self::assertNotNull($byId[$id]['selesai_at']);
        }
        self::assertSame('Sudah dijawab lewat SRIKANDI', $byId[$dua]['catatan_selesai']);

        $audit = $this->auditRows('surat.selesai');
        self::assertCount(1, $audit);
        $data = json_decode((string) $audit[0]['data_json'], true);
        self::assertSame(['status' => 'didisposisi'], $data['sebelum']);
        self::assertSame([$satu, $tiga], $data['sesudah']['disposisi_ditutup']);

        try {
            $this->service->closeSurat($suratId, $this->operatorId);
            self::fail('Surat yang sudah selesai seharusnya tidak bisa ditandai lagi');
        } catch (DomainRuleException $e) {
            self::assertSame(DisposisiService::PESAN_SURAT_SUDAH_TERTUTUP, $e->getMessage());
        }
    }

    public function test_surat_diterima_tanpa_disposisi_boleh_ditandai_selesai_tetapi_surat_batal_tidak(): void
    {
        $diterima = $this->surat(1);
        self::assertSame(0, $this->service->closeSurat($diterima, $this->operatorId));
        self::assertSame('selesai', $this->statusSurat($diterima));

        $batal = $this->surat(2, 'dibatalkan');
        $this->expectException(DomainRuleException::class);
        try {
            $this->service->closeSurat($batal, $this->operatorId);
        } finally {
            self::assertSame('dibatalkan', $this->statusSurat($batal));
        }
    }

    public function test_tandai_disposisi_selesai_mencatat_catatan_dan_tidak_bisa_diulang(): void
    {
        $suratId = $this->surat();
        [$dispo] = $this->service->create($suratId, $this->input([$this->psdm]), $this->operatorId);

        self::assertSame($suratId, $this->service->markDone($dispo, ['catatan_selesai' => 'Laporan diterima 5 Maret']));

        $row = $this->rows($suratId)[0];
        self::assertSame('selesai', $row['status']);
        self::assertSame('Laporan diterima 5 Maret', $row['catatan_selesai']);
        self::assertNotNull($row['selesai_at']);
        self::assertSame('didisposisi', $this->statusSurat($suratId), 'Surat tetap berjalan sampai ditandai selesai sendiri');
        self::assertCount(1, $this->auditRows('disposisi.selesai'));

        try {
            $this->service->markDone($dispo, []);
            self::fail('Disposisi yang sudah selesai seharusnya ditolak');
        } catch (DomainRuleException $e) {
            self::assertSame(DisposisiService::PESAN_SUDAH_SELESAI, $e->getMessage());
        }
        self::assertSame('Laporan diterima 5 Maret', $this->rows($suratId)[0]['catatan_selesai']);

        $errors = $this->errorsOf(fn () => $this->service->markDone($dispo, ['catatan_selesai' => str_repeat('a', 501)]));
        self::assertSame(['catatan_selesai' => str_replace(':n', '500', Validator::MSG_MAX)], $errors);
    }

    public function test_unit_tujuan_wajib_dan_hanya_unit_aktif(): void
    {
        $suratId = $this->surat();

        self::assertSame(
            ['tujuan' => DisposisiService::PESAN_TUJUAN_WAJIB],
            $this->errorsOf(fn () => $this->service->create($suratId, $this->input([]), $this->operatorId))
        );
        self::assertSame(
            ['tujuan' => DisposisiService::PESAN_TUJUAN_WAJIB],
            $this->errorsOf(fn () => $this->service->create($suratId, [...$this->input([]), DisposisiService::PREFIX_TUJUAN . $this->psdm => '0'], $this->operatorId))
        );
        foreach ([(string) $this->nonaktif, '999999', 'abc'] as $unit) {
            $errors = $this->errorsOf(fn () => $this->service->create(
                $suratId,
                [...$this->input([$this->psdm]), DisposisiService::PREFIX_TUJUAN . $unit => '1'],
                $this->operatorId
            ));
            self::assertSame(['tujuan' => Validator::MSG_IN], $errors);
        }
        self::assertSame([], $this->rows($suratId));
        self::assertSame('diterima', $this->statusSurat($suratId));
    }

    public function test_isian_wajib_divalidasi_sekaligus(): void
    {
        $suratId = $this->surat();

        $errors = $this->errorsOf(fn () => $this->service->create(
            $suratId,
            ['tgl_disposisi' => '2026-02-30', 'instruksi' => '', 'tenggat' => 'besok'],
            $this->operatorId
        ));

        self::assertSame(Validator::MSG_DATE, $errors['tgl_disposisi']);
        self::assertSame(Validator::MSG_REQUIRED, $errors['instruksi']);
        self::assertSame(Validator::MSG_DATE, $errors['tenggat']);
        self::assertSame(DisposisiService::PESAN_TUJUAN_WAJIB, $errors['tujuan']);
    }

    public function test_surat_atau_disposisi_tidak_ada_menghasilkan_404(): void
    {
        $calls = [
            fn () => $this->service->create(999999, $this->input([$this->psdm]), $this->operatorId),
            fn () => $this->service->formContext(999999, null),
            fn () => $this->service->markDone(999999, []),
            fn () => $this->service->closeSurat(999999, $this->operatorId),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Seharusnya HttpException 404');
            } catch (HttpException $e) {
                self::assertSame(404, $e->status());
            }
        }
    }

    public function test_detail_surat_terbuka_menampilkan_aksi_dan_jumlah_disposisi_yang_ikut_ditutup(): void
    {
        $suratId = $this->surat();
        $kosong = $this->render('Agenda::detail', $this->detailData($suratId));
        self::assertStringContainsString('Belum ada disposisi untuk surat ini.', $kosong);
        self::assertStringContainsString('href="/surat-masuk/' . $suratId . '/disposisi/baru"', $kosong);

        [$satu, $dua] = $this->service->create($suratId, $this->input([$this->psdm, $this->sekretariat]), $this->operatorId);
        $this->service->markDone($dua, []);
        $html = $this->render('Agenda::detail', $this->detailData($suratId));

        self::assertStringContainsString('action="/surat-masuk/' . $suratId . '/selesai"', $html);
        self::assertStringContainsString('1 disposisi yang masih Dikirim ikut ditutup', $html);
        self::assertStringContainsString('action="/disposisi/' . $satu . '/selesai"', $html);
        self::assertStringNotContainsString('action="/disposisi/' . $dua . '/selesai"', $html);
        self::assertStringContainsString('name="catatan_selesai"', $html);
        self::assertStringContainsString('Disposisi Lanjutan', $html);
    }

    public function test_formulir_menampilkan_unit_aktif_berjenjang_dan_mempertahankan_pilihan(): void
    {
        $suratId = $this->surat();
        $context = $this->service->formContext($suratId, null);
        self::assertSame(
            [$this->psdm, $this->subbidang, $this->sekretariat],
            array_map(static fn (array $u): int => (int) $u['id'], $context['units'])
        );

        $html = $this->render(
            'Disposisi::form',
            ['judul' => 'Catat Disposisi', ...$context, 'isAdmin' => false],
            [DisposisiService::PREFIX_TUJUAN . $this->sekretariat => '1']
        );

        self::assertStringContainsString('action="/surat-masuk/' . $suratId . '/disposisi"', $html);
        self::assertStringNotContainsString('Bidang Lama', $html);
        self::assertStringNotContainsString('name="parent_id"', $html);
        self::assertMatchesRegularExpression('/name="tujuan_' . $this->sekretariat . '"[^>]*checked/', $html);
        self::assertDoesNotMatchRegularExpression('/name="tujuan_' . $this->psdm . '"[^>]*checked/', $html);

        [$induk] = $this->service->create($suratId, $this->input([$this->psdm]), $this->operatorId);
        $lanjutan = $this->render('Disposisi::form', ['judul' => 'Disposisi Lanjutan', ...$this->service->formContext($suratId, $induk), 'isAdmin' => false]);
        self::assertStringContainsString('name="parent_id" value="' . $induk . '"', $lanjutan);
        self::assertStringContainsString('Lanjutan dari disposisi ke Bidang PSDM', $lanjutan);
    }

    public function test_formulir_tanpa_unit_aktif_menampilkan_state_kosong(): void
    {
        $this->db->execute('UPDATE unit_kerja SET is_active = 0');
        $suratId = $this->surat();
        $context = $this->service->formContext($suratId, null);

        $operator = $this->render('Disposisi::form', ['judul' => 'Catat Disposisi', ...$context, 'isAdmin' => false]);
        $admin = $this->render('Disposisi::form', ['judul' => 'Catat Disposisi', ...$context, 'isAdmin' => true]);

        self::assertStringContainsString('Belum ada unit tujuan aktif. Hubungi Admin', $operator);
        self::assertStringNotContainsString('<form method="post" action="/surat-masuk/' . $suratId . '/disposisi"', $operator);
        self::assertStringContainsString('href="/master/unit/baru"', $admin);
    }

    /** @param array<string,mixed> $context */
    private function lembar(array $context): string
    {
        return $this->render('Disposisi::lembar', [
            ...$context,
            'kop' => ['PEMERINTAH KABUPATEN UJI', 'BADAN UJI'],
            'dicetak' => '3 Mar 2026 09.00 oleh Operator Satu',
        ]);
    }

    /** @param array<string,mixed> $store */
    private function controller(array &$store): DisposisiController
    {
        $surat = new SuratMasukService(
            $this->suratRepo,
            new BerkasSuratRepository($this->db),
            new AgendaNumberService(new AgendaCounterRepository($this->db), $this->db),
            new KlasifikasiService(new KlasifikasiRepository($this->db), $this->audit, $this->db),
            new FileStore(sys_get_temp_dir(), 10, false),
            $this->audit,
            $this->db
        );
        return new DisposisiController($this->service, $surat, new Session($store));
    }

    private function bukaLembar(int $suratId, array &$store): Response
    {
        $request = new Request('GET', '/surat-masuk/' . $suratId . '/lembar-disposisi');
        $request->setUser(['id' => $this->operatorId, 'nama' => 'Operator Satu', 'role' => 'operator']);
        return $this->controller($store)->lembar($request, $suratId);
    }

    public function test_lembar_memuat_data_surat_dan_unit_aktif_berjenjang_tanpa_unit_nonaktif(): void
    {
        $suratId = $this->surat();
        $context = $this->service->lembarContext($suratId);
        self::assertSame(
            [$this->psdm, $this->subbidang, $this->sekretariat],
            array_map(static fn (array $u): int => (int) $u['id'], $context['units'])
        );

        $html = $this->lembar($context);

        self::assertStringContainsString('LEMBAR DISPOSISI', $html);
        self::assertStringContainsString('PEMERINTAH KABUPATEN UJI', $html);
        foreach (['0001/2026', 'BKN Kanreg VIII Banjarmasin', 'NS-1', '2 Mar 2026', '27 Feb 2026', 'Biasa', '800 — Kepegawaian', 'Undangan sosialisasi HCDP'] as $isi) {
            self::assertStringContainsString($isi, $html);
        }
        self::assertStringContainsString('<span>Kepala Bidang PSDM</span>', $html);
        self::assertStringContainsString('<span>Sekretaris</span>', $html);
        self::assertMatchesRegularExpression(
            '#<span class="inline-block w-6 shrink-0" aria-hidden="true"></span>\s*<span class="inline-block size-4[^"]*" aria-hidden="true"></span>\s*<span>Subbidang Pengembangan Kompetensi</span>#',
            $html
        );
        self::assertStringNotContainsString('Bidang Lama', $html);
        // 3 unit aktif + 2 baris kosong + 3 instruksi baku + 1 instruksi kosong
        self::assertSame(9, substr_count($html, 'inline-block size-4 shrink-0 border border-ink'));
        foreach (['Tanggapan dan saran', 'Proses lebih lanjut', 'Koordinasi/konfirmasikan', 'Batas waktu:', 'Catatan:', 'Paraf'] as $isi) {
            self::assertStringContainsString($isi, $html);
        }
        self::assertStringContainsString('data-print=""', $html);
        self::assertStringContainsString('Dicetak dari SIMASUK · 3 Mar 2026 09.00 oleh Operator Satu', $html);
    }

    public function test_lembar_tanpa_unit_aktif_tetap_memberi_baris_kosong(): void
    {
        $this->db->execute('UPDATE unit_kerja SET is_active = 0');
        $suratId = $this->surat();
        $context = $this->service->lembarContext($suratId);
        self::assertSame([], $context['units']);

        $html = $this->lembar($context);

        self::assertStringContainsString('Belum ada unit tujuan aktif; tulis tujuan secara manual.', $html);
        // 5 baris kosong tujuan + 1 baris kosong instruksi
        self::assertSame(6, substr_count($html, '<li class="flex items-end gap-2">'));
    }

    public function test_lembar_surat_selesai_atau_dibatalkan_ditolak_dan_tombol_cetak_tak_tampil(): void
    {
        $terbuka = $this->surat(1);
        self::assertStringContainsString(
            'href="/surat-masuk/' . $terbuka . '/lembar-disposisi"',
            $this->render('Agenda::detail', $this->detailData($terbuka))
        );

        foreach (['selesai' => 2, 'dibatalkan' => 3] as $status => $no) {
            $suratId = $this->surat($no, $status);
            try {
                $this->service->lembarContext($suratId);
                self::fail("Lembar surat {$status} seharusnya ditolak");
            } catch (DomainRuleException $e) {
                self::assertSame(DisposisiService::PESAN_LEMBAR_TERTUTUP, $e->getMessage());
            }
            self::assertStringNotContainsString('lembar-disposisi', $this->render('Agenda::detail', $this->detailData($suratId)));

            $store = [];
            $response = $this->bukaLembar($suratId, $store);
            self::assertSame(303, $response->status);
            self::assertSame('/surat-masuk/' . $suratId, $response->headers['Location']);
            self::assertSame(['type' => 'danger', 'message' => DisposisiService::PESAN_LEMBAR_TERTUTUP], $store['_flash']['toast']);
        }
    }

    public function test_lembar_surat_tidak_ada_404_dan_isi_surat_di_escape(): void
    {
        try {
            $this->service->lembarContext(999999);
            self::fail('Seharusnya 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }

        $suratId = $this->surat();
        $this->db->execute('UPDATE surat_masuk SET perihal = ? WHERE id = ?', ['<script>alert(1)</script> & "uji"', $suratId]);
        $html = $this->lembar($this->service->lembarContext($suratId));

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;uji&quot;', $html);
    }

    public function test_lembar_hanya_baca_tanpa_log_audit_dan_memakai_layout_cetak(): void
    {
        $suratId = $this->surat();
        $sebelum = (int) $this->db->fetchValue('SELECT COUNT(*) FROM log_audit');

        $store = [];
        $response = $this->bukaLembar($suratId, $store);

        self::assertSame($sebelum, (int) $this->db->fetchValue('SELECT COUNT(*) FROM log_audit'));
        self::assertSame(200, $response->status);
        self::assertStringContainsString('<title>Lembar Disposisi — Surat 0001/2026 · SIMASUK</title>', $response->body);
        self::assertStringContainsString('BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA', $response->body);
        self::assertStringContainsString('oleh Operator Satu', $response->body);
        self::assertStringContainsString('print:hidden', $response->body);
        self::assertStringNotContainsString('data-drawer-open', $response->body);
        self::assertStringNotContainsString('action="/logout"', $response->body);
        self::assertSame('diterima', $this->statusSurat($suratId));
    }
}
