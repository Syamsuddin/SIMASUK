<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Audit;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        View::reset();
    }

    public function test_e_meng_escape_html_dan_kutip(): void
    {
        self::assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;&quot;', e("<script>alert('x')</script>\""));
        self::assertSame('', e(null));
        self::assertSame('12', e(12));
    }

    public function test_tgl_format_indonesia(): void
    {
        self::assertSame('26 Okt 2026', tgl('2026-10-26'));
        self::assertSame('1 Agu 2026', tgl('2026-08-01 07:00:00'));
        self::assertSame('26 Okt 2026 14.05', tgl_waktu('2026-10-26 14:05:00'));
        self::assertSame('', tgl(null));
    }

    public function test_url_membuang_query_kosong(): void
    {
        self::assertSame('/pengguna?role=admin&page=2', url('/pengguna', ['q' => '', 'role' => 'admin', 'page' => 2]));
        self::assertSame('/pengguna', url('/pengguna', ['q' => null]));
    }

    public function test_old_dan_error_membaca_flash_request(): void
    {
        View::share('old', ['nama' => 'Siti <b>']);
        View::share('errors', ['nama' => 'Wajib diisi.']);
        self::assertSame('Siti <b>', old('nama'));
        self::assertSame('bawaan', old('lain', 'bawaan'));
        self::assertSame('Wajib diisi.', error('nama'));
        self::assertNull(error('lain'));
    }

    public function test_sanitize_audit_membuang_kunci_rahasia_bersarang(): void
    {
        $clean = Audit::sanitize([
            'username' => 'siti',
            'password' => 'x',
            'sesudah' => ['password_hash' => 'h', 'nama' => 'Siti', '_token' => 't'],
        ]);
        self::assertSame(['username' => 'siti', 'sesudah' => ['nama' => 'Siti']], $clean);
    }
}
