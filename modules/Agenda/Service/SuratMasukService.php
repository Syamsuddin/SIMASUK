<?php

declare(strict_types=1);

namespace Modules\Agenda\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\DomainRuleException;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Agenda\Repository\BerkasSuratRepository;
use Modules\Agenda\Repository\SuratMasukRepository;
use Modules\Master\Service\KlasifikasiService;

/** Pencatatan, perubahan, dan pembatalan surat masuk (docs/06 P1, P3). */
final class SuratMasukService
{
    public const PER_PAGE = 25;
    public const SIFAT = ['biasa' => 'Biasa', 'segera' => 'Segera', 'sangat_segera' => 'Sangat Segera', 'rahasia' => 'Rahasia'];
    public const STATUS = ['diterima' => 'Diterima', 'didisposisi' => 'Didisposisi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    public const PESAN_TGL_SURAT = 'Tanggal surat tidak boleh setelah tanggal terima.';
    public const PESAN_TGL_TERIMA_DEPAN = 'Tanggal terima tidak boleh melewati hari ini.';
    public const PESAN_TAHUN_TETAP = 'Tanggal terima harus tetap di tahun agenda :tahun.';
    public const PESAN_GANDA = 'Surat dengan nomor, asal, dan tanggal surat yang sama sudah tercatat. '
        . 'Periksa surat tersebut; centang "Tetap simpan" bila memang surat yang berbeda.';
    public const PESAN_SUDAH_BATAL = 'Surat ini sudah dibatalkan.';
    public const PESAN_BATAL_TAK_DIUBAH = 'Surat yang dibatalkan tidak dapat diubah.';

    private const RULES = [
        'tgl_terima' => 'required|date',
        'no_surat' => 'required|max:100',
        'tgl_surat' => 'required|date',
        'asal' => 'required|max:200',
        'perihal' => 'required|max:500',
        'sifat' => 'required|in:biasa,segera,sangat_segera,rahasia',
        'klasifikasi_id' => 'required|int',
        'no_srikandi' => 'nullable|max:100',
        'jumlah_lampiran' => 'nullable|max:50',
        'tetap_simpan' => 'accepted',
    ];
    private const CANCEL_RULES = ['alasan' => 'required|max:255'];
    private const FILTER_RULES = [
        'q' => 'nullable|max:100',
        'tahun' => 'nullable|int',
        'status' => 'nullable|in:diterima,didisposisi,selesai,dibatalkan',
        'sifat' => 'nullable|in:biasa,segera,sangat_segera,rahasia',
        'dari' => 'nullable|date',
        'sampai' => 'nullable|date',
        'page' => 'nullable|int',
    ];

    public function __construct(
        private readonly SuratMasukRepository $repo,
        private readonly BerkasSuratRepository $berkas,
        private readonly AgendaNumberService $numbers,
        private readonly KlasifikasiService $klasifikasi,
        private readonly FileStore $files,
        private readonly Audit $audit,
        private readonly Database $db
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>,
     *   errors:array<string,string>,years:list<int>}
     */
    public function list(array $query): array
    {
        [$f, $errors] = Validator::filter($query, self::FILTER_RULES);
        $filter = [
            'q' => (string) ($f['q'] ?? ''),
            'tahun' => (string) ($f['tahun'] ?? ''),
            'status' => (string) ($f['status'] ?? ''),
            'sifat' => (string) ($f['sifat'] ?? ''),
            'dari' => (string) ($f['dari'] ?? ''),
            'sampai' => (string) ($f['sampai'] ?? ''),
        ];
        $page = max(1, (int) ($f['page'] ?? 1));
        $result = $this->repo->paginate($filter, $page, self::PER_PAGE);
        return [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'filter' => $filter,
            'errors' => $errors,
            'years' => $this->repo->years(),
        ];
    }

    /** @return array<string,mixed> */
    public function findOrFail(int $id): array
    {
        $row = $this->repo->findById($id);
        if ($row === null) {
            throw new HttpException(404);
        }
        return $row;
    }

    /** @return array{surat:array<string,mixed>,berkas:list<array<string,mixed>>} */
    public function detail(int $id): array
    {
        return ['surat' => $this->findOrFail($id), 'berkas' => $this->berkas->listActive($id)];
    }

    /**
     * Pilihan klasifikasi formulir: yang aktif, ditambah klasifikasi surat saat ini walau sudah nonaktif.
     *
     * @param array<string,mixed>|null $current
     * @return array<int,string>
     */
    public function klasifikasiOptions(?array $current = null): array
    {
        $options = [];
        foreach ($this->klasifikasi->activeOptions() as $row) {
            $options[(int) $row['id']] = $row['kode'] . ' — ' . $row['uraian'];
        }
        if ($current !== null && !isset($options[(int) $current['klasifikasi_id']])) {
            $options[(int) $current['klasifikasi_id']] = $current['klasifikasi_kode'] . ' — ' . $current['klasifikasi_uraian'];
        }
        return $options;
    }

    /**
     * Surat yang diduga sama dengan isian formulir, untuk tautan pada peringatan dugaan ganda.
     *
     * @param array<string,mixed> $input
     * @return list<array<string,mixed>>
     */
    public function duplicatesFor(array $input, ?int $exceptId = null): array
    {
        $noSurat = self::raw($input, 'no_surat');
        $asal = self::raw($input, 'asal');
        $tglSurat = self::raw($input, 'tgl_surat');
        if ($noSurat === '' || $asal === '' || !Validator::isDate($tglSurat)) {
            return [];
        }
        return $this->repo->findDuplicates($noSurat, $asal, $tglSurat, $exceptId);
    }

    /**
     * Mencatat surat baru beserta 1–5 berkas PDF. Nomor agenda, baris surat, berkas, dan log audit
     * berada dalam satu transaksi: kegagalan apa pun tidak memakai nomor dan tidak meninggalkan berkas.
     *
     * @param array<string,mixed> $input
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $uploads
     */
    public function create(array $input, array $uploads, ?int $userId): int
    {
        $errors = [];
        $data = $this->validated($input, null, $errors);
        try {
            $this->files->validatePdfs($uploads);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->guardDuplicate($data, null);

        return $this->db->transaction(function () use ($data, $uploads, $userId): int {
            $tahun = (int) substr((string) $data['tgl_terima'], 0, 4);
            $noAgenda = $this->numbers->next($tahun);
            $now = date('Y-m-d H:i:s');
            $id = $this->repo->insert([
                ...self::columns($data),
                'tahun' => $tahun,
                'no_agenda' => $noAgenda,
                'status' => 'diterima',
                'cancelled_at' => null,
                'cancel_reason' => null,
                'created_by' => $userId,
            ], $now);

            $stored = [];
            foreach ($uploads as $upload) {
                $file = $this->files->storePdf($upload, $tahun, $this->db);
                $this->berkas->insert($id, $file['path'], $file['nama_asli'], $file['ukuran'], $file['sha256'], $userId, $now);
                $stored[] = ['nama_asli' => $file['nama_asli'], 'ukuran' => $file['ukuran'], 'sha256' => $file['sha256']];
            }

            $this->audit->record('surat.create', 'surat_masuk', $id, [
                'sesudah' => [...self::columns($data), 'tahun' => $tahun, 'no_agenda' => $noAgenda, 'berkas' => $stored],
            ]);
            return $id;
        });
    }

    /** Ubah metadata; nomor agenda dan tahun tidak pernah berubah (docs/05). @param array<string,mixed> $input */
    public function update(int $id, array $input, ?int $userId): void
    {
        $before = $this->findOrFail($id);
        if ($before['status'] === 'dibatalkan') {
            throw new DomainRuleException(self::PESAN_BATAL_TAK_DIUBAH);
        }
        $errors = [];
        $data = $this->validated($input, $before, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->guardDuplicate($data, $id);

        $this->db->transaction(function () use ($id, $data, $before, $userId): void {
            $this->repo->update($id, self::columns($data), $userId, date('Y-m-d H:i:s'));
            $sesudah = self::columns($data);
            $sebelum = array_intersect_key($before, $sesudah);
            $this->audit->record('surat.update', 'surat_masuk', $id, ['sebelum' => $sebelum, 'sesudah' => $sesudah]);
        });
    }

    /** Pembatalan oleh Admin: status Dibatalkan, nomor agenda tidak dipakai ulang (docs/06 P3). @param array<string,mixed> $input */
    public function cancel(int $id, array $input, ?int $userId): void
    {
        $before = $this->findOrFail($id);
        if ($before['status'] === 'dibatalkan') {
            throw new DomainRuleException(self::PESAN_SUDAH_BATAL);
        }
        $data = Validator::validate($input, self::CANCEL_RULES);
        $this->db->transaction(function () use ($id, $data, $before, $userId): void {
            $this->repo->cancel($id, (string) $data['alasan'], $userId, date('Y-m-d H:i:s'));
            $this->audit->record('surat.cancel', 'surat_masuk', $id, [
                'sebelum' => ['status' => $before['status']],
                'sesudah' => ['status' => 'dibatalkan', 'cancel_reason' => $data['alasan']],
            ]);
        });
    }

    /**
     * Berkas untuk diunduh. Berkas yang sudah dilepas hanya terlihat oleh Admin (docs/21).
     *
     * @return array{isi:string,nama_asli:string}
     */
    public function berkasForDownload(int $berkasId, string $role): array
    {
        $row = $this->berkas->findById($berkasId);
        if ($row === null || ($row['deleted_at'] !== null && $role !== 'admin')) {
            throw new HttpException(404);
        }
        return ['isi' => $this->files->read((string) $row['path']), 'nama_asli' => (string) $row['nama_asli']];
    }

    public function uploadLimitMb(): int
    {
        return $this->files->maxMb();
    }

    /** @param array<string,mixed> $surat */
    public static function nomor(array $surat): string
    {
        return AgendaNumberService::format((int) $surat['no_agenda'], (int) $surat['tahun']);
    }

    /**
     * Validasi field + aturan lintas field. Pesan dikumpulkan ke $errors agar formulir menampilkan
     * semua masalah sekaligus.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $current surat yang diubah; null = surat baru
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    private function validated(array $input, ?array $current, array &$errors): array
    {
        try {
            $data = Validator::validate($input, self::RULES);
        } catch (ValidationException $e) {
            $errors += $e->errors();
            $data = [];
        }

        $terima = self::raw($input, 'tgl_terima');
        $surat = self::raw($input, 'tgl_surat');
        if (!isset($errors['tgl_terima']) && Validator::isDate($terima)) {
            if ($terima > date('Y-m-d')) {
                $errors['tgl_terima'] = self::PESAN_TGL_TERIMA_DEPAN;
            } elseif ($current !== null && (int) substr($terima, 0, 4) !== (int) $current['tahun']) {
                $errors['tgl_terima'] = str_replace(':tahun', (string) $current['tahun'], self::PESAN_TAHUN_TETAP);
            }
        }
        if (!isset($errors['tgl_surat']) && Validator::isDate($terima) && Validator::isDate($surat) && $surat > $terima) {
            $errors['tgl_surat'] = self::PESAN_TGL_SURAT;
        }
        if (!isset($errors['klasifikasi_id'])
            && !array_key_exists((int) self::raw($input, 'klasifikasi_id'), $this->klasifikasiOptions($current))) {
            $errors['klasifikasi_id'] = Validator::MSG_IN;
        }
        return $data;
    }

    /** @param array<string,mixed> $data */
    private function guardDuplicate(array $data, ?int $exceptId): void
    {
        if ($data['tetap_simpan'] === true) {
            return;
        }
        $dupes = $this->repo->findDuplicates((string) $data['no_surat'], (string) $data['asal'], (string) $data['tgl_surat'], $exceptId);
        if ($dupes !== []) {
            throw new ValidationException(['tetap_simpan' => self::PESAN_GANDA]);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{tgl_terima:string,no_surat:string,tgl_surat:string,asal:string,perihal:string,sifat:string,
     *   klasifikasi_id:int,no_srikandi:?string,jumlah_lampiran:?string}
     */
    private static function columns(array $data): array
    {
        return [
            'tgl_terima' => (string) $data['tgl_terima'],
            'no_surat' => (string) $data['no_surat'],
            'tgl_surat' => (string) $data['tgl_surat'],
            'asal' => (string) $data['asal'],
            'perihal' => (string) $data['perihal'],
            'sifat' => (string) $data['sifat'],
            'klasifikasi_id' => (int) $data['klasifikasi_id'],
            'no_srikandi' => $data['no_srikandi'] === null ? null : (string) $data['no_srikandi'],
            'jumlah_lampiran' => $data['jumlah_lampiran'] === null ? null : (string) $data['jumlah_lampiran'],
        ];
    }

    /** @param array<string,mixed> $input */
    private static function raw(array $input, string $key): string
    {
        return is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
    }
}
