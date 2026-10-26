<?php

declare(strict_types=1);

use Modules\Auth\Controller\AuthController;
use Modules\Dasbor\Controller\DasborController;
use Modules\Master\Controller\KlasifikasiController;
use Modules\Master\Controller\UnitKerjaController;
use Modules\Pengguna\Controller\PenggunaController;

// [method, path, [Controller::class, 'aksi'], middleware[]] — setiap rute wajib bermiddleware (docs/21).
// CSRF dicek App untuk semua POST sebelum rute dicocokkan.
$admin = ['auth', 'role:admin'];

return [
    ['GET', '/login', [AuthController::class, 'showLogin'], ['guest']],
    ['POST', '/login', [AuthController::class, 'login'], ['guest']],
    ['POST', '/logout', [AuthController::class, 'logout'], ['auth']],
    ['GET', '/ubah-password', [AuthController::class, 'showPassword'], ['auth']],
    ['POST', '/ubah-password', [AuthController::class, 'updatePassword'], ['auth']],

    ['GET', '/', [DasborController::class, 'index'], ['auth']],

    ['GET', '/pengguna', [PenggunaController::class, 'index'], $admin],
    ['GET', '/pengguna/baru', [PenggunaController::class, 'create'], $admin],
    ['POST', '/pengguna', [PenggunaController::class, 'store'], $admin],
    ['POST', '/pengguna/{id}/nonaktifkan', [PenggunaController::class, 'deactivate'], $admin],
    ['POST', '/pengguna/{id}/aktifkan', [PenggunaController::class, 'activate'], $admin],
    ['GET', '/pengguna/{id}/reset-password', [PenggunaController::class, 'editPassword'], $admin],
    ['POST', '/pengguna/{id}/reset-password', [PenggunaController::class, 'resetPassword'], $admin],

    ['GET', '/master/klasifikasi', [KlasifikasiController::class, 'index'], $admin],
    ['GET', '/master/klasifikasi/baru', [KlasifikasiController::class, 'create'], $admin],
    ['POST', '/master/klasifikasi', [KlasifikasiController::class, 'store'], $admin],
    ['GET', '/master/klasifikasi/{id}/ubah', [KlasifikasiController::class, 'edit'], $admin],
    ['POST', '/master/klasifikasi/{id}/ubah', [KlasifikasiController::class, 'update'], $admin],
    ['POST', '/master/klasifikasi/{id}/nonaktifkan', [KlasifikasiController::class, 'deactivate'], $admin],
    ['POST', '/master/klasifikasi/{id}/aktifkan', [KlasifikasiController::class, 'activate'], $admin],

    ['GET', '/master/unit', [UnitKerjaController::class, 'index'], $admin],
    ['GET', '/master/unit/baru', [UnitKerjaController::class, 'create'], $admin],
    ['POST', '/master/unit', [UnitKerjaController::class, 'store'], $admin],
    ['GET', '/master/unit/{id}/ubah', [UnitKerjaController::class, 'edit'], $admin],
    ['POST', '/master/unit/{id}/ubah', [UnitKerjaController::class, 'update'], $admin],
    ['POST', '/master/unit/{id}/nonaktifkan', [UnitKerjaController::class, 'deactivate'], $admin],
    ['POST', '/master/unit/{id}/aktifkan', [UnitKerjaController::class, 'activate'], $admin],
];
