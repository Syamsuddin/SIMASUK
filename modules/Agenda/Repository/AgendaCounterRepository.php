<?php

declare(strict_types=1);

namespace Modules\Agenda\Repository;

use App\Core\Database;

/** Hanya dipakai AgendaNumberService, satu-satunya penulis agenda_counter (docs/12, docs/20 #4). */
class AgendaCounterRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Membuat baris tahun bila belum ada; ON DUPLICATE KEY ikut mengunci baris yang sudah ada. */
    public function ensureYear(int $tahun): void
    {
        $this->db->execute(
            'INSERT INTO agenda_counter (tahun, last_no) VALUES (?, 0) ON DUPLICATE KEY UPDATE last_no = last_no',
            [$tahun]
        );
    }

    public function lockLastNo(int $tahun): int
    {
        return (int) $this->db->fetchValue('SELECT last_no FROM agenda_counter WHERE tahun = ? FOR UPDATE', [$tahun]);
    }

    public function setLastNo(int $tahun, int $lastNo): void
    {
        $this->db->execute('UPDATE agenda_counter SET last_no = ? WHERE tahun = ?', [$lastNo, $tahun]);
    }
}
