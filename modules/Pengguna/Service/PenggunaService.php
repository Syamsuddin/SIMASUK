<?php

declare(strict_types=1);

namespace Modules\Pengguna\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\DomainRuleException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Pengguna\Repository\PenggunaRepository;

final class PenggunaService
{
    public const PER_PAGE = 25;
    public const PERAN = ['admin' => 'Admin', 'operator' => 'Operator TU'];

    public const PESAN_DIRI_SENDIRI = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
    public const PESAN_ADMIN_TERAKHIR = 'Admin aktif terakhir tidak dapat dinonaktifkan.';

    private const CREATE_RULES = [
        'username' => 'required|max:50|username',
        'nama' => 'required|max:120',
        'role' => 'required|in:admin,operator',
        'password' => 'required|min:10',
        'password_konfirmasi' => 'required|same:password',
    ];
    private const RESET_RULES = [
        'password' => 'required|min:10',
        'password_konfirmasi' => 'required|same:password',
    ];
    private const FILTER_RULES = [
        'q' => 'nullable|max:100',
        'role' => 'nullable|in:admin,operator',
        'status' => 'nullable|in:aktif,nonaktif',
        'page' => 'nullable|int',
    ];

    public function __construct(
        private readonly PenggunaRepository $users,
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
        $filter = [
            'q' => (string) ($f['q'] ?? ''),
            'role' => (string) ($f['role'] ?? ''),
            'status' => (string) ($f['status'] ?? ''),
        ];
        $page = max(1, (int) ($f['page'] ?? 1));
        $result = $this->users->paginate($filter, $page, self::PER_PAGE);
        return [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'filter' => $filter,
        ];
    }

    /** @return array<string,mixed> */
    public function findOrFail(int $id): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new HttpException(404);
        }
        return $user;
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationException
     */
    public function create(array $input): int
    {
        $input['username'] = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $data = Validator::validate($input, self::CREATE_RULES);

        return $this->db->transaction(function () use ($data): int {
            if ($this->users->usernameExists((string) $data['username'])) {
                throw new ValidationException(['username' => 'Username sudah dipakai.']);
            }
            $id = $this->users->insert([
                'username' => (string) $data['username'],
                'nama' => (string) $data['nama'],
                'password_hash' => password_hash((string) $data['password'], PASSWORD_ARGON2ID),
                'role' => (string) $data['role'],
            ], date('Y-m-d H:i:s'));
            $this->audit->record('user.create', 'users', $id, ['sesudah' => [
                'username' => $data['username'],
                'nama' => $data['nama'],
                'role' => $data['role'],
                'is_active' => 1,
            ]]);
            return $id;
        });
    }

    /** @throws DomainRuleException */
    public function deactivate(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new DomainRuleException(self::PESAN_DIRI_SENDIRI);
        }
        $this->db->transaction(function () use ($id): void {
            $user = $this->users->findById($id, true);
            if ($user === null) {
                throw new HttpException(404);
            }
            if ((int) $user['is_active'] !== 1) {
                return;
            }
            if ($user['role'] === 'admin' && $this->users->countActiveAdminsForUpdate() <= 1) {
                throw new DomainRuleException(self::PESAN_ADMIN_TERAKHIR);
            }
            $this->users->setActive($id, false, date('Y-m-d H:i:s'));
            $this->audit->record('user.deactivate', 'users', $id, [
                'sebelum' => ['is_active' => 1],
                'sesudah' => ['is_active' => 0],
            ]);
        });
    }

    public function activate(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $user = $this->users->findById($id, true);
            if ($user === null) {
                throw new HttpException(404);
            }
            if ((int) $user['is_active'] === 1) {
                return;
            }
            $this->users->setActive($id, true, date('Y-m-d H:i:s'));
            $this->audit->record('user.activate', 'users', $id, [
                'sebelum' => ['is_active' => 0],
                'sesudah' => ['is_active' => 1],
            ]);
        });
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationException
     */
    public function resetPassword(int $id, array $input): void
    {
        $data = Validator::validate($input, self::RESET_RULES);
        $this->db->transaction(function () use ($id, $data): void {
            if ($this->users->findById($id, true) === null) {
                throw new HttpException(404);
            }
            $this->users->updatePassword(
                $id,
                password_hash((string) $data['password'], PASSWORD_ARGON2ID),
                date('Y-m-d H:i:s')
            );
            $this->audit->record('user.reset_password', 'users', $id);
        });
    }
}
