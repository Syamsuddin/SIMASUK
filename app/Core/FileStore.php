<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Satu-satunya yang menyentuh STORAGE_PATH (docs/12, docs/21). Berkas disimpan di luar webroot dengan
 * nama acak 32 heksa dan hanya bisa diunduh lewat controller berotorisasi.
 */
final class FileStore
{
    public const MAX_FILES = 5;
    private const PATH_PATTERN = '#^surat/\d{4}/[0-9a-f]{32}\.pdf$#';

    /** @param bool $requireUploaded false hanya untuk tes, karena berkas tes bukan hasil unggahan HTTP */
    public function __construct(
        private readonly string $root,
        private readonly int $maxMb = 10,
        private readonly bool $requireUploaded = true
    ) {
        if ($root === '' || !str_starts_with($root, '/')) {
            throw new \LogicException('STORAGE_PATH wajib berupa path absolut.');
        }
    }

    public static function fromEnv(string $appRoot): self
    {
        $root = rtrim((string) Env::get('STORAGE_PATH', ''), '/');
        $public = realpath($appRoot . '/public');
        if ($root !== '' && $public !== false && str_starts_with($root . '/', $public . '/')) {
            throw new \LogicException('STORAGE_PATH tidak boleh berada di dalam public/.');
        }
        return new self($root, max(1, (int) Env::get('UPLOAD_MAX_MB', '10')));
    }

    public function maxMb(): int
    {
        return $this->maxMb;
    }

    /**
     * Menyeragamkan $_FILES[field] (tunggal atau name="x[]") menjadi daftar; slot kosong dibuang.
     *
     * @return list<array{name:string,tmp_name:string,error:int,size:int}>
     */
    public static function uploads(mixed $field): array
    {
        if (!is_array($field) || !isset($field['error'])) {
            return [];
        }
        $keys = ['name', 'tmp_name', 'error', 'size'];
        $columns = [];
        foreach ($keys as $key) {
            $columns[$key] = is_array($field[$key] ?? null) ? array_values($field[$key]) : [$field[$key] ?? null];
        }
        $out = [];
        foreach ($columns['error'] as $i => $error) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) ($columns['name'][$i] ?? ''),
                'tmp_name' => (string) ($columns['tmp_name'][$i] ?? ''),
                'error' => (int) $error,
                'size' => (int) ($columns['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Memeriksa jumlah, ukuran, dan isi PDF tanpa menulis apa pun, agar kegagalan tidak memakai nomor agenda.
     *
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $uploads
     * @throws ValidationException pada field $field
     */
    public function validatePdfs(array $uploads, string $field = 'berkas'): void
    {
        $error = match (true) {
            $uploads === [] => Validator::MSG_BERKAS_WAJIB,
            count($uploads) > self::MAX_FILES => str_replace(':n', (string) self::MAX_FILES, Validator::MSG_BERKAS_JUMLAH),
            default => null,
        };
        foreach ($error === null ? $uploads : [] as $upload) {
            $error = $this->checkPdf($upload);
            if ($error !== null) {
                break;
            }
        }
        if ($error !== null) {
            throw new ValidationException([$field => $error]);
        }
    }

    /**
     * Menulis satu PDF ke surat/{tahun}/. Wajib di dalam transaksi: bila transaksi di-rollback,
     * berkas yang sudah tertulis dihapus lagi (docs/14).
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $upload
     * @return array{path:string,nama_asli:string,ukuran:int,sha256:string}
     */
    public function storePdf(array $upload, int $tahun, Database $db): array
    {
        if (!$db->pdo()->inTransaction()) {
            throw new \LogicException('FileStore::storePdf wajib dipanggil di dalam transaksi.');
        }
        $error = $this->checkPdf($upload);
        if ($error !== null) {
            throw new ValidationException(['berkas' => $error]);
        }

        $dir = $this->root . '/surat/' . $tahun;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $relative = 'surat/' . $tahun . '/' . bin2hex(random_bytes(16)) . '.pdf';
        $target = $this->root . '/' . $relative;
        $db->onRollback(static function () use ($target): void {
            if (is_file($target)) {
                unlink($target);
            }
        });

        $written = $this->requireUploaded
            ? move_uploaded_file($upload['tmp_name'], $target)
            : copy($upload['tmp_name'], $target);
        if (!$written) {
            throw new \RuntimeException('Gagal menulis berkas ke penyimpanan.');
        }
        chmod($target, 0640);

        return [
            'path' => $relative,
            'nama_asli' => self::sanitizeName($upload['name']),
            'ukuran' => (int) filesize($target),
            'sha256' => (string) hash_file('sha256', $target),
        ];
    }

    /** Isi berkas tersimpan untuk diunduh lewat controller berotorisasi. */
    public function read(string $relative): string
    {
        return (string) file_get_contents($this->absolutePath($relative));
    }

    /** Path absolut berkas tersimpan; path di luar pola baku atau berkas hilang → 404. */
    private function absolutePath(string $relative): string
    {
        if (preg_match(self::PATH_PATTERN, $relative) !== 1) {
            throw new HttpException(404);
        }
        $path = $this->root . '/' . $relative;
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        return $path;
    }

    /** Nama asli hanya untuk tampilan dan header unduhan: tanpa path, tanpa karakter kontrol. */
    public static function sanitizeName(string $name): string
    {
        $name = str_replace(['/', '\\', '"'], '_', $name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_check_encoding($name, 'UTF-8') ? trim($name) : '';
        $name = mb_substr($name, 0, 255);
        return $name === '' ? 'berkas.pdf' : $name;
    }

    /** @param array{name:string,tmp_name:string,error:int,size:int} $upload */
    private function checkPdf(array $upload): ?string
    {
        $tooBig = str_replace(':n', (string) $this->maxMb, Validator::MSG_BERKAS_UKURAN);
        if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return $tooBig;
        }
        $tmp = $upload['tmp_name'];
        if ($upload['error'] !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)
            || ($this->requireUploaded && !is_uploaded_file($tmp))) {
            return Validator::MSG_BERKAS_GAGAL;
        }
        if ((int) filesize($tmp) > $this->maxMb * 1024 * 1024) {
            return $tooBig;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $head = (string) file_get_contents($tmp, false, null, 0, 5);
        return $mime === 'application/pdf' && $head === '%PDF-' ? null : Validator::MSG_BERKAS_PDF;
    }
}
