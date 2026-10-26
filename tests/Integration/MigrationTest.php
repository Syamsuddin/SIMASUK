<?php

declare(strict_types=1);

namespace Tests\Integration;

/** Skema DB uji sesuai docs/07 untuk constraint yang dijaga DB, bukan hanya aplikasi. */
final class MigrationTest extends IntegrationTestCase
{
    public function test_seluruh_tabel_docs07_ada(): void
    {
        $tables = array_column($this->db->fetchAll(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'
        ), 't');

        foreach (['users', 'unit_kerja', 'klasifikasi_arsip', 'agenda_counter', 'surat_masuk', 'berkas_surat', 'disposisi', 'log_audit'] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function test_username_unik_di_db(): void
    {
        $this->makeUser('siti');
        $this->expectException(\PDOException::class);
        $this->makeUser('siti');
    }

    public function test_surat_menolak_tgl_surat_setelah_tgl_terima_dan_nomor_agenda_ganda(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute("INSERT INTO klasifikasi_arsip (kode, uraian, created_at, updated_at) VALUES ('800', 'Uji', ?, ?)", [$now, $now]);
        $klas = $this->db->lastInsertId();
        $sql = 'INSERT INTO surat_masuk (tahun, no_agenda, tgl_terima, no_surat, tgl_surat, asal, perihal, klasifikasi_id, created_at, updated_at)
                VALUES (2026, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $this->db->execute($sql, [1, '2026-10-26', '1/X', '2026-10-25', 'Asal', 'Perihal', $klas, $now, $now]);

        try {
            $this->db->execute($sql, [2, '2026-10-26', '2/X', '2026-10-27', 'Asal', 'Perihal', $klas, $now, $now]);
            self::fail('CHECK tgl_surat <= tgl_terima seharusnya menolak');
        } catch (\PDOException $e) {
            self::assertStringContainsString('chk_surat_tgl', $e->getMessage());
        }

        $this->expectException(\PDOException::class);
        $this->db->execute($sql, [1, '2026-10-26', '3/X', '2026-10-25', 'Asal', 'Perihal', $klas, $now, $now]);
    }

    public function test_zona_waktu_sesi_mysql_wita(): void
    {
        self::assertSame('+08:00', $this->db->fetchValue('SELECT @@session.time_zone'));
        self::assertSame('Asia/Makassar', date_default_timezone_get());
    }
}
