<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    /** @return list<array{0:string,1:string,2:array{0:class-string,1:string},3:list<string>}> */
    private static function realRoutes(): array
    {
        return require dirname(__DIR__, 2) . '/config/routes.php';
    }

    public function test_mencocokkan_rute_statis_dan_parameter_angka(): void
    {
        $router = new Router([
            ['GET', '/pengguna', ['A', 'index'], ['auth']],
            ['POST', '/pengguna/{id}/nonaktifkan', ['A', 'off'], ['auth']],
        ]);

        self::assertSame(['A', 'index'], $router->match('GET', '/pengguna')['handler']);
        self::assertSame(['A', 'index'], $router->match('GET', '/pengguna/')['handler']);
        $match = $router->match('POST', '/pengguna/42/nonaktifkan');
        self::assertSame(['A', 'off'], $match['handler']);
        self::assertSame([42], $match['params']);
    }

    public function test_parameter_bukan_angka_menghasilkan_404(): void
    {
        $router = new Router([['GET', '/pengguna/{id}/ubah', ['A', 'edit'], ['auth']]]);
        try {
            $router->match('GET', '/pengguna/1%20OR%201/ubah');
            self::fail('Seharusnya 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }
    }

    public function test_method_berbeda_menghasilkan_404(): void
    {
        $router = new Router([['POST', '/logout', ['A', 'logout'], ['auth']]]);
        $this->expectException(HttpException::class);
        $router->match('GET', '/logout');
    }

    public function test_setiap_rute_nyata_bermiddleware_dan_hanya_login_tanpa_auth(): void
    {
        $tanpaAuth = [];
        foreach (self::realRoutes() as [$method, $path, , $middleware]) {
            self::assertNotEmpty($middleware, "Rute {$method} {$path} tanpa middleware");
            if (!in_array('auth', $middleware, true)) {
                $tanpaAuth[] = $method . ' ' . $path;
            }
        }
        sort($tanpaAuth);
        self::assertSame(['GET /login', 'POST /login'], $tanpaAuth);
    }

    public function test_rute_pengguna_dan_master_wajib_role_admin(): void
    {
        $diperiksa = 0;
        foreach (self::realRoutes() as [$method, $path, , $middleware]) {
            if (str_starts_with($path, '/pengguna') || str_starts_with($path, '/master')) {
                self::assertContains('role:admin', $middleware, "Rute {$method} {$path} tanpa role:admin");
                $diperiksa++;
            }
        }
        self::assertGreaterThanOrEqual(21, $diperiksa);
    }

    public function test_tidak_ada_rute_ganda_dan_handler_ada(): void
    {
        $kunci = [];
        foreach (self::realRoutes() as [$method, $path, [$class, $action]]) {
            $kunci[] = $method . ' ' . $path;
            self::assertTrue(method_exists($class, $action), "{$class}::{$action} tidak ada");
        }
        self::assertSame(count($kunci), count(array_unique($kunci)));
    }
}
