<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\App;
use App\Core\Audit;
use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use Modules\Audit\Repository\AuditRepository;
use Modules\Pengguna\Repository\PenggunaRepository;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $store = [];
    private Session $session;
    private App $app;

    protected function setUp(): void
    {
        $this->store = [];
        $this->session = new Session($this->store);
        $this->session->set(AuthMiddleware::SESSION_KEY, ['id' => 1, 'login_at' => time(), 'last_activity' => time()]);

        $users = $this->createStub(PenggunaRepository::class);
        $users->method('findById')->willReturn([
            'id' => 1, 'username' => 'admin', 'nama' => 'Admin', 'role' => 'admin', 'is_active' => 1,
            'password_hash' => 'x', 'locked_until' => null, 'last_login_at' => null,
        ]);
        $logger = new Logger(sys_get_temp_dir() . '/simasuk-test-logs');
        $this->app = new App(new Router(require dirname(__DIR__, 2) . '/config/routes.php'));
        $this->app->instance(Session::class, $this->session);
        $this->app->instance(Logger::class, $logger);
        $this->app->instance(ErrorHandler::class, new ErrorHandler($logger));
        $this->app->instance(PenggunaRepository::class, $users);
        $this->app->instance(Audit::class, new Audit($this->createStub(AuditRepository::class)));
    }

    public function test_token_64_heksa_dan_stabil_dalam_satu_sesi(): void
    {
        $first = Csrf::token($this->session);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertSame($first, Csrf::token($this->session));
        self::assertTrue(Csrf::valid($this->session, $first));
    }

    public function test_token_salah_kosong_atau_bukan_string_tidak_valid(): void
    {
        $token = Csrf::token($this->session);
        self::assertFalse(Csrf::valid($this->session, strrev($token)));
        self::assertFalse(Csrf::valid($this->session, ''));
        self::assertFalse(Csrf::valid($this->session, null));
        self::assertFalse(Csrf::valid($this->session, [$token]));
    }

    public function test_rotate_mengganti_token(): void
    {
        $old = Csrf::token($this->session);
        $new = Csrf::rotate($this->session);
        self::assertNotSame($old, $new);
        self::assertFalse(Csrf::valid($this->session, $old));
    }

    /** @return iterable<string,array{0:string}> */
    public static function postPaths(): iterable
    {
        yield 'login' => ['/login'];
        yield 'logout' => ['/logout'];
        yield 'simpan pengguna' => ['/pengguna'];
        yield 'nonaktifkan pengguna' => ['/pengguna/2/nonaktifkan'];
        yield 'simpan klasifikasi' => ['/master/klasifikasi'];
        yield 'ubah unit' => ['/master/unit/1/ubah'];
        yield 'rute tak dikenal' => ['/tidak-ada'];
    }

    /** @dataProvider postPaths */
    #[\PHPUnit\Framework\Attributes\DataProvider('postPaths')]
    public function test_post_tanpa_token_valid_419(string $path): void
    {
        Csrf::token($this->session);

        $tanpa = $this->app->handle(new Request('POST', $path, [], ['username' => 'admin']));
        self::assertSame(419, $tanpa->status);
        self::assertStringContainsString('Sesi berakhir. Muat ulang halaman lalu coba lagi.', $tanpa->body);

        $salah = $this->app->handle(new Request('POST', $path, [], [Csrf::FIELD => str_repeat('a', 64)]));
        self::assertSame(419, $salah->status);
    }

    public function test_post_dengan_token_valid_diteruskan(): void
    {
        $token = Csrf::token($this->session);

        // Sudah login: lolos CSRF, lalu GuestMiddleware mengalihkan ke dasbor tanpa menyentuh DB.
        $response = $this->app->handle(new Request('POST', '/login', [], [Csrf::FIELD => $token]));

        self::assertSame(303, $response->status);
        self::assertSame('/', $response->headers['Location']);
    }

    public function test_token_lewat_header_diterima(): void
    {
        $token = Csrf::token($this->session);

        $response = $this->app->handle(new Request('POST', '/login', [], [], [], ['x-csrf-token' => $token]));

        self::assertSame(303, $response->status);
        self::assertSame('/', $response->headers['Location']);
    }
}
