<?php

declare(strict_types=1);

namespace Modules\Pengguna\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Modules\Pengguna\Service\PenggunaService;

final class PenggunaController
{
    public function __construct(private readonly PenggunaService $service, private readonly Session $session)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Pengguna::index', [
            'title' => 'Pengguna',
            'result' => $this->service->list($request->query),
            'peran' => PenggunaService::PERAN,
            'currentUserId' => $request->userId(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Response::view('Pengguna::form', ['title' => 'Tambah Pengguna', 'peran' => PenggunaService::PERAN]);
    }

    public function store(Request $request): Response
    {
        $id = $this->service->create($request->post);
        $user = $this->service->findOrFail($id);
        $this->toast('success', 'Akun ' . $user['username'] . ' tersimpan.');
        return Response::redirect('/pengguna');
    }

    public function deactivate(Request $request, int $id): Response
    {
        $user = $this->service->findOrFail($id);
        $this->service->deactivate($id, (int) $request->userId());
        $this->toast('success', 'Akun ' . $user['username'] . ' dinonaktifkan.');
        return Response::redirect($request->backUrl('/pengguna'));
    }

    public function activate(Request $request, int $id): Response
    {
        $user = $this->service->findOrFail($id);
        $this->service->activate($id);
        $this->toast('success', 'Akun ' . $user['username'] . ' diaktifkan kembali.');
        return Response::redirect($request->backUrl('/pengguna'));
    }

    public function editPassword(Request $request, int $id): Response
    {
        return Response::view('Pengguna::reset_password', [
            'title' => 'Reset Password',
            'target' => $this->service->findOrFail($id),
        ]);
    }

    public function resetPassword(Request $request, int $id): Response
    {
        $user = $this->service->findOrFail($id);
        $this->service->resetPassword($id, $request->post);
        $this->toast('success', 'Password ' . $user['username'] . ' berhasil direset.');
        return Response::redirect('/pengguna');
    }

    private function toast(string $type, string $message): void
    {
        $this->session->flash('toast', ['type' => $type, 'message' => $message]);
    }
}
