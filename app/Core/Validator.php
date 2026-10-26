<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validasi input berbasis aturan (docs/08, docs/21). Aturan dipisah "|":
 * required, nullable, max:N, min:N, in:a,b, date, int, username, same:field, accepted.
 * Semua string di-trim; karakter kontrol ditolak.
 */
final class Validator
{
    public const MSG_REQUIRED = 'Wajib diisi.';
    public const MSG_MAX = 'Maksimal :n karakter.';
    public const MSG_MIN = 'Minimal :n karakter.';
    public const MSG_IN = 'Pilihan tidak valid.';
    public const MSG_DATE = 'Tanggal tidak valid. Gunakan format tanggal yang benar.';
    public const MSG_INT = 'Harus berupa angka bulat.';
    public const MSG_USERNAME = 'Hanya huruf kecil, angka, titik, dan garis bawah.';
    public const MSG_SAME = 'Konfirmasi tidak cocok.';
    public const MSG_CONTROL = 'Mengandung karakter yang tidak diizinkan.';
    public const MSG_BERKAS_WAJIB = 'Pilih minimal satu berkas PDF.';
    public const MSG_BERKAS_JUMLAH = 'Maksimal :n berkas PDF per surat.';
    public const MSG_BERKAS_PDF = 'Berkas harus PDF.';
    public const MSG_BERKAS_UKURAN = 'Ukuran berkas maks :n MB.';
    public const MSG_BERKAS_GAGAL = 'Berkas gagal diunggah. Pilih ulang lalu simpan kembali.';

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $rules
     * @return array<string,mixed> nilai bersih, hanya field yang punya aturan
     * @throws ValidationException
     */
    public static function validate(array $input, array $rules): array
    {
        $clean = [];
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = $ruleString === '' ? [] : explode('|', $ruleString);
            $raw = $input[$field] ?? null;
            $isCheckbox = in_array('accepted', $ruleList, true);

            if (is_array($raw)) {
                $errors[$field] = self::MSG_IN;
                continue;
            }
            $value = $raw === null ? '' : trim((string) $raw);

            if ($isCheckbox) {
                $clean[$field] = in_array($value, ['1', 'on', 'yes'], true);
                continue;
            }

            if ($value === '') {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field] = self::MSG_REQUIRED;
                } else {
                    $clean[$field] = null;
                }
                continue;
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 || !mb_check_encoding($value, 'UTF-8')) {
                $errors[$field] = self::MSG_CONTROL;
                continue;
            }

            $error = null;
            $typed = $value;
            foreach ($ruleList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'required', 'nullable' => null,
                    'max' => mb_strlen($value) > (int) $param ? str_replace(':n', (string) $param, self::MSG_MAX) : null,
                    'min' => mb_strlen($value) < (int) $param ? str_replace(':n', (string) $param, self::MSG_MIN) : null,
                    'in' => in_array($value, explode(',', (string) $param), true) ? null : self::MSG_IN,
                    'date' => self::isDate($value) ? null : self::MSG_DATE,
                    'int' => preg_match('/^-?\d{1,10}$/', $value) === 1 ? null : self::MSG_INT,
                    'username' => preg_match('/^[a-z0-9._]+$/', $value) === 1 ? null : self::MSG_USERNAME,
                    'same' => $value === trim((string) ($input[(string) $param] ?? '')) ? null : self::MSG_SAME,
                    default => throw new \LogicException("Aturan validasi tidak dikenal: {$name}"),
                };
                if ($error !== null) {
                    break;
                }
                if ($name === 'int') {
                    $typed = (int) $value;
                }
            }

            if ($error !== null) {
                $errors[$field] = $error;
                continue;
            }
            $clean[$field] = $typed;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    public static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
