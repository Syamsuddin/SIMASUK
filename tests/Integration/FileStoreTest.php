<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Env;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\ValidationException;

/** FileStore menulis ke folder sementara; transaksi memakai PDO nyata (docs/13). */
final class FileStoreTest extends IntegrationTestCase
{
    private string $root;
    private string $tmp;
    private FileStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/simasuk-fs-' . bin2hex(random_bytes(4));
        $this->root = $base . '/storage';
        $this->tmp = $base . '/tmp';
        mkdir($this->root, 0750, true);
        mkdir($this->tmp, 0750, true);
        $this->store = new FileStore($this->root, 10, false);
    }

    protected function tearDown(): void
    {
        self::removeTree(dirname($this->root));
        parent::tearDown();
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /** @return array{name:string,tmp_name:string,error:int,size:int} */
    private function upload(string $name, string $content): array
    {
        $path = $this->tmp . '/' . bin2hex(random_bytes(6));
        file_put_contents($path, $content);
        return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private static function pdf(int $padding = 64): string
    {
        return "%PDF-1.4\n" . str_repeat('0', $padding) . "\n%%EOF\n";
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return glob($this->root . '/surat/*/*.pdf') ?: [];
    }

    /** @param list<array{name:string,tmp_name:string,error:int,size:int}> $uploads */
    private function validationError(array $uploads): ?string
    {
        try {
            $this->store->validatePdfs($uploads);
        } catch (ValidationException $e) {
            return $e->errors()['berkas'] ?? null;
        }
        return null;
    }

    public function test_pdf_valid_tersimpan_di_luar_webroot_dengan_nama_acak_dan_izin_0640(): void
    {
        $content = self::pdf();
        $upload = $this->upload("../../surat\x01 undangan.pdf", $content);

        $file = $this->db->transaction(fn () => $this->store->storePdf($upload, 2026, $this->db));

        self::assertMatchesRegularExpression('#^surat/2026/[0-9a-f]{32}\.pdf$#', $file['path']);
        $absolute = $this->root . '/' . $file['path'];
        self::assertFileExists($absolute);
        self::assertStringStartsNotWith((string) realpath(dirname(__DIR__, 2) . '/public'), (string) realpath($absolute));
        self::assertSame('0640', substr(sprintf('%o', fileperms($absolute)), -4));
        self::assertSame(hash('sha256', $content), $file['sha256']);
        self::assertSame(strlen($content), $file['ukuran']);
        self::assertSame('.._.._surat undangan.pdf', $file['nama_asli']);
        self::assertSame($content, $this->store->read($file['path']));
    }

    public function test_dua_berkas_tidak_pernah_bernama_sama(): void
    {
        $paths = $this->db->transaction(fn () => [
            $this->store->storePdf($this->upload('a.pdf', self::pdf()), 2026, $this->db)['path'],
            $this->store->storePdf($this->upload('a.pdf', self::pdf()), 2026, $this->db)['path'],
        ]);
        self::assertNotSame($paths[0], $paths[1]);
    }

    public function test_png_berekstensi_pdf_ditolak(): void
    {
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32);
        self::assertSame('Berkas harus PDF.', $this->validationError([$this->upload('scan.pdf', $png)]));
    }

    public function test_teks_berawalan_bukan_pdf_ditolak(): void
    {
        self::assertSame('Berkas harus PDF.', $this->validationError([$this->upload('scan.pdf', "%PDX-1.4\nhalo")]));
    }

    public function test_berkas_10_5_mb_ditolak(): void
    {
        $big = $this->upload('besar.pdf', self::pdf((int) (10.5 * 1024 * 1024)));
        self::assertSame('Ukuran berkas maks 10 MB.', $this->validationError([$big]));
    }

    public function test_batas_ukuran_php_dilaporkan_sebagai_ukuran(): void
    {
        $upload = ['name' => 'x.pdf', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0];
        self::assertSame('Ukuran berkas maks 10 MB.', $this->validationError([$upload]));
        $upload['error'] = UPLOAD_ERR_PARTIAL;
        self::assertSame('Berkas gagal diunggah. Pilih ulang lalu simpan kembali.', $this->validationError([$upload]));
    }

    public function test_jumlah_berkas_minimal_1_maksimal_5(): void
    {
        self::assertSame('Pilih minimal satu berkas PDF.', $this->validationError([]));
        $six = array_map(fn (int $i) => $this->upload($i . '.pdf', self::pdf()), range(1, 6));
        self::assertSame('Maksimal 5 berkas PDF per surat.', $this->validationError($six));
        self::assertNull($this->validationError(array_slice($six, 0, 5)));
    }

    public function test_berkas_bukan_hasil_unggahan_http_ditolak_di_produksi(): void
    {
        $strict = new FileStore($this->root, 10, true);
        $this->expectException(ValidationException::class);
        $strict->validatePdfs([$this->upload('a.pdf', self::pdf())]);
    }

    public function test_rollback_menghapus_berkas_yang_sudah_tertulis(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->store->storePdf($this->upload('a.pdf', self::pdf()), 2026, $this->db);
                $this->store->storePdf($this->upload('b.pdf', self::pdf()), 2026, $this->db);
                self::assertCount(2, $this->storedFiles());
                throw new \RuntimeException('gagal simpan');
            });
            self::fail('Seharusnya RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertSame('gagal simpan', $e->getMessage());
        }
        self::assertSame([], $this->storedFiles());
    }

    public function test_simpan_di_luar_transaksi_ditolak(): void
    {
        $this->expectException(\LogicException::class);
        $this->store->storePdf($this->upload('a.pdf', self::pdf()), 2026, $this->db);
    }

    public function test_baca_path_di_luar_pola_atau_hilang_404(): void
    {
        foreach (['../.env', 'surat/2026/../../x.pdf', 'surat/2026/' . str_repeat('a', 32) . '.pdf'] as $path) {
            try {
                $this->store->read($path);
                self::fail('Seharusnya 404 untuk ' . $path);
            } catch (HttpException $e) {
                self::assertSame(404, $e->status());
            }
        }
    }

    public function test_uploads_menyeragamkan_files_multi_dan_membuang_slot_kosong(): void
    {
        $files = [
            'name' => ['a.pdf', '', 'c.pdf'],
            'type' => ['application/pdf', '', 'application/pdf'],
            'tmp_name' => ['/tmp/a', '', '/tmp/c'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size' => [10, 0, 30],
        ];
        $uploads = FileStore::uploads($files);
        self::assertCount(2, $uploads);
        self::assertSame(['name' => 'c.pdf', 'tmp_name' => '/tmp/c', 'error' => UPLOAD_ERR_OK, 'size' => 30], $uploads[1]);
        self::assertSame([], FileStore::uploads(null));
        self::assertCount(1, FileStore::uploads(['name' => 'a.pdf', 'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 1]));
    }

    public function test_storage_path_di_dalam_public_ditolak(): void
    {
        $app = dirname(__DIR__, 2);
        $before = (string) Env::get('STORAGE_PATH', '');
        Env::set('STORAGE_PATH', $app . '/public/berkas');
        try {
            FileStore::fromEnv($app);
            self::fail('Seharusnya LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('public/', $e->getMessage());
        } finally {
            Env::set('STORAGE_PATH', $before);
        }
    }
}
