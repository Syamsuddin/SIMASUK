<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Satu-satunya pembungkus PDO (docs/08). SQL bisnis tetap ditulis di Repository.
 */
final class Database
{
    private PDO $pdo;

    /** @var list<callable():void> dijalankan bila transaksi terluar di-rollback */
    private array $onRollback = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function connect(string $host, string $port, string $name, string $user, string $pass): self
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Tahun agenda bergantung pada zona waktu yang sama di PHP dan MySQL (docs/16 jebakan 4).
        $pdo->exec("SET time_zone = '+08:00'");
        return new self($pdo);
    }

    public static function fromEnv(bool $testing = false): self
    {
        $name = $testing ? Env::get('DB_TEST_NAME', '') : Env::get('DB_NAME', '');
        return self::connect(
            (string) Env::get('DB_HOST', '127.0.0.1'),
            (string) Env::get('DB_PORT', '3306'),
            (string) $name,
            (string) Env::get('DB_USER', ''),
            (string) Env::get('DB_PASS', ''),
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string|int,mixed> $params @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params @return array<string,mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Parameter di-bind sesuai tipe PHP agar LIMIT/OFFSET bekerja dengan prepared statement asli.
     *
     * @param array<string|int,mixed> $params
     */
    private function run(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Menjalankan $fn dalam transaksi. Transaksi bersarang ikut transaksi terluar.
     *
     * @template T
     * @param callable(self):T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            $this->onRollback = [];
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $callbacks = $this->onRollback;
            $this->onRollback = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
            throw $e;
        }
    }

    /** Dipakai FileStore agar berkas yang sudah ditulis dihapus bila transaksi gagal (docs/14). */
    public function onRollback(callable $callback): void
    {
        $this->onRollback[] = $callback;
    }
}
