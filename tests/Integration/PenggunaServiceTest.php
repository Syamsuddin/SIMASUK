<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DomainRuleException;
use App\Core\ValidationException;
use Modules\Pengguna\Repository\PenggunaRepository;
use Modules\Pengguna\Service\PenggunaService;

final class PenggunaServiceTest extends IntegrationTestCase
{
    private PenggunaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PenggunaService(new PenggunaRepository($this->db), $this->audit, $this->db);
    }

    /** @return array<string,string> */
    private static function input(array $override = []): array
    {
        return [
            'username' => 'siti.tu',
            'nama' => 'Siti Aminah',
            'role' => 'operator',
            'password' => 'Sandi-Awal-0001',
            'password_konfirmasi' => 'Sandi-Awal-0001',
            ...$override,
        ];
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

    public function test_admin_membuat_operator_dan_audit_tanpa_password(): void
    {
        $adminId = $this->makeUser('admin1', 'admin');
        $this->audit->setContext($adminId, '10.0.0.5');

        $id = $this->service->create(self::input(['username' => 'Siti.TU']));

        $row = $this->userRow($id);
        self::assertSame('siti.tu', $row['username']);
        self::assertSame('operator', $row['role']);
        self::assertSame(1, (int) $row['is_active']);
        self::assertTrue(password_verify('Sandi-Awal-0001', (string) $row['password_hash']));
        self::assertStringStartsWith('$argon2id$', (string) $row['password_hash']);

        $log = $this->auditRows('user.create');
        self::assertCount(1, $log);
        self::assertSame($adminId, (int) $log[0]['user_id']);
        self::assertSame('10.0.0.5', $log[0]['ip']);
        self::assertStringNotContainsString('password', (string) $log[0]['data_json']);
        self::assertStringNotContainsString('Sandi-Awal-0001', (string) $log[0]['data_json']);
    }

    public function test_validasi_username_ganda_format_peran_dan_password(): void
    {
        $this->makeUser('siti.tu');

        self::assertSame(['username' => 'Username sudah dipakai.'], $this->errorsOf(fn () => $this->service->create(self::input())));
        self::assertArrayHasKey('username', $this->errorsOf(fn () => $this->service->create(self::input(['username' => 'siti tu']))));
        self::assertArrayHasKey('role', $this->errorsOf(fn () => $this->service->create(self::input(['username' => 'baru', 'role' => 'pimpinan']))));
        $errors = $this->errorsOf(fn () => $this->service->create(self::input([
            'username' => 'baru', 'password' => 'pendek', 'password_konfirmasi' => 'lain',
        ])));
        self::assertSame('Minimal 10 karakter.', $errors['password']);
        self::assertSame('Konfirmasi tidak cocok.', $errors['password_konfirmasi']);
        self::assertCount(0, $this->auditRows('user.create'));
    }

    public function test_admin_tidak_bisa_menonaktifkan_dirinya_sendiri(): void
    {
        $adminId = $this->makeUser('admin1', 'admin');
        $this->makeUser('admin2', 'admin');

        try {
            $this->service->deactivate($adminId, $adminId);
            self::fail('Seharusnya ditolak');
        } catch (DomainRuleException $e) {
            self::assertSame(PenggunaService::PESAN_DIRI_SENDIRI, $e->getMessage());
        }
        self::assertSame(1, (int) $this->userRow($adminId)['is_active']);
    }

    public function test_admin_aktif_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        $terakhir = $this->makeUser('admin1', 'admin');
        $nonaktif = $this->makeUser('admin2', 'admin', false);

        try {
            $this->service->deactivate($terakhir, $nonaktif);
            self::fail('Seharusnya ditolak');
        } catch (DomainRuleException $e) {
            self::assertSame(PenggunaService::PESAN_ADMIN_TERAKHIR, $e->getMessage());
        }
        self::assertSame(1, (int) $this->userRow($terakhir)['is_active']);
        self::assertCount(0, $this->auditRows('user.deactivate'));
    }

    public function test_menonaktifkan_dan_mengaktifkan_kembali_tercatat_audit(): void
    {
        $adminId = $this->makeUser('admin1', 'admin');
        $admin2 = $this->makeUser('admin2', 'admin');
        $operator = $this->makeUser('siti');
        $this->audit->setContext($adminId, null);

        $this->service->deactivate($admin2, $adminId);
        $this->service->deactivate($operator, $adminId);
        self::assertSame(0, (int) $this->userRow($admin2)['is_active']);
        self::assertSame(0, (int) $this->userRow($operator)['is_active']);
        self::assertCount(2, $this->auditRows('user.deactivate'));

        $this->service->activate($operator);
        self::assertSame(1, (int) $this->userRow($operator)['is_active']);
        $log = $this->auditRows('user.activate');
        self::assertCount(1, $log);
        self::assertSame(['sebelum' => ['is_active' => 0], 'sesudah' => ['is_active' => 1]], json_decode((string) $log[0]['data_json'], true));
    }

    public function test_reset_password_mengganti_hash_dan_membuka_kunci(): void
    {
        $id = $this->makeUser('siti');
        $this->db->execute('UPDATE users SET failed_attempts = 5, locked_until = ? WHERE id = ?', [
            date('Y-m-d H:i:s', time() + 600), $id,
        ]);

        $this->service->resetPassword($id, ['password' => 'Sandi-Reset-0003', 'password_konfirmasi' => 'Sandi-Reset-0003']);

        $row = $this->userRow($id);
        self::assertTrue(password_verify('Sandi-Reset-0003', (string) $row['password_hash']));
        self::assertSame(0, (int) $row['failed_attempts']);
        self::assertNull($row['locked_until']);
        $log = $this->auditRows('user.reset_password');
        self::assertCount(1, $log);
        self::assertNull($log[0]['data_json']);
    }

    public function test_daftar_memfilter_dan_paginasi_25(): void
    {
        $this->makeUser('admin1', 'admin');
        for ($i = 1; $i <= 26; $i++) {
            $this->db->execute(
                "INSERT INTO users (username, nama, password_hash, role, is_active, created_at, updated_at)
                 VALUES (?, ?, 'x', 'operator', ?, NOW(), NOW())",
                [sprintf('op%02d', $i), sprintf('Operator %02d', $i), $i === 26 ? 0 : 1]
            );
        }

        $hal1 = $this->service->list([]);
        self::assertSame(27, $hal1['total']);
        self::assertCount(25, $hal1['rows']);
        self::assertSame(2, $hal1['pages']);
        self::assertCount(2, $this->service->list(['page' => '2'])['rows']);

        self::assertSame(1, $this->service->list(['role' => 'admin'])['total']);
        self::assertSame(1, $this->service->list(['status' => 'nonaktif'])['total']);
        self::assertSame(1, $this->service->list(['q' => 'op07'])['total']);
        self::assertSame(0, $this->service->list(['q' => '100%_'])['total']);
        self::assertSame(27, $this->service->list(['role' => 'tidak-valid'])['total'], 'Filter tak valid diabaikan');
    }
}
