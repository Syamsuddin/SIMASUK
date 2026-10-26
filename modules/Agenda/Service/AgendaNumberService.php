<?php

declare(strict_types=1);

namespace Modules\Agenda\Service;

use App\Core\Database;
use Modules\Agenda\Repository\AgendaCounterRepository;

/**
 * Satu-satunya sumber nomor agenda (docs/08). Nomor dikunci per tahun dengan SELECT … FOR UPDATE
 * di dalam transaksi pemanggil, sehingga INSERT surat_masuk dan kenaikan nomor jadi satu kesatuan:
 * bila penyimpanan surat gagal, nomor tidak terpakai. Jangan pernah memakai MAX(no_agenda)+1.
 */
final class AgendaNumberService
{
    public function __construct(
        private readonly AgendaCounterRepository $counter,
        private readonly Database $db
    ) {
    }

    /** @param int $tahun tahun dari tgl_terima, bukan jam server (docs/04) */
    public function next(int $tahun): int
    {
        if (!$this->db->pdo()->inTransaction()) {
            throw new \LogicException('AgendaNumberService::next wajib dipanggil di dalam transaksi pemanggil.');
        }
        if ($tahun < 2000 || $tahun > 2155) {
            throw new \InvalidArgumentException('Tahun agenda di luar rentang.');
        }
        $this->counter->ensureYear($tahun);
        $next = $this->counter->lockLastNo($tahun) + 1;
        $this->counter->setLastNo($tahun, $next);
        return $next;
    }

    /** Tampilan baku NNNN/TAHUN, mis. 0127/2026. */
    public static function format(int $noAgenda, int $tahun): string
    {
        return sprintf('%04d/%d', $noAgenda, $tahun);
    }
}
