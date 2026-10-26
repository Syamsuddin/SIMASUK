<?php

declare(strict_types=1);

namespace Modules\Disposisi\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\DomainRuleException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Disposisi\Repository\DisposisiRepository;
use Modules\Master\Service\UnitKerjaService;

/** Disposisi berjenjang yang dicatat Operator dari lembar kertas pimpinan, dan penutupan surat (docs/06 P2). */
final class DisposisiService
{
    public const STATUS = ['dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
    /** Prefiks field checkbox unit tujuan ("tujuan_<id>"), skalar agar isian lama tetap terpilih saat validasi gagal. */
    public const PREFIX_TUJUAN = 'tujuan_';
    public const CATATAN_TUTUP_SURAT = 'Ditutup bersama surat';

    public const PESAN_SURAT_TERTUTUP = 'Surat sudah selesai atau dibatalkan; disposisi tidak dapat dicatat atau diubah.';
    public const PESAN_SURAT_SUDAH_TERTUTUP = 'Surat ini sudah selesai atau dibatalkan.';
    public const PESAN_TENGGAT = 'Tenggat tidak boleh sebelum tanggal terima surat (:tgl).';
    public const PESAN_TUJUAN_WAJIB = 'Pilih minimal satu unit tujuan.';
    public const PESAN_INDUK = 'Disposisi induk tidak ditemukan pada surat ini.';
    public const PESAN_SUDAH_SELESAI = 'Disposisi ini sudah ditandai selesai.';

    private const RULES = [
        'tgl_disposisi' => 'required|date',
        'instruksi' => 'required|max:500',
        'catatan' => 'nullable|max:500',
        'tenggat' => 'nullable|date',
        'parent_id' => 'nullable|int',
    ];
    private const SELESAI_RULES = ['catatan_selesai' => 'nullable|max:500'];
    private const STATUS_TERBUKA = ['diterima', 'didisposisi'];

    public function __construct(
        private readonly DisposisiRepository $repo,
        private readonly SuratMasukRepository $surat,
        private readonly UnitKerjaService $unit,
        private readonly Audit $audit,
        private readonly Database $db
    ) {
    }

    /** Disposisi hanya boleh dicatat/diubah selama surat belum Selesai atau Dibatalkan. @param array<string,mixed> $surat */
    public static function terbuka(array $surat): bool
    {
        return in_array($surat['status'], self::STATUS_TERBUKA, true);
    }

    /** Jumlah disposisi berstatus Dikirim di seluruh jenjang pohon. @param list<array<string,mixed>> $nodes */
    public static function jumlahDikirim(array $nodes): int
    {
        $total = 0;
        foreach ($nodes as $node) {
            $total += ($node['status'] === 'dikirim' ? 1 : 0) + self::jumlahDikirim($node['children']);
        }
        return $total;
    }

    /**
     * Pohon disposisi satu surat: tiap simpul membawa kunci 'children' berisi lanjutannya.
     *
     * @return list<array<string,mixed>>
     */
    public function tree(int $suratId): array
    {
        $byParent = [];
        foreach ($this->repo->listBySurat($suratId) as $row) {
            $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
        }
        return self::branch($byParent, 0);
    }

    /**
     * Data formulir catat disposisi. Induk yang bukan milik surat ini dianggap tidak ada.
     *
     * @return array{surat:array<string,mixed>,induk:array<string,mixed>|null,units:list<array<string,mixed>>}
     */
    public function formContext(int $suratId, ?int $indukId): array
    {
        $surat = $this->findSuratOrFail($suratId);
        if (!self::terbuka($surat)) {
            throw new DomainRuleException(self::PESAN_SURAT_TERTUTUP);
        }
        $induk = null;
        if ($indukId !== null) {
            $induk = $this->repo->findById($indukId);
            if ($induk === null || (int) $induk['surat_id'] !== $suratId) {
                throw new HttpException(404);
            }
        }
        return ['surat' => $surat, 'induk' => $induk, 'units' => $this->unit->pilihanTujuan()];
    }

    /**
     * Satu baris disposisi per unit tujuan, berstatus Dikirim. Surat yang masih Diterima menjadi Didisposisi.
     *
     * @param array<string,mixed> $input
     * @return list<int> id disposisi baru
     */
    public function create(int $suratId, array $input, ?int $userId): array
    {
        $surat = $this->findSuratOrFail($suratId);
        if (!self::terbuka($surat)) {
            throw new DomainRuleException(self::PESAN_SURAT_TERTUTUP);
        }

        $errors = [];
        try {
            $data = Validator::validate($input, self::RULES);
        } catch (ValidationException $e) {
            $errors += $e->errors();
            $data = [];
        }
        $tenggat = $data['tenggat'] ?? null;
        if ($tenggat !== null && $tenggat < $surat['tgl_terima']) {
            $errors['tenggat'] = str_replace(':tgl', tgl((string) $surat['tgl_terima']), self::PESAN_TENGGAT);
        }
        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            $parent = $this->repo->findById((int) $parentId);
            if ($parent === null || (int) $parent['surat_id'] !== $suratId) {
                $errors['parent_id'] = self::PESAN_INDUK;
            }
        }
        $unitIds = $this->selectedUnits($input, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->db->transaction(function () use ($suratId, $data, $unitIds, $userId): array {
            $locked = $this->surat->lockForUpdate($suratId);
            if ($locked === null || !self::terbuka($locked)) {
                throw new DomainRuleException(self::PESAN_SURAT_TERTUTUP);
            }
            $now = date('Y-m-d H:i:s');
            $ids = [];
            foreach ($unitIds as $unitId) {
                $row = [
                    'surat_id' => $suratId,
                    'parent_id' => $data['parent_id'] === null ? null : (int) $data['parent_id'],
                    'unit_tujuan_id' => $unitId,
                    'tgl_disposisi' => (string) $data['tgl_disposisi'],
                    'instruksi' => (string) $data['instruksi'],
                    'catatan' => $data['catatan'] === null ? null : (string) $data['catatan'],
                    'tenggat' => $data['tenggat'] === null ? null : (string) $data['tenggat'],
                    'status' => 'dikirim',
                    'selesai_at' => null,
                    'catatan_selesai' => null,
                    'created_by' => $userId,
                ];
                $id = $this->repo->insert($row, $now);
                $this->audit->record('disposisi.create', 'disposisi', $id, ['sesudah' => $row]);
                $ids[] = $id;
            }
            if ($locked['status'] === 'diterima') {
                $this->surat->updateStatus($suratId, 'didisposisi', $userId, $now);
                $this->audit->record('surat.update', 'surat_masuk', $suratId, [
                    'sebelum' => ['status' => 'diterima'],
                    'sesudah' => ['status' => 'didisposisi'],
                ]);
            }
            return $ids;
        });
    }

    /**
     * Menandai satu disposisi Selesai saat tindak lanjut dilaporkan.
     *
     * @param array<string,mixed> $input
     * @return int id surat pemilik disposisi
     */
    public function markDone(int $disposisiId, array $input): int
    {
        $row = $this->repo->findById($disposisiId);
        if ($row === null) {
            throw new HttpException(404);
        }
        $suratId = (int) $row['surat_id'];
        $data = Validator::validate($input, self::SELESAI_RULES);

        $this->db->transaction(function () use ($disposisiId, $suratId, $data): void {
            $locked = $this->surat->lockForUpdate($suratId);
            if ($locked === null || !self::terbuka($locked)) {
                throw new DomainRuleException(self::PESAN_SURAT_TERTUTUP);
            }
            $catatan = $data['catatan_selesai'] === null ? null : (string) $data['catatan_selesai'];
            if ($this->repo->markDone($disposisiId, $catatan, date('Y-m-d H:i:s')) === 0) {
                throw new DomainRuleException(self::PESAN_SUDAH_SELESAI);
            }
            $this->audit->record('disposisi.selesai', 'disposisi', $disposisiId, [
                'sebelum' => ['status' => 'dikirim'],
                'sesudah' => ['status' => 'selesai', 'catatan_selesai' => $catatan],
            ]);
        });
        return $suratId;
    }

    /**
     * Menandai surat Selesai; disposisi yang masih Dikirim ikut ditutup dengan catatan otomatis.
     *
     * @return int jumlah disposisi yang ikut ditutup
     */
    public function closeSurat(int $suratId, ?int $userId): int
    {
        $surat = $this->findSuratOrFail($suratId);
        if (!self::terbuka($surat)) {
            throw new DomainRuleException(self::PESAN_SURAT_SUDAH_TERTUTUP);
        }

        return $this->db->transaction(function () use ($suratId, $userId): int {
            $locked = $this->surat->lockForUpdate($suratId);
            if ($locked === null || !self::terbuka($locked)) {
                throw new DomainRuleException(self::PESAN_SURAT_SUDAH_TERTUTUP);
            }
            $now = date('Y-m-d H:i:s');
            $openIds = $this->repo->openIdsBySurat($suratId);
            $this->repo->closeOpenBySurat($suratId, self::CATATAN_TUTUP_SURAT, $now);
            $this->surat->updateStatus($suratId, 'selesai', $userId, $now);
            $this->audit->record('surat.selesai', 'surat_masuk', $suratId, [
                'sebelum' => ['status' => $locked['status']],
                'sesudah' => ['status' => 'selesai', 'disposisi_ditutup' => $openIds],
            ]);
            return count($openIds);
        });
    }

    /** @return array<string,mixed> */
    private function findSuratOrFail(int $suratId): array
    {
        $surat = $this->surat->findById($suratId);
        if ($surat === null) {
            throw new HttpException(404);
        }
        return $surat;
    }

    /**
     * Unit tujuan yang dicentang. Hanya unit aktif yang sah (docs/06 P2: unit nonaktif tidak muncul di pilihan).
     *
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     * @return list<int>
     */
    private function selectedUnits(array $input, array &$errors): array
    {
        $rules = [];
        foreach ($this->unit->pilihanTujuan() as $unit) {
            $rules[self::PREFIX_TUJUAN . (int) $unit['id']] = 'accepted';
        }
        // Centang untuk unit yang tak (lagi) aktif ditolak, bukan diabaikan: unit bisa dinonaktifkan Admin
        // saat formulir masih terbuka, dan disposisi tak boleh tersimpan tanpa salah satu tujuannya.
        foreach (array_keys($input) as $key) {
            if (is_string($key) && str_starts_with($key, self::PREFIX_TUJUAN) && !isset($rules[$key])) {
                $errors['tujuan'] = Validator::MSG_IN;
                return [];
            }
        }
        try {
            $checked = Validator::validate($input, $rules);
        } catch (ValidationException) {
            $errors['tujuan'] = Validator::MSG_IN;
            return [];
        }
        $ids = [];
        foreach ($checked as $key => $isChecked) {
            if ($isChecked === true) {
                $ids[] = (int) substr($key, strlen(self::PREFIX_TUJUAN));
            }
        }
        if ($ids === []) {
            $errors['tujuan'] = self::PESAN_TUJUAN_WAJIB;
        }
        sort($ids);
        return $ids;
    }

    /**
     * @param array<int,list<array<string,mixed>>> $byParent
     * @return list<array<string,mixed>>
     */
    private static function branch(array $byParent, int $parentId): array
    {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $row) {
            $row['children'] = self::branch($byParent, (int) $row['id']);
            $nodes[] = $row;
        }
        return $nodes;
    }
}
