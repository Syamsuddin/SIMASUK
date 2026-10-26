<?php

declare(strict_types=1);

namespace Modules\Auth\Service;

use App\Core\Audit;
use App\Core\Database;
use App\Core\DomainRuleException;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Core\Validator;
use Modules\Pengguna\Repository\PenggunaRepository;

final class AuthService
{
    public const PESAN_GAGAL = 'Username atau password salah.';
    public const MAKS_GAGAL = 5;
    public const KUNCI_MENIT = 15;

    private const LOGIN_RULES = ['username' => 'required|max:50', 'password' => 'required'];
    private const PASSWORD_RULES = [
        'password_lama' => 'required',
        'password_baru' => 'required|min:10',
        'password_konfirmasi' => 'required|same:password_baru',
    ];

    /** @var \Closure():void */
    private \Closure $delay;
    private ?string $dummyHash = null;

    public function __construct(
        private readonly PenggunaRepository $users,
        private readonly Audit $audit,
        private readonly Database $db,
        private readonly Logger $logger,
        ?\Closure $delay = null
    ) {
        // Jeda 1 detik per percobaan gagal memperlambat tebak-tebakan password (docs/21).
        $this->delay = $delay ?? static function (): void {
            sleep(1);
        };
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> baris pengguna yang berhasil login
     * @throws ValidationException|DomainRuleException
     */
    public function attempt(array $input): array
    {
        $data = Validator::validate($input, self::LOGIN_RULES);
        $username = mb_strtolower((string) $data['username']);
        $password = (string) ($input['password'] ?? '');

        $outcome = $this->db->transaction(function () use ($username, $password): array {
            $now = date('Y-m-d H:i:s');
            $found = $this->users->findByUsername($username);
            if ($found === null) {
                // Tetap memverifikasi hash tiruan agar waktu respons tidak membocorkan username yang ada.
                password_verify($password, $this->dummyHash());
                $this->audit->record('auth.login_failed', 'users', null, ['username' => $username], anonymous: true);
                return ['ok' => false, 'user' => null];
            }

            $user = $this->users->findById((int) $found['id'], true) ?? $found;
            $id = (int) $user['id'];

            if ($user['locked_until'] !== null && $user['locked_until'] > $now) {
                password_verify($password, $this->dummyHash());
                $this->audit->record('auth.locked', 'users', $id, [
                    'username' => $username,
                    'locked_until' => $user['locked_until'],
                ], anonymous: true);
                return ['ok' => false, 'user' => $user];
            }

            $valid = password_verify($password, (string) $user['password_hash']);

            if (!$valid) {
                $expiredLock = $user['locked_until'] !== null;
                $attempts = ($expiredLock ? 0 : (int) $user['failed_attempts']) + 1;
                $lockedUntil = null;
                if ($attempts >= self::MAKS_GAGAL) {
                    $lockedUntil = date('Y-m-d H:i:s', strtotime($now) + self::KUNCI_MENIT * 60);
                }
                $this->users->recordFailedLogin($id, $attempts, $lockedUntil, $now);
                $this->audit->record('auth.login_failed', 'users', $id, [
                    'username' => $username,
                    'percobaan' => $attempts,
                ], anonymous: true);
                if ($lockedUntil !== null) {
                    $this->audit->record('auth.locked', 'users', $id, [
                        'username' => $username,
                        'locked_until' => $lockedUntil,
                    ], anonymous: true);
                }
                return ['ok' => false, 'user' => $user, 'locked' => $lockedUntil !== null];
            }

            if ((int) $user['is_active'] !== 1) {
                $this->audit->record('auth.login_failed', 'users', $id, [
                    'username' => $username,
                    'alasan' => 'nonaktif',
                ], anonymous: true);
                return ['ok' => false, 'user' => $user];
            }

            $newHash = password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)
                ? password_hash($password, PASSWORD_ARGON2ID)
                : null;
            $this->users->recordSuccessfulLogin($id, $now, $newHash);
            $this->audit->record('auth.login', 'users', $id, null, userId: $id);
            return ['ok' => true, 'user' => $this->users->findById($id)];
        });

        if (!$outcome['ok']) {
            $this->logger->warning('Login gagal', [
                'username' => $username,
                'user_id' => $outcome['user']['id'] ?? null,
                'terkunci' => $outcome['locked'] ?? false,
            ]);
            ($this->delay)();
            throw new DomainRuleException(self::PESAN_GAGAL);
        }

        $this->logger->info('Login berhasil', ['user_id' => (int) $outcome['user']['id']]);
        return $outcome['user'];
    }

    public function logout(int $userId): void
    {
        $this->audit->record('auth.logout', 'users', $userId, null, userId: $userId);
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationException
     */
    public function changePassword(int $userId, array $input): void
    {
        $data = Validator::validate($input, self::PASSWORD_RULES);
        $user = $this->users->findById($userId);
        if ($user === null || !password_verify((string) ($input['password_lama'] ?? ''), (string) $user['password_hash'])) {
            throw new ValidationException(['password_lama' => 'Password lama tidak sesuai.']);
        }
        $this->db->transaction(function () use ($userId, $data): void {
            $this->users->updatePassword(
                $userId,
                password_hash((string) $data['password_baru'], PASSWORD_ARGON2ID),
                date('Y-m-d H:i:s')
            );
            $this->audit->record('user.password_change', 'users', $userId);
        });
    }

    private function dummyHash(): string
    {
        return $this->dummyHash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
    }
}
