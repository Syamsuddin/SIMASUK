<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Audit\Repository\AuditRepository;
use Modules\Pengguna\Repository\PenggunaRepository;
use Modules\Pengguna\Service\PenggunaService;

/** Penelusuran log audit oleh Admin (docs/23 F-AUDIT halaman). */
final class AuditRepositoryTest extends IntegrationTestCase
{
    private AuditRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AuditRepository($this->db);
    }

    /** @param array<string,string> $filter @return list<string> */
    private function aksi(array $filter, int $page = 1, int $perPage = 25): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['aksi'],
            $this->repo->paginate($filter, $page, $perPage)['rows']
        );
    }

    public function test_filter_aksi_surat_cancel_hanya_memuat_baris_pembatalan(): void
    {
        $this->audit->record('surat.create', 'surat_masuk', 1, ['sesudah' => ['no_agenda' => 1]]);
        $this->audit->record('surat.cancel', 'surat_masuk', 1, ['alasan' => 'Salah catat']);
        $this->audit->record('disposisi.create', 'disposisi', 4);
        $this->audit->record('surat.cancel', 'surat_masuk', 2, ['alasan' => 'Ganda']);

        $hasil = $this->repo->paginate(['aksi' => 'surat.cancel'], 1, 25);

        self::assertSame(2, $hasil['total']);
        self::assertSame(['surat.cancel', 'surat.cancel'], array_column($hasil['rows'], 'aksi'));
        self::assertSame([2, 1], array_map('intval', array_column($hasil['rows'], 'entitas_id')), 'Terbaru lebih dulu');
    }

    public function test_filter_pengguna_dan_rentang_tanggal_inklusif_sampai_akhir_hari(): void
    {
        $siti = $this->makeUser('siti');
        $budi = $this->makeUser('budi');
        $this->repo->insert($siti, 'a.sebelum', null, null, null, null, '2026-10-01 23:59:59');
        $this->repo->insert($siti, 'b.awal_hari', null, null, null, null, '2026-10-02 00:00:00');
        $this->repo->insert($siti, 'c.akhir_hari', null, null, null, null, '2026-10-02 23:59:59');
        $this->repo->insert($budi, 'd.orang_lain', null, null, null, null, '2026-10-02 12:00:00');
        $this->repo->insert($siti, 'e.sesudah', null, null, null, null, '2026-10-03 00:00:00');

        self::assertSame(
            ['c.akhir_hari', 'b.awal_hari'],
            $this->aksi(['user_id' => (string) $siti, 'dari' => '2026-10-02', 'sampai' => '2026-10-02'])
        );
        self::assertSame(['e.sesudah', 'c.akhir_hari', 'd.orang_lain', 'b.awal_hari'], $this->aksi(['dari' => '2026-10-02']));
        self::assertSame(['a.sebelum'], $this->aksi(['sampai' => '2026-10-01']));
    }

    public function test_urut_terbaru_dan_paginasi_25_dengan_nama_pengguna(): void
    {
        $siti = $this->makeUser('siti');
        for ($i = 1; $i <= 27; $i++) {
            $this->repo->insert($siti, 'uji.' . $i, null, null, null, '10.0.0.1', sprintf('2026-10-01 08:%02d:00', $i));
        }
        $this->repo->insert(null, 'auth.login_failed', null, null, null, '10.0.0.9', '2026-10-01 09:00:00');

        $hal1 = $this->repo->paginate([], 1, 25);
        self::assertSame(28, $hal1['total']);
        self::assertCount(25, $hal1['rows']);
        self::assertSame('auth.login_failed', $hal1['rows'][0]['aksi']);
        self::assertNull($hal1['rows'][0]['user_nama'], 'Aksi tanpa pengguna tetap tampil');
        self::assertSame('Siti', $hal1['rows'][1]['user_nama']);
        self::assertSame(['uji.3', 'uji.2', 'uji.1'], $this->aksi([], 2));
    }

    public function test_daftar_aksi_unik_urut_abjad(): void
    {
        $this->audit->record('surat.update');
        $this->audit->record('auth.login');
        $this->audit->record('surat.update');

        self::assertSame(['auth.login', 'surat.update'], $this->repo->actions());
    }

    public function test_aksi_pengguna_tidak_menulis_password_hash_ke_data_json(): void
    {
        $admin = $this->makeUser('admin1', 'admin');
        $this->audit->setContext($admin, '127.0.0.1');
        $service = new PenggunaService(new PenggunaRepository($this->db), $this->audit, $this->db);
        $id = $service->create([
            'username' => 'siti.tu',
            'nama' => 'Siti Aminah',
            'role' => 'operator',
            'password' => 'Sandi-Baru-2026',
            'password_konfirmasi' => 'Sandi-Baru-2026',
        ]);
        $service->resetPassword($id, ['password' => 'Sandi-Lain-2026', 'password_konfirmasi' => 'Sandi-Lain-2026']);

        $rows = $this->repo->paginate(['user_id' => (string) $admin], 1, 25)['rows'];
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertStringNotContainsString('password', (string) $row['data_json'], $row['aksi']);
            self::assertStringNotContainsString('$argon2', (string) $row['data_json'], $row['aksi']);
        }
    }
}
