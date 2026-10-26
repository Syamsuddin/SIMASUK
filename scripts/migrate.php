<?php

declare(strict_types=1);

/*
 * Runner migrasi maju-saja (docs/08, docs/11).
 *   php scripts/migrate.php               jalankan migrasi tertunda pada DB_NAME
 *   php scripts/migrate.php --status      tampilkan status
 *   php scripts/migrate.php --env=testing jalankan pada DB_TEST_NAME
 */

use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
date_default_timezone_set('Asia/Makassar');

$args = array_slice($argv, 1);
$testing = in_array('--env=testing', $args, true);
$statusOnly = in_array('--status', $args, true);

$db = Database::fromEnv($testing);
$dbName = (string) ($testing ? Env::get('DB_TEST_NAME') : Env::get('DB_NAME'));

$db->pdo()->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = array_column($db->fetchAll('SELECT migration FROM schema_migrations'), 'migration');
$files = glob($root . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

if ($statusOnly) {
    fwrite(STDOUT, "Basis data: {$dbName}\n");
    foreach ($files as $file) {
        $name = basename($file);
        $mark = in_array($name, $applied, true) ? 'sudah' : 'TERTUNDA';
        fwrite(STDOUT, sprintf("  [%s] %s\n", $mark, $name));
    }
    exit(0);
}

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    $sql = trim((string) file_get_contents($file));
    if ($sql === '') {
        fwrite(STDERR, "Migrasi kosong: {$name}\n");
        exit(1);
    }
    try {
        // DDL MySQL melakukan commit implisit; satu berkas = satu perubahan skema (docs/16).
        $db->pdo()->exec($sql);
        $db->execute(
            'INSERT INTO schema_migrations (migration, applied_at) VALUES (?, ?)',
            [$name, date('Y-m-d H:i:s')]
        );
    } catch (\PDOException $e) {
        fwrite(STDERR, "GAGAL pada {$name}: {$e->getMessage()}\n");
        exit(1);
    }
    fwrite(STDOUT, "  dijalankan: {$name}\n");
    $ran++;
}

fwrite(STDOUT, "Basis data {$dbName}: {$ran} migrasi dijalankan.\n");
