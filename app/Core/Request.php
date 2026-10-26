<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed>|null pengguna terautentikasi, diisi AuthMiddleware */
    private ?array $user = null;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     * @param array<string,string> $headers nama header huruf kecil
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $headers = [],
        public readonly ?string $ip = null,
        public readonly string $host = ''
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return new self(
            $method === 'HEAD' ? 'GET' : $method,
            self::normalizePath($path),
            $_GET,
            $_POST,
            $_FILES,
            [
                'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
                'x-csrf-token' => (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''),
            ],
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            (string) ($_SERVER['HTTP_HOST'] ?? '')
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user === null ? null : (int) $this->user['id'];
    }

    /**
     * Tujuan "kembali" setelah POST gagal: hanya path dari Referer ber-host sama, agar tidak jadi open redirect.
     */
    public function backUrl(string $fallback = '/'): string
    {
        $referer = $this->header('referer');
        if ($referer === '') {
            return $fallback;
        }
        $parts = parse_url($referer);
        if ($parts === false || ($parts['host'] ?? '') === '' || !isset($parts['path'])) {
            return $fallback;
        }
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($this->host === '' || strcasecmp($host, $this->host) !== 0) {
            return $fallback;
        }
        return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
