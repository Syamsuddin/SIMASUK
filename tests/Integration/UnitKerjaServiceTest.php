<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\ValidationException;
use Modules\Master\Repository\UnitKerjaRepository;
use Modules\Master\Service\UnitKerjaService;

final class UnitKerjaServiceTest extends IntegrationTestCase
{
    private UnitKerjaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitKerjaService(new UnitKerjaRepository($this->db), $this->audit, $this->db);
    }

    /** @return array{kepala:int,psdm:int,subbid:int,sekretariat:int} */
    private function pohon(): array
    {
        $kepala = $this->service->create(['nama' => 'Kepala Badan', 'urutan' => '1']);
        $psdm = $this->service->create(['nama' => 'Bidang PSDM', 'jabatan_pimpinan' => 'Kepala Bidang PSDM', 'parent_id' => (string) $kepala, 'urutan' => '2']);
        $subbid = $this->service->create(['nama' => 'Subbidang Diklat', 'parent_id' => (string) $psdm]);
        $sekretariat = $this->service->create(['nama' => 'Sekretariat', 'parent_id' => (string) $kepala, 'urutan' => '1']);
        return compact('kepala', 'psdm', 'subbid', 'sekretariat');
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

    public function test_pohon_berurutan_dengan_kedalaman(): void
    {
        $this->pohon();

        $tree = array_map(static fn (array $u): string => $u['depth'] . ':' . $u['nama'], $this->service->tree());

        self::assertSame(['0:Kepala Badan', '1:Sekretariat', '1:Bidang PSDM', '2:Subbidang Diklat'], $tree);
        self::assertCount(4, $this->auditRows('unit.create'));
    }

    public function test_unit_nonaktif_tak_muncul_di_pilihan_tujuan_tetapi_namanya_tetap_ada(): void
    {
        $u = $this->pohon();

        $this->service->setActive($u['psdm'], false);

        $pilihan = array_column($this->service->pilihanTujuan(), 'nama');
        self::assertNotContains('Bidang PSDM', $pilihan);
        self::assertContains('Subbidang Diklat', $pilihan);
        self::assertSame('Bidang PSDM', $this->service->findOrFail($u['psdm'])['nama']);
        self::assertCount(1, $this->auditRows('unit.deactivate'));
    }

    public function test_induk_tidak_boleh_diri_sendiri(): void
    {
        $u = $this->pohon();

        $errors = $this->errorsOf(fn () => $this->service->update($u['psdm'], ['nama' => 'Bidang PSDM', 'parent_id' => (string) $u['psdm']]));

        self::assertSame(['parent_id' => UnitKerjaService::PESAN_INDUK_MELINGKAR], $errors);
    }

    public function test_induk_tidak_boleh_anak_atau_cucu(): void
    {
        $u = $this->pohon();

        self::assertSame(
            ['parent_id' => UnitKerjaService::PESAN_INDUK_MELINGKAR],
            $this->errorsOf(fn () => $this->service->update($u['kepala'], ['nama' => 'Kepala Badan', 'parent_id' => (string) $u['psdm']]))
        );
        self::assertSame(
            ['parent_id' => UnitKerjaService::PESAN_INDUK_MELINGKAR],
            $this->errorsOf(fn () => $this->service->update($u['kepala'], ['nama' => 'Kepala Badan', 'parent_id' => (string) $u['subbid']]))
        );
        self::assertNull($this->service->findOrFail($u['kepala'])['parent_id']);
        self::assertCount(0, $this->auditRows('unit.update'));
    }

    public function test_pilihan_induk_mengecualikan_diri_dan_turunan(): void
    {
        $u = $this->pohon();

        $ids = array_map(static fn (array $p): int => (int) $p['id'], $this->service->parentOptions($u['psdm']));

        sort($ids);
        self::assertSame([$u['kepala'], $u['sekretariat']], $ids);
    }

    public function test_pindah_induk_yang_sah_tersimpan_dan_diaudit(): void
    {
        $u = $this->pohon();

        $this->service->update($u['subbid'], ['nama' => 'Subbidang Diklat', 'parent_id' => (string) $u['sekretariat']]);

        self::assertSame($u['sekretariat'], (int) $this->service->findOrFail($u['subbid'])['parent_id']);
        $data = json_decode((string) $this->auditRows('unit.update')[0]['data_json'], true);
        self::assertSame($u['psdm'], $data['sebelum']['parent_id']);
        self::assertSame($u['sekretariat'], $data['sesudah']['parent_id']);
    }

    public function test_induk_tak_ada_dan_isian_tak_valid_ditolak(): void
    {
        self::assertSame(
            ['parent_id' => UnitKerjaService::PESAN_INDUK_TIDAK_ADA],
            $this->errorsOf(fn () => $this->service->create(['nama' => 'Yatim', 'parent_id' => '999']))
        );
        $errors = $this->errorsOf(fn () => $this->service->create(['nama' => '', 'urutan' => 'satu']));
        self::assertSame('Wajib diisi.', $errors['nama']);
        self::assertSame('Harus berupa angka bulat.', $errors['urutan']);
    }
}
