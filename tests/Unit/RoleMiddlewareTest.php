<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\App;
use App\Core\Audit;
use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use Modules\Audit\Repository\AuditRepository;
use Modules\Dasbor\Repository\DasborRepository;
use Modules\Pengguna\Repository\PenggunaRepository;
use PHPUnit\Framework\TestCase;

/** Otorisasi diuji lewat kernel App dengan tabel rute nyata (docs/13). */
final class RoleMiddlewareTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $store = [];
    private Session $session;

    private function app(string $role): App
    {
        $this->store = [];
        $this->session = new Session($this->store);
        $this->session->set(AuthMiddleware::SESSION_KEY, ['id' => 7, 'login_at' => time(), 'last_activity' => time()]);

        $users = $this->createStub(PenggunaRepository::class);
        $users->method('findById')->willReturn([
            'id' => 7, 'username' => 'uji', 'nama' => 'Pengguna Uji', 'role' => $role, 'is_active' => 1,
            'password_hash' => 'x', 'locked_until' => null, 'last_login_at' => null,
        ]);
        $logger = new Logger(sys_get_temp_dir() . '/simasuk-test-logs');

        $app = new App(new Router(require dirname(__DIR__, 2) . '/config/routes.php'));
        $app->instance(Session::class, $this->session);
        $app->instance(Logger::class, $logger);
        $app->instance(ErrorHandler::class, new ErrorHandler($logger));
        $app->instance(PenggunaRepository::class, $users);
        $app->instance(Audit::class, new Audit($this->createStub(AuditRepository::class)));
        // Tes unit tanpa DB: dasbor diberi ringkasan kosong (stub mengembalikan 0 dan []).
        $app->instance(DasborRepository::class, $this->createStub(DasborRepository::class));
        return $app;
    }

    /** @return iterable<string,array{0:string,1:string}> */
    public static function adminRoutes(): iterable
    {
        yield 'daftar pengguna' => ['GET', '/pengguna'];
        yield 'form pengguna' => ['GET', '/pengguna/baru'];
        yield 'simpan pengguna' => ['POST', '/pengguna'];
        yield 'nonaktifkan pengguna' => ['POST', '/pengguna/3/nonaktifkan'];
        yield 'reset password' => ['POST', '/pengguna/3/reset-password'];
        yield 'master klasifikasi' => ['GET', '/master/klasifikasi'];
        yield 'simpan klasifikasi' => ['POST', '/master/klasifikasi'];
        yield 'master unit' => ['GET', '/master/unit'];
        yield 'ubah unit' => ['POST', '/master/unit/2/ubah'];
        yield 'batalkan surat' => ['POST', '/surat-masuk/5/batalkan'];
        yield 'log audit' => ['GET', '/audit'];
    }

    /** @dataProvider adminRoutes */
    #[\PHPUnit\Framework\Attributes\DataProvider('adminRoutes')]
    public function test_operator_ditolak_403_pada_rute_admin(string $method, string $path): void
    {
        $app = $this->app('operator');
        $post = $method === 'POST' ? [Csrf::FIELD => Csrf::token($this->session)] : [];

        $response = $app->handle(new Request($method, $path, [], $post));

        self::assertSame(403, $response->status);
        self::assertStringContainsString('Anda tidak memiliki akses ke halaman ini.', $response->body);
    }

    public function test_operator_boleh_membuka_dasbor(): void
    {
        $response = $this->app('operator')->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Dasbor', $response->body);
        self::assertStringNotContainsString('href="/pengguna"', $response->body, 'Menu Pengaturan tak boleh tampil untuk Operator');
        self::assertStringNotContainsString('href="/audit"', $response->body, 'Menu Log Audit tak boleh tampil untuk Operator');
        self::assertStringContainsString('Tidak ada surat yang menunggu disposisi.', $response->body);
        self::assertStringContainsString('Tidak ada disposisi yang lewat tenggat.', $response->body);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $app = $this->app('operator');
        $this->session->forget(AuthMiddleware::SESSION_KEY);

        $response = $app->handle(new Request('GET', '/pengguna'));

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
    }

    public function test_sesi_menganggur_lebih_dari_60_menit_berakhir(): void
    {
        $app = $this->app('admin');
        $this->session->set(AuthMiddleware::SESSION_KEY, [
            'id' => 7, 'login_at' => time() - 7200, 'last_activity' => time() - 3601,
        ]);

        $response = $app->handle(new Request('GET', '/'));

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
        self::assertFalse($this->session->has(AuthMiddleware::SESSION_KEY));
    }

    public function test_role_middleware_meneruskan_admin_dan_menolak_lainnya(): void
    {
        $mw = new RoleMiddleware(['admin']);
        $next = static fn (Request $r): Response => new Response('lanjut');

        $admin = new Request('GET', '/pengguna');
        $admin->setUser(['id' => 1, 'role' => 'admin']);
        self::assertSame('lanjut', $mw->handle($admin, $next)->body);

        $operator = new Request('GET', '/pengguna');
        $operator->setUser(['id' => 2, 'role' => 'operator']);
        $this->expectException(HttpException::class);
        $mw->handle($operator, $next);
    }
}
