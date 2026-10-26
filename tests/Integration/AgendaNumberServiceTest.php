<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use Modules\Agenda\Repository\AgendaCounterRepository;
use Modules\Agenda\Service\AgendaNumberService;

/** Penomoran agenda: urut, reset per tahun tgl_terima, aman dari dua koneksi bersamaan (docs/13). */
final class AgendaNumberServiceTest extends IntegrationTestCase
{
    private const PER_PROSES = 20;

    private AgendaNumberService $numbers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbers = self::service($this->db);
    }

    private static function service(Database $db): AgendaNumberService
    {
        return new AgendaNumberService(new AgendaCounterRepository($db), $db);
    }

    private function next(int $tahun): int
    {
        return $this->db->transaction(fn (): int => $this->numbers->next($tahun));
    }

    private function lastNo(int $tahun): ?int
    {
        $value = $this->db->fetchValue('SELECT last_no FROM agenda_counter WHERE tahun = ?', [$tahun]);
        return $value === null ? null : (int) $value;
    }

    public function test_nomor_pertama_tahun_baru_adalah_1_dan_tampil_0001_tahun(): void
    {
        self::assertSame(1, $this->next(2026));
        self::assertSame('0001/2026', AgendaNumberService::format(1, 2026));
        self::assertSame('0127/2026', AgendaNumberService::format(127, 2026));
        self::assertSame('12345/2026', AgendaNumberService::format(12345, 2026));
    }

    public function test_nomor_urut_naik(): void
    {
        self::assertSame([1, 2, 3], [$this->next(2026), $this->next(2026), $this->next(2026)]);
        self::assertSame(3, $this->lastNo(2026));
    }

    public function test_nomor_agenda_reset_tahun_baru(): void
    {
        $this->db->execute('INSERT INTO agenda_counter (tahun, last_no) VALUES (2025, 412)');
        self::assertSame(1, $this->next(2026));
        self::assertSame(413, $this->next(2025));
        self::assertSame(2, $this->next(2026));
    }

    public function test_rollback_tidak_memakai_nomor(): void
    {
        self::assertSame(1, $this->next(2026));
        try {
            $this->db->transaction(function (): void {
                $this->numbers->next(2026);
                throw new \RuntimeException('simpan gagal');
            });
        } catch (\RuntimeException) {
            self::assertSame(1, $this->lastNo(2026));
        }
        self::assertSame(2, $this->next(2026));
    }

    public function test_wajib_di_dalam_transaksi_dan_tahun_dalam_rentang(): void
    {
        try {
            $this->numbers->next(2026);
            self::fail('Seharusnya LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('transaksi', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->next(1999);
    }

    public function test_baris_tahun_terkunci_sampai_transaksi_pertama_selesai(): void
    {
        $this->db->execute('INSERT INTO agenda_counter (tahun, last_no) VALUES (2026, 0)');
        $other = Database::fromEnv(true);
        $other->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 1');

        $this->db->pdo()->beginTransaction();
        self::assertSame(1, $this->numbers->next(2026));

        $other->pdo()->beginTransaction();
        try {
            self::service($other)->next(2026);
            self::fail('Koneksi kedua seharusnya menunggu kunci baris lalu timeout');
        } catch (\PDOException $e) {
            self::assertSame(1205, $e->errorInfo[1] ?? null, 'Lock wait timeout');
        } finally {
            $other->pdo()->rollBack();
        }

        $this->db->pdo()->commit();
        self::assertSame(2, $other->transaction(fn (): int => self::service($other)->next(2026)));
    }

    public function test_dua_proses_paralel_tidak_menghasilkan_nomor_kembar(): void
    {
        $klasifikasiId = $this->db->transaction(function (): int {
            $now = date('Y-m-d H:i:s');
            $this->db->execute(
                'INSERT INTO klasifikasi_arsip (kode, uraian, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
                ['800', 'Kepegawaian', $now, $now]
            );
            return $this->db->lastInsertId();
        });

        $start = microtime(true) + 0.5;
        $procs = [];
        foreach (['A', 'B'] as $tag) {
            $procs[$tag] = $this->spawnWorker($klasifikasiId, $start, $tag);
        }
        foreach ($procs as $tag => [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($proc), "Proses {$tag} gagal: {$out}");
            self::assertSame('OK', trim($out), "Proses {$tag}: {$out}");
        }

        $total = self::PER_PROSES * 2;
        $stat = $this->db->fetchOne(
            'SELECT COUNT(*) AS n, COUNT(DISTINCT no_agenda) AS unik, MIN(no_agenda) AS min_no, MAX(no_agenda) AS max_no
             FROM surat_masuk WHERE tahun = 2026'
        );
        self::assertSame(
            ['n' => $total, 'unik' => $total, 'min_no' => 1, 'max_no' => $total],
            array_map('intval', (array) $stat)
        );
        self::assertSame($total, $this->lastNo(2026));
    }

    /** @return array{0:resource,1:array<int,resource>} */
    private function spawnWorker(int $klasifikasiId, float $start, string $tag): array
    {
        $code = <<<'PHP'
            [, $root, $klasifikasiId, $start, $tag, $count] = $argv;
            require $root . '/vendor/autoload.php';
            \App\Core\Env::load($root . '/.env');
            date_default_timezone_set('Asia/Makassar');
            $db = \App\Core\Database::fromEnv(true);
            $numbers = new \Modules\Agenda\Service\AgendaNumberService(new \Modules\Agenda\Repository\AgendaCounterRepository($db), $db);
            $repo = new \Modules\Agenda\Repository\SuratMasukRepository($db);
            while (microtime(true) < (float) $start) {
                usleep(1000);
            }
            for ($i = 0; $i < (int) $count; $i++) {
                $db->transaction(function () use ($numbers, $repo, $klasifikasiId, $tag, $i): void {
                    $no = $numbers->next(2026);
                    usleep(2000);
                    $repo->insert([
                        'tahun' => 2026, 'no_agenda' => $no, 'tgl_terima' => '2026-03-02', 'no_surat' => $tag . '-' . $i,
                        'tgl_surat' => '2026-03-01', 'asal' => 'Uji paralel', 'perihal' => 'Uji paralel', 'sifat' => 'biasa',
                        'klasifikasi_id' => (int) $klasifikasiId, 'no_srikandi' => null, 'jumlah_lampiran' => null,
                        'status' => 'diterima', 'cancelled_at' => null, 'cancel_reason' => null, 'created_by' => null,
                    ], date('Y-m-d H:i:s'));
                });
            }
            echo 'OK';
            PHP;
        $proc = proc_open(
            [PHP_BINARY, '-r', $code, '--', dirname(__DIR__, 2), (string) $klasifikasiId, sprintf('%.6F', $start), $tag, (string) self::PER_PROSES],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($proc);
        return [$proc, $pipes];
    }
}
