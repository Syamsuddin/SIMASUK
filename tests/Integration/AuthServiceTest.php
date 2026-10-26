<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DomainRuleException;
use App\Core\Logger;
use App\Core\ValidationException;
use Modules\Auth\Service\AuthService;
use Modules\Pengguna\Repository\PenggunaRepository;

final class AuthServiceTest extends IntegrationTestCase
{
    private const SANDI = 'Sandi-Uji-0001';

    private AuthService $auth;
    private int $delays = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->delays = 0;
        $this->auth = new AuthService(
            new PenggunaRepository($this->db),
            $this->audit,
            $this->db,
            new Logger(sys_get_temp_dir() . '/simasuk-test-logs'),
            function (): void {
                $this->delays++;
            }
        );
    }

    private function gagalLogin(string $username, string $sandi): string
    {
        try {
            $this->auth->attempt(['username' => $username, 'password' => $sandi]);
        } catch (DomainRuleException $e) {
            return $e->getMessage();
        }
        self::fail('Login seharusnya ditolak');
    }

    public function test_admin_aktif_login_benar_mengisi_last_login_at(): void
    {
        $id = $this->makeUser('admin1', 'admin');

        $user = $this->auth->attempt(['username' => 'ADMIN1', 'password' => self::SANDI]);

        self::assertSame($id, (int) $user['id']);
        $row = $this->userRow($id);
        self::assertNotNull($row['last_login_at']);
        self::assertSame(0, (int) $row['failed_attempts']);
        $log = $this->auditRows('auth.login');
        self::assertCount(1, $log);
        self::assertSame($id, (int) $log[0]['user_id']);
        self::assertSame(0, $this->delays);
    }

    public function test_salah_lima_kali_terkunci_dan_percobaan_keenam_benar_tetap_ditolak(): void
    {
        $id = $this->makeUser('siti');

        for ($i = 1; $i <= 5; $i++) {
            self::assertSame(AuthService::PESAN_GAGAL, $this->gagalLogin('siti', 'salah-' . $i));
        }
        $row = $this->userRow($id);
        self::assertSame(5, (int) $row['failed_attempts']);
        self::assertNotNull($row['locked_until']);
        $menit = (strtotime((string) $row['locked_until']) - time()) / 60;
        self::assertGreaterThan(14, $menit);
        self::assertLessThanOrEqual(15, $menit);
        $lockedSetelahKelima = count($this->auditRows('auth.locked'));
        self::assertSame(1, $lockedSetelahKelima);

        self::assertSame(AuthService::PESAN_GAGAL, $this->gagalLogin('siti', self::SANDI));

        self::assertCount($lockedSetelahKelima + 1, $this->auditRows('auth.locked'));
        self::assertNull($this->userRow($id)['last_login_at']);
        self::assertCount(5, $this->auditRows('auth.login_failed'));
        self::assertSame(6, $this->delays, 'Setiap percobaan gagal diberi jeda');
    }

    public function test_kunci_kedaluwarsa_boleh_login_dan_penghitung_direset(): void
    {
        $id = $this->makeUser('siti');
        $this->db->execute('UPDATE users SET failed_attempts = 5, locked_until = ? WHERE id = ?', [
            date('Y-m-d H:i:s', time() - 60), $id,
        ]);

        $this->auth->attempt(['username' => 'siti', 'password' => self::SANDI]);

        $row = $this->userRow($id);
        self::assertSame(0, (int) $row['failed_attempts']);
        self::assertNull($row['locked_until']);
    }

    public function test_salah_setelah_kunci_kedaluwarsa_mulai_hitung_dari_satu(): void
    {
        $id = $this->makeUser('siti');
        $this->db->execute('UPDATE users SET failed_attempts = 5, locked_until = ? WHERE id = ?', [
            date('Y-m-d H:i:s', time() - 60), $id,
        ]);

        $this->gagalLogin('siti', 'salah');

        $row = $this->userRow($id);
        self::assertSame(1, (int) $row['failed_attempts']);
        self::assertNull($row['locked_until']);
    }

    public function test_pengguna_nonaktif_ditolak_dengan_pesan_sama(): void
    {
        $id = $this->makeUser('lama', 'operator', false);

        self::assertSame(AuthService::PESAN_GAGAL, $this->gagalLogin('lama', self::SANDI));
        self::assertNull($this->userRow($id)['last_login_at']);
        self::assertCount(0, $this->auditRows('auth.login'));
    }

    public function test_username_tak_dikenal_ditolak_dicatat_tanpa_pelaku_dan_tanpa_password(): void
    {
        self::assertSame(AuthService::PESAN_GAGAL, $this->gagalLogin('hantu', 'Rahasia-Sekali-99'));

        $log = $this->auditRows('auth.login_failed');
        self::assertCount(1, $log);
        self::assertNull($log[0]['user_id']);
        self::assertStringNotContainsString('Rahasia-Sekali-99', (string) $log[0]['data_json']);
        self::assertStringNotContainsString('password', (string) $log[0]['data_json']);
    }

    public function test_isian_login_kosong_gagal_validasi(): void
    {
        $this->expectException(ValidationException::class);
        $this->auth->attempt(['username' => '', 'password' => '']);
    }

    public function test_ubah_password_memeriksa_password_lama_dan_minimal_10(): void
    {
        $id = $this->makeUser('siti');

        try {
            $this->auth->changePassword($id, [
                'password_lama' => 'keliru-keliru', 'password_baru' => 'Sandi-Baru-0002', 'password_konfirmasi' => 'Sandi-Baru-0002',
            ]);
            self::fail('Password lama keliru seharusnya ditolak');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('password_lama', $e->errors());
        }

        try {
            $this->auth->changePassword($id, [
                'password_lama' => self::SANDI, 'password_baru' => 'pendek', 'password_konfirmasi' => 'pendek',
            ]);
            self::fail('Password pendek seharusnya ditolak');
        } catch (ValidationException $e) {
            self::assertSame('Minimal 10 karakter.', $e->errors()['password_baru']);
        }

        $this->auth->changePassword($id, [
            'password_lama' => self::SANDI, 'password_baru' => 'Sandi-Baru-0002', 'password_konfirmasi' => 'Sandi-Baru-0002',
        ]);
        $this->auth->attempt(['username' => 'siti', 'password' => 'Sandi-Baru-0002']);
        self::assertCount(1, $this->auditRows('user.password_change'));
    }
}
