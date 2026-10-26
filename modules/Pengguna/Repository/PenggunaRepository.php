<?php

declare(strict_types=1);

namespace Modules\Pengguna\Repository;

use App\Core\Database;

class PenggunaRepository
{
    private const KOLOM = 'id, username, nama, password_hash, role, is_active, failed_attempts,
        locked_until, last_login_at, created_at, updated_at';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT ' . self::KOLOM . ' FROM users WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : '');
        return $this->db->fetchOne($sql, [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne('SELECT ' . self::KOLOM . ' FROM users WHERE username = ?', [$username]);
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM users WHERE username = ?', [$username]) !== null;
    }

    /**
     * @param array{q?:string,role?:string,status?:string} $filter
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function paginate(array $filter, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if (($filter['q'] ?? '') !== '') {
            $where[] = '(username LIKE ? OR nama LIKE ?)';
            $like = '%' . addcslashes($filter['q'], '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (($filter['role'] ?? '') !== '') {
            $where[] = 'role = ?';
            $params[] = $filter['role'];
        }
        if (($filter['status'] ?? '') !== '') {
            $where[] = 'is_active = ?';
            $params[] = $filter['status'] === 'aktif' ? 1 : 0;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM users' . $whereSql, $params);
        $rows = $this->db->fetchAll(
            'SELECT id, username, nama, role, is_active, locked_until, last_login_at, created_at FROM users'
            . $whereSql . ' ORDER BY is_active DESC, nama ASC, id ASC LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array{username:string,nama:string,password_hash:string,role:string} $data */
    public function insert(array $data, string $now): int
    {
        $this->db->execute(
            'INSERT INTO users (username, nama, password_hash, role, is_active, failed_attempts, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, 0, ?, ?)',
            [$data['username'], $data['nama'], $data['password_hash'], $data['role'], $now, $now]
        );
        return $this->db->lastInsertId();
    }

    public function setActive(int $id, bool $active, string $now): void
    {
        $this->db->execute(
            'UPDATE users SET is_active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, $now, $id]
        );
    }

    /** Ganti password sekaligus membuka kunci akun. */
    public function updatePassword(int $id, string $hash, string $now): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL, updated_at = ? WHERE id = ?',
            [$hash, $now, $id]
        );
    }

    public function recordFailedLogin(int $id, int $attempts, ?string $lockedUntil, string $now): void
    {
        $this->db->execute(
            'UPDATE users SET failed_attempts = ?, locked_until = ?, updated_at = ? WHERE id = ?',
            [$attempts, $lockedUntil, $now, $id]
        );
    }

    public function recordSuccessfulLogin(int $id, string $now, ?string $newHash): void
    {
        if ($newHash !== null) {
            $this->db->execute(
                'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = ?, password_hash = ?,
                 updated_at = ? WHERE id = ?',
                [$now, $newHash, $now, $id]
            );
            return;
        }
        $this->db->execute(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $id]
        );
    }

    /** Dipanggil dalam transaksi; mengunci baris admin aktif agar dua penonaktifan bersamaan tak lolos. */
    public function countActiveAdminsForUpdate(): int
    {
        return count($this->db->fetchAll(
            "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE"
        ));
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM users');
    }
}
