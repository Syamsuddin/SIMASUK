<?php

declare(strict_types=1);

/*
 * Seeder awal (docs/10, docs/11): satu admin dari SEED_ADMIN_USERNAME/SEED_ADMIN_PASSWORD
 * dan contoh unit kerja. Menolak berjalan di produksi bila tabel users sudah berisi.
 */

use App\Core\Audit;
use App\Core\Database;
use App\Core\Env;
use App\Core\ValidationException;
use Modules\Audit\Repository\AuditRepository;
use Modules\Master\Repository\UnitKerjaRepository;
use Modules\Master\Service\UnitKerjaService;
use Modules\Pengguna\Repository\PenggunaRepository;
use Modules\Pengguna\Service\PenggunaService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
date_default_timezone_set('Asia/Makassar');

$db = Database::fromEnv();
$audit = new Audit(new AuditRepository($db));
$userRepo = new PenggunaRepository($db);
$unitRepo = new UnitKerjaRepository($db);

if (Env::get('APP_ENV') === 'production' && $userRepo->count() > 0) {
    fwrite(STDERR, "Ditolak: APP_ENV=production dan tabel users sudah berisi.\n");
    exit(1);
}

$username = mb_strtolower((string) Env::get('SEED_ADMIN_USERNAME', ''));
$password = (string) Env::get('SEED_ADMIN_PASSWORD', '');

if ($userRepo->usernameExists($username)) {
    fwrite(STDOUT, "Admin '{$username}' sudah ada, dilewati.\n");
} else {
    try {
        (new PenggunaService($userRepo, $audit, $db))->create([
            'username' => $username,
            'nama' => 'Administrator',
            'role' => 'admin',
            'password' => $password,
            'password_konfirmasi' => $password,
        ]);
    } catch (ValidationException $e) {
        foreach ($e->errors() as $field => $message) {
            fwrite(STDERR, "SEED_ADMIN ({$field}): {$message}\n");
        }
        exit(1);
    }
    fwrite(STDOUT, "Admin '{$username}' dibuat.\n");
}

if ($unitRepo->count() > 0) {
    fwrite(STDOUT, "Unit kerja sudah berisi, contoh dilewati.\n");
    exit(0);
}

// Contoh struktur; Admin menyesuaikannya dengan SOTK BKPSDM yang berlaku lewat menu Unit Kerja.
$units = new UnitKerjaService($unitRepo, $audit, $db);
$kepala = $units->create(['nama' => 'Kepala Badan', 'jabatan_pimpinan' => 'Kepala BKPSDM', 'urutan' => '1']);
$sekretariat = $units->create([
    'nama' => 'Sekretariat', 'jabatan_pimpinan' => 'Sekretaris', 'parent_id' => (string) $kepala, 'urutan' => '1',
]);
$units->create([
    'nama' => 'Subbagian Umum dan Kepegawaian', 'jabatan_pimpinan' => 'Kepala Subbagian Umum dan Kepegawaian',
    'parent_id' => (string) $sekretariat, 'urutan' => '1',
]);
$units->create([
    'nama' => 'Subbagian Perencanaan dan Keuangan', 'jabatan_pimpinan' => 'Kepala Subbagian Perencanaan dan Keuangan',
    'parent_id' => (string) $sekretariat, 'urutan' => '2',
]);
$bidang = [
    'Bidang Pengadaan, Pemberhentian dan Informasi',
    'Bidang Mutasi dan Promosi',
    'Bidang Pengembangan Sumber Daya Manusia',
    'Bidang Penilaian Kinerja Aparatur dan Penghargaan',
];
foreach ($bidang as $i => $nama) {
    $units->create([
        'nama' => $nama,
        'jabatan_pimpinan' => 'Kepala ' . $nama,
        'parent_id' => (string) $kepala,
        'urutan' => (string) ($i + 2),
    ]);
}
fwrite(STDOUT, "Contoh unit kerja dibuat (8 unit).\n");
