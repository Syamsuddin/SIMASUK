<?php

declare(strict_types=1);

namespace Modules\Audit\Service;

use App\Core\Audit;
use App\Core\Validator;
use Modules\Audit\Repository\AuditRepository;
use Modules\Pengguna\Repository\PenggunaRepository;

/** Penelusuran log audit oleh Admin (docs/01 F-AUDIT). Hanya baca; log tidak pernah diubah. */
final class AuditService
{
    public const PER_PAGE = 25;

    private const FILTER_RULES = [
        'user_id' => 'nullable|int',
        'aksi' => 'nullable|max:40',
        'dari' => 'nullable|date',
        'sampai' => 'nullable|date',
        'page' => 'nullable|int',
    ];

    public function __construct(
        private readonly AuditRepository $repo,
        private readonly PenggunaRepository $users
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,filter:array<string,string>,
     *   errors:array<string,string>,actions:array<string,string>,users:array<int,string>}
     */
    public function list(array $query): array
    {
        [$f, $errors] = Validator::filter($query, self::FILTER_RULES);
        $filter = [
            'user_id' => (string) ($f['user_id'] ?? ''),
            'aksi' => (string) ($f['aksi'] ?? ''),
            'dari' => (string) ($f['dari'] ?? ''),
            'sampai' => (string) ($f['sampai'] ?? ''),
        ];
        $page = max(1, (int) ($f['page'] ?? 1));
        $result = $this->repo->paginate($filter, $page, self::PER_PAGE);

        $rows = [];
        foreach ($result['rows'] as $row) {
            $row['data_tampil'] = self::dataTampil($row['data_json']);
            unset($row['data_json']);
            $rows[] = $row;
        }
        $actions = $this->repo->actions();

        return [
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'filter' => $filter,
            'errors' => $errors,
            'actions' => array_combine($actions, $actions) ?: [],
            'users' => $this->users->options(),
        ];
    }

    /**
     * JSON rapi untuk ditampilkan. Disaring ulang saat dibaca: baris lama atau hasil impor pun tidak
     * pernah memperlihatkan password_hash maupun token (docs/07).
     */
    public static function dataTampil(?string $json): string
    {
        if ($json === null || $json === '') {
            return '';
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }
        return (string) json_encode(
            Audit::sanitize($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
