<?php

declare(strict_types=1);

namespace Modules\Master\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Modules\Master\Service\KlasifikasiService;

final class KlasifikasiController
{
    public function __construct(private readonly KlasifikasiService $service, private readonly Session $session)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Master::klasifikasi_index', [
            'title' => 'Klasifikasi Arsip',
            'result' => $this->service->list($request->query),
        ]);
    }

    public function create(Request $request): Response
    {
        return Response::view('Master::klasifikasi_form', ['title' => 'Tambah Klasifikasi', 'item' => null]);
    }

    public function store(Request $request): Response
    {
        $id = $this->service->create($request->post);
        $this->toast('Klasifikasi ' . $this->service->findOrFail($id)['kode'] . ' tersimpan.');
        return Response::redirect('/master/klasifikasi');
    }

    public function edit(Request $request, int $id): Response
    {
        return Response::view('Master::klasifikasi_form', [
            'title' => 'Ubah Klasifikasi',
            'item' => $this->service->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post);
        $this->toast('Klasifikasi ' . $this->service->findOrFail($id)['kode'] . ' tersimpan.');
        return Response::redirect('/master/klasifikasi');
    }

    public function deactivate(Request $request, int $id): Response
    {
        $this->service->setActive($id, false);
        $this->toast('Klasifikasi ' . $this->service->findOrFail($id)['kode'] . ' dinonaktifkan.');
        return Response::redirect($request->backUrl('/master/klasifikasi'));
    }

    public function activate(Request $request, int $id): Response
    {
        $this->service->setActive($id, true);
        $this->toast('Klasifikasi ' . $this->service->findOrFail($id)['kode'] . ' diaktifkan kembali.');
        return Response::redirect($request->backUrl('/master/klasifikasi'));
    }

    private function toast(string $message): void
    {
        $this->session->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
