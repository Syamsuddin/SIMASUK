<?php

declare(strict_types=1);

namespace Modules\Dasbor\Service;

use Modules\Dasbor\Repository\DasborRepository;

/** Ringkasan kerja harian: surat bulan ini, belum didisposisi, disposisi lewat tenggat (docs/01 F-DASBOR). */
final class DasborService
{
    /** Baris maksimum tiap tabel dasbor; selebihnya dirujuk lewat jumlah di kartu. */
    public const BATAS_TABEL = 25;

    public function __construct(private readonly DasborRepository $repo)
    {
    }

    /**
     * "Lewat tenggat" berarti tenggat sebelum hari ini; tenggat hari ini belum lewat.
     *
     * @param string|null $hari Y-m-d acuan (WITA); null = hari ini
     * @return array{hari:string,bulan_ini:int,belum:array{rows:list<array<string,mixed>>,total:int},
     *   lewat:array{rows:list<array<string,mixed>>,total:int},batas:int}
     */
    public function ringkasan(?string $hari = null): array
    {
        $hari ??= date('Y-m-d');
        $awalBulan = substr($hari, 0, 7) . '-01';
        $akhirBulan = date('Y-m-t', (int) strtotime($awalBulan));

        return [
            'hari' => $hari,
            'bulan_ini' => $this->repo->countSuratDiterimaAntara($awalBulan, $akhirBulan),
            'belum' => [
                'rows' => $this->repo->listBelumDidisposisi(self::BATAS_TABEL),
                'total' => $this->repo->countBelumDidisposisi(),
            ],
            'lewat' => [
                'rows' => $this->repo->listLewatTenggat($hari, self::BATAS_TABEL),
                'total' => $this->repo->countLewatTenggat($hari),
            ],
            'batas' => self::BATAS_TABEL,
        ];
    }

    /** Jumlah hari sejak tenggat terlewati, dihitung dari tanggal kalender. */
    public static function hariLewat(string $tenggat, string $hari): int
    {
        return (int) (new \DateTimeImmutable($tenggat))->diff(new \DateTimeImmutable($hari))->format('%r%a');
    }
}
