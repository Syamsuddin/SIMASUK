<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use Modules\Audit\Repository\AuditRepository;
use Modules\Audit\Service\AuditService;
use Modules\Pengguna\Repository\PenggunaRepository;

/** Halaman log audit: filter, saring ulang data_json, dan state (docs/23 F-AUDIT halaman). */
final class AuditServiceTest extends IntegrationTestCase
{
    private AuditService $service;
    private AuditRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AuditRepository($this->db);
        $this->service = new AuditService($this->repo, new PenggunaRepository($this->db));
    }

    protected function tearDown(): void
    {
        View::reset();
        parent::tearDown();
    }

    public function test_admin_memfilter_surat_cancel_dan_data_json_tidak_memuat_password_hash(): void
    {
        $admin = $this->makeUser('admin1', 'admin');
        $this->audit->setContext($admin, '127.0.0.1');
        $this->audit->record('surat.cancel', 'surat_masuk', 9, ['alasan' => 'Salah catat']);
        $this->audit->record('user.create', 'users', 3, ['sesudah' => ['username' => 'siti']]);
        // Baris lama yang ditulis tanpa Audit::sanitize tetap tidak boleh memperlihatkan hash.
        $this->repo->insert(
            $admin,
            'surat.cancel',
            'surat_masuk',
            10,
            '{"alasan":"Ganda","password_hash":"$argon2id$v=19$rahasia","nested":{"remember_token":"abc"}}',
            '127.0.0.1',
            date('Y-m-d H:i:s')
        );

        $hasil = $this->service->list(['aksi' => 'surat.cancel']);

        self::assertSame(2, $hasil['total']);
        self::assertSame(['surat.cancel', 'surat.cancel'], array_column($hasil['rows'], 'aksi'));
        foreach ($hasil['rows'] as $row) {
            self::assertArrayNotHasKey('data_json', $row);
            self::assertStringNotContainsString('password_hash', $row['data_tampil']);
            self::assertStringNotContainsString('argon2', $row['data_tampil']);
            self::assertStringNotContainsString('token', $row['data_tampil']);
        }
        self::assertStringContainsString('"alasan": "Ganda"', $hasil['rows'][0]['data_tampil']);
        self::assertSame(['surat.cancel' => 'surat.cancel', 'user.create' => 'user.create'], $hasil['actions']);
        self::assertSame([$admin => 'Admin1 (admin1)'], $hasil['users']);
    }

    public function test_filter_tak_valid_dibuang_dengan_pesan_dan_filter_lain_tetap_berlaku(): void
    {
        $this->audit->record('surat.cancel');
        $this->audit->record('surat.create');

        $rusak = $this->service->list(['dari' => '2026-02-30', 'aksi' => 'surat.cancel', 'user_id' => 'x']);
        self::assertSame(Validator::MSG_DATE, $rusak['errors']['dari']);
        self::assertSame(Validator::MSG_INT, $rusak['errors']['user_id']);
        self::assertSame(['user_id' => '', 'aksi' => 'surat.cancel', 'dari' => '', 'sampai' => ''], $rusak['filter']);
        self::assertSame(1, $rusak['total']);

        $terbalik = $this->service->list(['dari' => '2026-10-10', 'sampai' => '2026-10-01']);
        self::assertSame(Validator::MSG_RENTANG, $terbalik['errors']['sampai']);
        self::assertSame('', $terbalik['filter']['sampai']);
    }

    public function test_data_tampil_kosong_untuk_null_dan_json_rusak(): void
    {
        self::assertSame('', AuditService::dataTampil(null));
        self::assertSame('', AuditService::dataTampil('bukan json'));
        self::assertSame("{\n    \"ok\": true\n}", AuditService::dataTampil('{"ok":true,"csrf":"x"}'));
    }

    public function test_halaman_audit_menampilkan_baris_dan_state_kosong_berfilter(): void
    {
        $store = [];
        View::share('session', new Session($store));
        View::share('errors', []);
        View::share('old', []);
        $admin = $this->makeUser('admin1', 'admin');
        $this->audit->setContext($admin, '10.1.2.3');
        $this->audit->record('surat.cancel', 'surat_masuk', 9, ['alasan' => 'Salah <b>catat</b>']);

        $isi = View::render('Audit::index', ['result' => $this->service->list([])], null);
        self::assertStringContainsString('href="/surat-masuk/9"', $isi);
        self::assertStringContainsString('10.1.2.3', $isi);
        self::assertStringContainsString('Salah &lt;b&gt;catat&lt;/b&gt;', $isi, 'Isi data_json di-escape');

        $kosong = View::render('Audit::index', ['result' => $this->service->list(['aksi' => 'tidak.ada'])], null);
        self::assertStringContainsString('Tidak ada catatan audit yang cocok dengan filter ini.', $kosong);

        $galat = View::render('Audit::index', ['result' => $this->service->list(['dari' => '2026-10-10', 'sampai' => '2026-10-01'])], null);
        self::assertStringContainsString(Validator::MSG_RENTANG, $galat);
        self::assertStringContainsString('aria-invalid="true"', $galat);
    }
}
