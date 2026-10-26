<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DomainRuleException;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use Modules\Agenda\Controller\BerkasController;
use Modules\Agenda\Repository\AgendaCounterRepository;
use Modules\Agenda\Repository\BerkasSuratRepository;
use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Agenda\Service\AgendaNumberService;
use Modules\Agenda\Service\SuratMasukService;
use Modules\Master\Repository\KlasifikasiRepository;
use Modules\Master\Service\KlasifikasiService;

/** Pencatatan, berkas, dugaan ganda, unduh, ubah, dan pembatalan surat (docs/23 F-AGENDA). */
final class SuratMasukServiceTest extends IntegrationTestCase
{
    private string $root;
    private string $tmp;
    private SuratMasukService $service;
    private int $klasifikasiId;
    private int $operatorId;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/simasuk-surat-' . bin2hex(random_bytes(4));
        $this->root = $base . '/storage';
        $this->tmp = $base . '/tmp';
        mkdir($this->root, 0750, true);
        mkdir($this->tmp, 0750, true);

        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['800', 'Kepegawaian', $now, $now]
        );
        $this->klasifikasiId = $this->db->lastInsertId();
        $this->operatorId = $this->makeUser('operator1');
        $this->audit->setContext($this->operatorId, '127.0.0.1');
        $this->service = $this->makeService(new BerkasSuratRepository($this->db));
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname($this->root), \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir(dirname($this->root));
        View::reset();
        parent::tearDown();
    }

    private function makeService(BerkasSuratRepository $berkas): SuratMasukService
    {
        return new SuratMasukService(
            new SuratMasukRepository($this->db),
            $berkas,
            new AgendaNumberService(new AgendaCounterRepository($this->db), $this->db),
            new KlasifikasiService(new KlasifikasiRepository($this->db), $this->audit, $this->db),
            new FileStore($this->root, 10, false),
            $this->audit,
            $this->db
        );
    }

    /** @param array<string,mixed> $over @return array<string,mixed> */
    private function input(array $over = []): array
    {
        return [
            'tgl_terima' => '2026-03-02',
            'no_surat' => '800/125/BKN.VIII/2026',
            'tgl_surat' => '2026-02-27',
            'asal' => 'BKN Kanreg VIII Banjarmasin',
            'perihal' => 'Undangan sosialisasi HCDP',
            'sifat' => 'biasa',
            'klasifikasi_id' => (string) $this->klasifikasiId,
            'no_srikandi' => '',
            'jumlah_lampiran' => '1 berkas',
            ...$over,
        ];
    }

    /** @return array{name:string,tmp_name:string,error:int,size:int} */
    private function upload(string $name = 'scan.pdf', ?string $content = null): array
    {
        $content ??= "%PDF-1.4\n" . bin2hex(random_bytes(16)) . "\n%%EOF\n";
        $path = $this->tmp . '/' . bin2hex(random_bytes(6));
        file_put_contents($path, $content);
        return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
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

    /** @param array<string,mixed> $over */
    private function create(array $over = [], int $files = 1): int
    {
        $uploads = array_map(fn (int $i) => $this->upload('scan-' . $i . '.pdf'), range(1, $files));
        return $this->service->create($this->input($over), $uploads, $this->operatorId);
    }

    private function counter(int $tahun): ?int
    {
        $value = $this->db->fetchValue('SELECT last_no FROM agenda_counter WHERE tahun = ?', [$tahun]);
        return $value === null ? null : (int) $value;
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return glob($this->root . '/surat/*/*.pdf') ?: [];
    }

    public function test_surat_pertama_tahun_2026_bernomor_1_tampil_0001_2026(): void
    {
        $id = $this->create();
        $surat = $this->service->findOrFail($id);

        self::assertSame(1, (int) $surat['no_agenda']);
        self::assertSame(2026, (int) $surat['tahun']);
        self::assertSame('0001/2026', SuratMasukService::nomor($surat));
        self::assertSame('diterima', $surat['status']);
        self::assertSame($this->operatorId, (int) $surat['created_by']);
    }

    public function test_nomor_reset_saat_tahun_terima_berganti(): void
    {
        $this->db->execute('INSERT INTO agenda_counter (tahun, last_no) VALUES (2025, 412)');

        $baru = $this->create(['tgl_terima' => '2026-01-02', 'tgl_surat' => '2025-12-30']);
        $lama = $this->create(['tgl_terima' => '2025-12-31', 'tgl_surat' => '2025-12-30', 'no_surat' => 'X/2025']);

        self::assertSame('0001/2026', SuratMasukService::nomor($this->service->findOrFail($baru)));
        self::assertSame('0413/2025', SuratMasukService::nomor($this->service->findOrFail($lama)));
    }

    public function test_png_berekstensi_pdf_ditolak_dan_tidak_ada_nomor_terpakai(): void
    {
        $png = $this->upload('scan.pdf', "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32));
        $errors = $this->errorsOf(fn () => $this->service->create($this->input(), [$png], $this->operatorId));

        self::assertSame(['berkas' => 'Berkas harus PDF.'], $errors);
        self::assertNull($this->counter(2026));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM surat_masuk'));
        self::assertSame(1, (int) $this->service->findOrFail($this->create())['no_agenda']);
    }

    public function test_berkas_10_5_mb_ditolak(): void
    {
        $big = $this->upload('besar.pdf', "%PDF-1.4\n" . str_repeat('0', (int) (10.5 * 1024 * 1024)));
        $errors = $this->errorsOf(fn () => $this->service->create($this->input(), [$big], $this->operatorId));
        self::assertSame('Ukuran berkas maks 10 MB.', $errors['berkas']);
    }

    public function test_tgl_surat_setelah_tgl_terima_ditolak_di_field_tgl_surat(): void
    {
        $errors = $this->errorsOf(fn () => $this->create(['tgl_surat' => '2026-03-03']));
        self::assertSame(['tgl_surat' => SuratMasukService::PESAN_TGL_SURAT], $errors);
    }

    public function test_tgl_terima_melewati_hari_ini_ditolak(): void
    {
        $besok = date('Y-m-d', strtotime('+1 day'));
        $errors = $this->errorsOf(fn () => $this->create(['tgl_terima' => $besok]));
        self::assertSame(SuratMasukService::PESAN_TGL_TERIMA_DEPAN, $errors['tgl_terima']);
    }

    public function test_isian_wajib_dan_berkas_dilaporkan_bersamaan(): void
    {
        $errors = $this->errorsOf(fn () => $this->service->create(
            $this->input(['no_surat' => '', 'perihal' => '  ', 'sifat' => 'kilat', 'klasifikasi_id' => '999']),
            [],
            $this->operatorId
        ));
        self::assertSame([
            'no_surat' => Validator::MSG_REQUIRED,
            'perihal' => Validator::MSG_REQUIRED,
            'sifat' => Validator::MSG_IN,
            'klasifikasi_id' => Validator::MSG_IN,
            'berkas' => 'Pilih minimal satu berkas PDF.',
        ], $errors);
    }

    public function test_klasifikasi_nonaktif_ditolak_untuk_surat_baru_tetapi_tetap_sah_saat_ubah(): void
    {
        $id = $this->create();
        $this->db->execute('UPDATE klasifikasi_arsip SET is_active = 0 WHERE id = ?', [$this->klasifikasiId]);

        $errors = $this->errorsOf(fn () => $this->create(['no_surat' => 'lain']));
        self::assertSame(Validator::MSG_IN, $errors['klasifikasi_id']);

        $this->service->update($id, $this->input(['perihal' => 'Perihal dikoreksi']), $this->operatorId);
        self::assertSame('Perihal dikoreksi', $this->service->findOrFail($id)['perihal']);
    }

    public function test_dugaan_ganda_hanya_disimpan_bila_tetap_simpan_dicentang(): void
    {
        $pertama = $this->create();

        $errors = $this->errorsOf(fn () => $this->create(['perihal' => 'Kiriman ulang']));
        self::assertSame(['tetap_simpan' => SuratMasukService::PESAN_GANDA], $errors);
        self::assertSame(1, $this->counter(2026), 'Peringatan ganda tidak memakai nomor');
        self::assertSame([$pertama], array_map(
            static fn (array $r): int => (int) $r['id'],
            $this->service->duplicatesFor($this->input())
        ));

        $kedua = $this->create(['perihal' => 'Kiriman ulang', 'tetap_simpan' => '1']);
        self::assertSame(2, (int) $this->service->findOrFail($kedua)['no_agenda']);
    }

    public function test_dugaan_ganda_mengabaikan_surat_dibatalkan(): void
    {
        $pertama = $this->create();
        $this->service->cancel($pertama, ['alasan' => 'Salah catat'], $this->operatorId);
        $kedua = $this->create();
        self::assertSame(2, (int) $this->service->findOrFail($kedua)['no_agenda']);
    }

    public function test_berkas_tersimpan_di_storage_tanpa_url_statis_dan_diunduh_sebagai_pdf(): void
    {
        $content = "%PDF-1.4\nisi surat uji\n%%EOF\n";
        $id = $this->service->create($this->input(), [$this->upload('Undangan HCDP.pdf', $content)], $this->operatorId);

        $row = $this->db->fetchOne('SELECT * FROM berkas_surat WHERE surat_id = ?', [$id]);
        self::assertNotNull($row);
        self::assertMatchesRegularExpression('#^surat/2026/[0-9a-f]{32}\.pdf$#', $row['path']);
        $absolute = $this->root . '/' . $row['path'];
        self::assertFileExists($absolute);
        self::assertStringStartsNotWith((string) realpath(dirname(__DIR__, 2) . '/public'), (string) realpath($absolute));
        self::assertSame(hash('sha256', $content), $row['sha256']);
        self::assertSame('Undangan HCDP.pdf', $row['nama_asli']);

        $request = new Request('GET', '/berkas/' . $row['id']);
        $request->setUser(['id' => $this->operatorId, 'role' => 'operator']);
        $response = (new BerkasController($this->service))->download($request, (int) $row['id']);

        self::assertSame(200, $response->status);
        self::assertSame('application/pdf', $response->headers['Content-Type']);
        self::assertStringStartsWith('attachment; filename="Undangan HCDP.pdf"', $response->headers['Content-Disposition']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame($content, $response->body);
    }

    public function test_berkas_dilepas_404_untuk_operator_tetapi_admin_boleh(): void
    {
        $id = $this->create();
        $berkasId = (int) $this->db->fetchValue('SELECT id FROM berkas_surat WHERE surat_id = ?', [$id]);
        $this->db->execute('UPDATE berkas_surat SET deleted_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $berkasId]);

        try {
            $this->service->berkasForDownload($berkasId, 'operator');
            self::fail('Seharusnya 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }
        self::assertStringStartsWith('%PDF-', $this->service->berkasForDownload($berkasId, 'admin')['isi']);
        self::assertSame([], $this->service->detail($id)['berkas']);
    }

    public function test_gagal_simpan_berkas_membatalkan_transaksi_tanpa_memakai_nomor(): void
    {
        $gagalKedua = new class ($this->db) extends BerkasSuratRepository {
            private int $calls = 0;

            public function insert(int $suratId, string $path, string $namaAsli, int $ukuran, string $sha256, ?int $userId, string $now): int
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('Simulasi gagal simpan berkas');
                }
                return parent::insert($suratId, $path, $namaAsli, $ukuran, $sha256, $userId, $now);
            }
        };
        $service = $this->makeService($gagalKedua);

        try {
            $service->create($this->input(), [$this->upload('a.pdf'), $this->upload('b.pdf')], $this->operatorId);
            self::fail('Seharusnya RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertSame('Simulasi gagal simpan berkas', $e->getMessage());
        }

        self::assertNull($this->counter(2026));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM surat_masuk'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM berkas_surat'));
        self::assertSame([], $this->storedFiles(), 'Berkas yang sudah tertulis dihapus saat rollback');
        self::assertSame([], $this->auditRows('surat.create'));
    }

    public function test_catat_surat_menulis_satu_baris_audit_dengan_daftar_berkas(): void
    {
        $id = $this->create([], 3);
        $rows = $this->auditRows('surat.create');
        self::assertCount(1, $rows);
        self::assertSame($id, (int) $rows[0]['entitas_id']);
        self::assertSame($this->operatorId, (int) $rows[0]['user_id']);
        $data = json_decode((string) $rows[0]['data_json'], true);
        self::assertSame(1, $data['sesudah']['no_agenda']);
        self::assertCount(3, $data['sesudah']['berkas']);
        self::assertCount(3, $this->service->detail($id)['berkas']);
    }

    public function test_admin_membatalkan_surat_dengan_alasan_dan_nomor_tidak_dipakai_ulang(): void
    {
        $adminId = $this->makeUser('admin1', 'admin');
        $this->audit->setContext($adminId, '127.0.0.1');
        $id = $this->create();

        $this->service->cancel($id, ['alasan' => 'Surat salah alamat'], $adminId);

        $surat = $this->service->findOrFail($id);
        self::assertSame('dibatalkan', $surat['status']);
        self::assertSame('Surat salah alamat', $surat['cancel_reason']);
        self::assertNotNull($surat['cancelled_at']);
        $audit = $this->auditRows('surat.cancel');
        self::assertCount(1, $audit);
        self::assertSame($adminId, (int) $audit[0]['user_id']);

        $berikutnya = $this->create(['no_surat' => 'lain']);
        self::assertSame(2, (int) $this->service->findOrFail($berikutnya)['no_agenda']);
    }

    public function test_batal_tanpa_alasan_ditolak_dan_batal_dua_kali_ditolak(): void
    {
        $id = $this->create();
        self::assertSame(['alasan' => Validator::MSG_REQUIRED], $this->errorsOf(fn () => $this->service->cancel($id, ['alasan' => ' '], 1)));

        $this->service->cancel($id, ['alasan' => 'Ganda'], $this->operatorId);
        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage(SuratMasukService::PESAN_SUDAH_BATAL);
        $this->service->cancel($id, ['alasan' => 'Lagi'], $this->operatorId);
    }

    public function test_ubah_metadata_tidak_mengubah_nomor_dan_tercatat_audit(): void
    {
        $id = $this->create();
        $this->service->update($id, $this->input(['perihal' => 'Undangan rapat koordinasi', 'sifat' => 'segera']), $this->operatorId);

        $surat = $this->service->findOrFail($id);
        self::assertSame('Undangan rapat koordinasi', $surat['perihal']);
        self::assertSame('segera', $surat['sifat']);
        self::assertSame('0001/2026', SuratMasukService::nomor($surat));
        $audit = $this->auditRows('surat.update');
        self::assertCount(1, $audit);
        $data = json_decode((string) $audit[0]['data_json'], true);
        self::assertSame('Undangan sosialisasi HCDP', $data['sebelum']['perihal']);

        $errors = $this->errorsOf(fn () => $this->service->update($id, $this->input(['tgl_terima' => '2025-12-31', 'tgl_surat' => '2025-12-30']), $this->operatorId));
        self::assertSame('Tanggal terima harus tetap di tahun agenda 2026.', $errors['tgl_terima']);
    }

    public function test_surat_dibatalkan_tidak_bisa_diubah(): void
    {
        $id = $this->create();
        $this->service->cancel($id, ['alasan' => 'Ganda'], $this->operatorId);
        $this->expectException(DomainRuleException::class);
        $this->service->update($id, $this->input(), $this->operatorId);
    }

    public function test_daftar_mengabaikan_filter_tak_valid(): void
    {
        $this->create();
        $result = $this->service->list(['sifat' => 'kilat', 'page' => 'x']);
        self::assertSame(['q' => '', 'tahun' => '', 'status' => '', 'sifat' => ''], $result['filter']);
        self::assertSame(1, $result['total']);
        self::assertSame([2026], $result['years']);
    }

    public function test_tombol_batalkan_surat_hanya_tampil_untuk_admin(): void
    {
        $id = $this->create();
        $store = [];
        View::share('session', new Session($store));
        View::share('errors', []);
        View::share('old', []);
        $data = [...$this->service->detail($id), 'disposisi' => []];

        $operator = View::render('Agenda::detail', [...$data, 'isAdmin' => false], null);
        $admin = View::render('Agenda::detail', [...$data, 'isAdmin' => true], null);

        self::assertStringNotContainsString('/batalkan', $operator);
        self::assertStringNotContainsString('Batalkan Surat', $operator);
        self::assertStringContainsString('action="/surat-masuk/' . $id . '/batalkan"', $admin);
        self::assertStringContainsString('name="alasan"', $admin);
    }
}
