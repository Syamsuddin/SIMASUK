<?php

declare(strict_types=1);

namespace Modules\Master\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Master\Repository\UnitKerjaRepository;

final class UnitKerjaService
{
    public const PESAN_INDUK_MELINGKAR = 'Unit induk tidak boleh unit ini sendiri atau turunannya.';
    public const PESAN_INDUK_TIDAK_ADA = 'Unit induk tidak ditemukan.';

    private const RULES = [
        'nama' => 'required|max:150',
        'jabatan_pimpinan' => 'nullable|max:150',
        'parent_id' => 'nullable|int',
        'urutan' => 'nullable|int',
    ];

    public function __construct(
        private readonly UnitKerjaRepository $repo,
        private readonly Audit $audit,
        private readonly Database $db
    ) {
    }

    /**
     * Seluruh unit sebagai daftar datar berurutan pohon, dengan kunci 'depth'.
     *
     * @return list<array<string,mixed>>
     */
    public function tree(): array
    {
        return self::flatten($this->repo->all());
    }

    /**
     * Pilihan unit tujuan disposisi: hanya unit aktif (docs/06 P2).
     *
     * @return list<array<string,mixed>>
     */
    public function pilihanTujuan(): array
    {
        return self::flatten($this->repo->allActive());
    }

    /**
     * Pilihan unit induk pada formulir: tanpa unit yang sedang diubah dan seluruh turunannya.
     *
     * @return list<array<string,mixed>>
     */
    public function parentOptions(?int $editingId = null): array
    {
        $tree = $this->tree();
        if ($editingId === null) {
            return $tree;
        }
        $excluded = $this->descendantIds($editingId, $this->repo->all());
        $excluded[] = $editingId;
        return array_values(array_filter($tree, static fn (array $u): bool => !in_array((int) $u['id'], $excluded, true)));
    }

    /** Unit dikembalikan apa pun statusnya, sehingga nama unit nonaktif tetap tampil pada disposisi lama. */
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
        $data = $this->validated($input);
        return $this->db->transaction(function () use ($data): int {
            $this->assertParentValid(null, $data['parent_id']);
            $id = $this->repo->insert($data, date('Y-m-d H:i:s'));
            $this->audit->record('unit.create', 'unit_kerja', $id, ['sesudah' => $data]);
            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $before = $this->findOrFail($id);
        $data = $this->validated($input);
        $this->db->transaction(function () use ($id, $data, $before): void {
            $this->assertParentValid($id, $data['parent_id']);
            $this->repo->update($id, $data, date('Y-m-d H:i:s'));
            $this->audit->record('unit.update', 'unit_kerja', $id, [
                'sebelum' => [
                    'parent_id' => $before['parent_id'],
                    'nama' => $before['nama'],
                    'jabatan_pimpinan' => $before['jabatan_pimpinan'],
                    'urutan' => $before['urutan'],
                ],
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
            $this->audit->record($active ? 'unit.activate' : 'unit.deactivate', 'unit_kerja', $id, [
                'sebelum' => ['is_active' => $active ? 0 : 1],
                'sesudah' => ['is_active' => $active ? 1 : 0],
            ]);
        });
    }

    /**
     * @param array<string,mixed> $input
     * @return array{parent_id:?int,nama:string,jabatan_pimpinan:?string,urutan:int}
     */
    private function validated(array $input): array
    {
        $data = Validator::validate($input, self::RULES);
        $urutan = $data['urutan'] ?? 0;
        if ($urutan < 0 || $urutan > 65535) {
            throw new ValidationException(['urutan' => 'Urutan harus antara 0 dan 65535.']);
        }
        return [
            'parent_id' => $data['parent_id'] === null ? null : (int) $data['parent_id'],
            'nama' => (string) $data['nama'],
            'jabatan_pimpinan' => $data['jabatan_pimpinan'] === null ? null : (string) $data['jabatan_pimpinan'],
            'urutan' => (int) $urutan,
        ];
    }

    private function assertParentValid(?int $id, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($this->repo->findById($parentId) === null) {
            throw new ValidationException(['parent_id' => self::PESAN_INDUK_TIDAK_ADA]);
        }
        if ($id === null) {
            return;
        }
        if ($parentId === $id || in_array($parentId, $this->descendantIds($id, $this->repo->all()), true)) {
            throw new ValidationException(['parent_id' => self::PESAN_INDUK_MELINGKAR]);
        }
    }

    /**
     * @param list<array<string,mixed>> $all
     * @return list<int>
     */
    private function descendantIds(int $id, array $all): array
    {
        $children = [];
        foreach ($all as $unit) {
            if ($unit['parent_id'] !== null) {
                $children[(int) $unit['parent_id']][] = (int) $unit['id'];
            }
        }
        $result = [];
        $stack = $children[$id] ?? [];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (in_array($current, $result, true)) {
                continue;
            }
            $result[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                $stack[] = $child;
            }
        }
        return $result;
    }

    /**
     * Urutkan unit menjadi urutan pohon. Unit yang induknya tidak ada dalam daftar (mis. induk nonaktif)
     * diperlakukan sebagai akar agar tetap tampil.
     *
     * @param list<array<string,mixed>> $units terurut urutan, nama
     * @return list<array<string,mixed>>
     */
    private static function flatten(array $units): array
    {
        $ids = array_map(static fn (array $u): int => (int) $u['id'], $units);
        $children = [];
        $roots = [];
        foreach ($units as $unit) {
            $parent = $unit['parent_id'] === null ? null : (int) $unit['parent_id'];
            if ($parent === null || !in_array($parent, $ids, true)) {
                $roots[] = $unit;
            } else {
                $children[$parent][] = $unit;
            }
        }
        $out = [];
        $walk = static function (array $nodes, int $depth) use (&$walk, &$out, $children): void {
            foreach ($nodes as $node) {
                $node['depth'] = $depth;
                $out[] = $node;
                $walk($children[(int) $node['id']] ?? [], $depth + 1);
            }
        };
        $walk($roots, 0);
        return $out;
    }
}
