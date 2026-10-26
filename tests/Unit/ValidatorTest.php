<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\ValidationException;
use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $rules
     * @return array<string,string>
     */
    private static function errorsOf(array $input, array $rules): array
    {
        try {
            Validator::validate($input, $rules);
        } catch (ValidationException $e) {
            return $e->errors();
        }
        return [];
    }

    public function test_required_trim_dan_field_tanpa_aturan_dibuang(): void
    {
        $clean = Validator::validate(['nama' => '  Siti  ', 'liar' => 'x'], ['nama' => 'required']);
        self::assertSame(['nama' => 'Siti'], $clean);
        self::assertSame(['nama' => Validator::MSG_REQUIRED], self::errorsOf(['nama' => '   '], ['nama' => 'required']));
    }

    public function test_nullable_kosong_menjadi_null(): void
    {
        self::assertSame(['catatan' => null], Validator::validate(['catatan' => ''], ['catatan' => 'nullable|max:5']));
    }

    public function test_max_dan_min_menghitung_karakter_multibyte(): void
    {
        self::assertSame([], self::errorsOf(['x' => 'éééé'], ['x' => 'max:4']));
        self::assertSame(['x' => 'Maksimal 3 karakter.'], self::errorsOf(['x' => 'éééé'], ['x' => 'max:3']));
        self::assertSame(['x' => 'Minimal 10 karakter.'], self::errorsOf(['x' => 'pendek'], ['x' => 'min:10']));
    }

    public function test_in_date_int_username_same(): void
    {
        self::assertSame(['r' => Validator::MSG_IN], self::errorsOf(['r' => 'pimpinan'], ['r' => 'in:admin,operator']));
        self::assertSame(['d' => Validator::MSG_DATE], self::errorsOf(['d' => '2026-02-30'], ['d' => 'date']));
        self::assertSame(['d' => Validator::MSG_DATE], self::errorsOf(['d' => '26/10/2026'], ['d' => 'date']));
        self::assertSame([], self::errorsOf(['d' => '2028-02-29'], ['d' => 'date']));
        self::assertSame(['n' => 12], Validator::validate(['n' => '12'], ['n' => 'int']));
        self::assertSame(['n' => Validator::MSG_INT], self::errorsOf(['n' => '1.5'], ['n' => 'int']));
        self::assertSame(['u' => Validator::MSG_USERNAME], self::errorsOf(['u' => 'Siti TU'], ['u' => 'username']));
        self::assertSame([], self::errorsOf(['u' => 'siti.tu_2'], ['u' => 'username']));
        self::assertSame(['k' => Validator::MSG_SAME], self::errorsOf(['a' => 'satu', 'k' => 'dua'], ['k' => 'same:a']));
    }

    public function test_karakter_kontrol_dan_array_ditolak(): void
    {
        self::assertSame(['x' => Validator::MSG_CONTROL], self::errorsOf(['x' => "ab\x00c"], ['x' => 'required']));
        self::assertSame(['x' => Validator::MSG_IN], self::errorsOf(['x' => ['a']], ['x' => 'required']));
        self::assertSame(['x' => "baris1\nbaris2"], Validator::validate(['x' => "baris1\nbaris2"], ['x' => 'required']));
    }

    public function test_semua_error_dikumpulkan_per_field(): void
    {
        $errors = self::errorsOf([], ['a' => 'required', 'b' => 'required', 'c' => 'nullable']);
        self::assertSame(['a', 'b'], array_keys($errors));
    }
}
