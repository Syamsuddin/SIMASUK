<?php

declare(strict_types=1);

namespace Modules\Master\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Master\Repository\KlasifikasiRepository;

final class KlasifikasiService
{
    public const PER_PAGE = 25;
    public const PESAN_KODE_DIPAKAI = 'Kode klasifikasi sudah dipakai.';

    private const RULES = [
        'kode' => 'required|max:20',
        'uraian' => 'required|max:255',
    ];
    private const FILTER_RULES = [
        'q' => 'nullable|max:100',
        'status' => 'nullable|in:aktif,nonaktif',
        'page' => 'nullable|int',
    ];

    public function __construct(
        private readonly KlasifikasiRepository $repo,
        private readonly Audit $audit,
        private readonly Database $db
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>}
     */
    public function list(array $query): array
    {
        try {
            $f = Validator::validate($query, self::FILTER_RULES);
        } catch (ValidationException) {
            $f = [];
        }
        $filter = ['q' => (string) ($f['q'] ?? ''), 'status' => (string) ($f['status'] ?? '')];
        $page = max(1, (int) ($f['page'] ?? 1));
        $result = $this->repo->paginate($filter, $page, self::PER_PAGE);
        return [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'filter' => $filter,
        ];
    }

    /** @return list<array<string,mixed>> pilihan formulir surat: hanya yang aktif */
    public function activeOptions(): array
    {
        return $this->repo->activeOptions();
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

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $data = Validator::validate($input, self::RULES);
        return $this->db->transaction(function () use ($data): int {
            if ($this->repo->kodeExists((string) $data['kode'])) {
                throw new ValidationException(['kode' => self::PESAN_KODE_DIPAKAI]);
            }
            $id = $this->repo->insert((string) $data['kode'], (string) $data['uraian'], date('Y-m-d H:i:s'));
            $this->audit->record('klasifikasi.create', 'klasifikasi_arsip', $id, ['sesudah' => $data]);
            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $before = $this->findOrFail($id);
        $data = Validator::validate($input, self::RULES);
        $this->db->transaction(function () use ($id, $data, $before): void {
            if ($this->repo->kodeExists((string) $data['kode'], $id)) {
                throw new ValidationException(['kode' => self::PESAN_KODE_DIPAKAI]);
            }
            $this->repo->update($id, (string) $data['kode'], (string) $data['uraian'], date('Y-m-d H:i:s'));
            $this->audit->record('klasifikasi.update', 'klasifikasi_arsip', $id, [
                'sebelum' => ['kode' => $before['kode'], 'uraian' => $before['uraian']],
                'sesudah' => $data,
            ]);
        });
    }

    public function setActive(int $id, bool $active): void
    {
        $before = $this->findOrFail($id);
        if ((int) $before['is_active'] === ($active ? 1 : 0)) {
            return;
        }
        $this->db->transaction(function () use ($id, $active): void {
            $this->repo->setActive($id, $active, date('Y-m-d H:i:s'));
            $this->audit->record($active ? 'klasifikasi.activate' : 'klasifikasi.deactivate', 'klasifikasi_arsip', $id, [
                'sebelum' => ['is_active' => $active ? 0 : 1],
                'sesudah' => ['is_active' => $active ? 1 : 0],
            ]);
        });
    }
}
