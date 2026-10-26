<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Env;
use Modules\Audit\Repository\AuditRepository;
use PHPUnit\Framework\TestCase;

/**
 * Basis tes integrasi: PDO nyata ke DB_TEST_NAME, seluruh tabel dikosongkan tiap setUp() (docs/11, docs/13).
 */
abstract class IntegrationTestCase extends TestCase
{
    private const TABLES = [
        'log_audit', 'disposisi', 'berkas_surat', 'surat_masuk', 'agenda_counter',
        'klasifikasi_arsip', 'unit_kerja', 'users',
    ];

    protected Database $db;
    protected Audit $audit;

    protected function setUp(): void
    {
        $testName = (string) Env::get('DB_TEST_NAME', '');
        if ($testName === '' || $testName === Env::get('DB_NAME')) {
            self::fail('DB_TEST_NAME wajib diisi dan berbeda dari DB_NAME; tes tidak boleh menyentuh DB utama.');
        }
        $this->db = Database::fromEnv(true);

        $migrations = count(glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: []);
        $applied = (int) $this->db->fetchValue('SELECT COUNT(*) FROM schema_migrations');
        if ($applied !== $migrations) {
            self::fail("DB uji belum dimigrasi ({$applied}/{$migrations}). Jalankan: php scripts/migrate.php --env=testing");
        }

        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->db->pdo()->exec('TRUNCATE TABLE ' . $table);
        }
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->audit = new Audit(new AuditRepository($this->db));
        $this->audit->setContext(null, '127.0.0.1');
    }

    /** Membuat pengguna langsung di DB uji; mengembalikan id. */
    protected function makeUser(string $username, string $role = 'operator', bool $active = true, string $plain = 'Sandi-Uji-0001'): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO users (username, nama, password_hash, role, is_active, failed_attempts, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
            [$username, ucfirst($username), password_hash($plain, PASSWORD_ARGON2ID), $role, $active ? 1 : 0, $now, $now]
        );
        return $this->db->lastInsertId();
    }

    /** @return array<string,mixed> */
    protected function userRow(int $id): array
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
        self::assertNotNull($row);
        return $row;
    }

    /** @return list<array<string,mixed>> */
    protected function auditRows(string $aksi): array
    {
        return $this->db->fetchAll('SELECT * FROM log_audit WHERE aksi = ? ORDER BY id', [$aksi]);
    }
}
