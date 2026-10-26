<?php

declare(strict_types=1);

namespace Modules\Master\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Modules\Master\Service\UnitKerjaService;

final class UnitKerjaController
{
    public function __construct(private readonly UnitKerjaService $service, private readonly Session $session)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Master::unit_index', ['title' => 'Unit Kerja', 'units' => $this->service->tree()]);
    }

    public function create(Request $request): Response
    {
        return Response::view('Master::unit_form', [
            'title' => 'Tambah Unit Kerja',
            'item' => null,
            'parents' => $this->service->parentOptions(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->service->create($request->post);
        $this->toast('Unit ' . $this->service->findOrFail($id)['nama'] . ' tersimpan.');
        return Response::redirect('/master/unit');
    }

    public function edit(Request $request, int $id): Response
    {
        return Response::view('Master::unit_form', [
            'title' => 'Ubah Unit Kerja',
            'item' => $this->service->findOrFail($id),
            'parents' => $this->service->parentOptions($id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post);
        $this->toast('Unit ' . $this->service->findOrFail($id)['nama'] . ' tersimpan.');
        return Response::redirect('/master/unit');
    }

    public function deactivate(Request $request, int $id): Response
    {
        $this->service->setActive($id, false);
        $this->toast('Unit ' . $this->service->findOrFail($id)['nama'] . ' dinonaktifkan.');
        return Response::redirect('/master/unit');
    }

    public function activate(Request $request, int $id): Response
    {
        $this->service->setActive($id, true);
        $this->toast('Unit ' . $this->service->findOrFail($id)['nama'] . ' diaktifkan kembali.');
        return Response::redirect('/master/unit');
    }

    private function toast(string $message): void
    {
        $this->session->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
