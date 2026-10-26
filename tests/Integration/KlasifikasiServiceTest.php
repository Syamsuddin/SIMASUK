<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\HttpException;
use App\Core\ValidationException;
use Modules\Master\Repository\KlasifikasiRepository;
use Modules\Master\Service\KlasifikasiService;

final class KlasifikasiServiceTest extends IntegrationTestCase
{
    private KlasifikasiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KlasifikasiService(new KlasifikasiRepository($this->db), $this->audit, $this->db);
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

    public function test_simpan_klasifikasi_dan_audit(): void
    {
        $id = $this->service->create(['kode' => ' 800.1.2 ', 'uraian' => 'Kepegawaian - Pengadaan']);

        $row = $this->service->findOrFail($id);
        self::assertSame('800.1.2', $row['kode']);
        self::assertSame(1, (int) $row['is_active']);
        self::assertCount(1, $this->auditRows('klasifikasi.create'));
    }

    public function test_kode_yang_sudah_ada_ditolak_dengan_pesan_baku(): void
    {
        $this->service->create(['kode' => '800.1.2', 'uraian' => 'Asli']);

        $errors = $this->errorsOf(fn () => $this->service->create(['kode' => '800.1.2', 'uraian' => 'Ganda']));

        self::assertSame(['kode' => 'Kode klasifikasi sudah dipakai.'], $errors);
        self::assertSame(1, $this->service->list([])['total']);
    }

    public function test_ubah_ke_kode_milik_klasifikasi_lain_ditolak_ke_kode_sendiri_boleh(): void
    {
        $a = $this->service->create(['kode' => '800.1', 'uraian' => 'A']);
        $this->service->create(['kode' => '800.2', 'uraian' => 'B']);

        self::assertSame(
            ['kode' => KlasifikasiService::PESAN_KODE_DIPAKAI],
            $this->errorsOf(fn () => $this->service->update($a, ['kode' => '800.2', 'uraian' => 'A']))
        );

        $this->service->update($a, ['kode' => '800.1', 'uraian' => 'A diperbarui']);
        self::assertSame('A diperbarui', $this->service->findOrFail($a)['uraian']);
        $log = $this->auditRows('klasifikasi.update');
        self::assertCount(1, $log);
        $data = json_decode((string) $log[0]['data_json'], true);
        self::assertSame('A', $data['sebelum']['uraian']);
        self::assertSame('A diperbarui', $data['sesudah']['uraian']);
    }

    public function test_validasi_wajib_dan_panjang_maksimal(): void
    {
        $errors = $this->errorsOf(fn () => $this->service->create(['kode' => str_repeat('9', 21), 'uraian' => '']));
        self::assertSame('Maksimal 20 karakter.', $errors['kode']);
        self::assertSame('Wajib diisi.', $errors['uraian']);
    }

    public function test_klasifikasi_nonaktif_hilang_dari_pilihan_formulir(): void
    {
        $a = $this->service->create(['kode' => '800.1', 'uraian' => 'A']);
        $this->service->create(['kode' => '800.2', 'uraian' => 'B']);

        $this->service->setActive($a, false);

        self::assertSame(['800.2'], array_column($this->service->activeOptions(), 'kode'));
        self::assertSame('800.1', $this->service->findOrFail($a)['kode']);
        self::assertCount(1, $this->auditRows('klasifikasi.deactivate'));

        $this->service->setActive($a, true);
        self::assertSame(['800.1', '800.2'], array_column($this->service->activeOptions(), 'kode'));
    }

    public function test_id_tak_ada_404(): void
    {
        try {
            $this->service->update(999, ['kode' => 'x', 'uraian' => 'y']);
            self::fail('Seharusnya 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }
    }
}
