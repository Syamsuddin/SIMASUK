<?php

declare(strict_types=1);

namespace Modules\Agenda\Controller;

use App\Core\FileStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use Modules\Agenda\Service\SuratMasukService;

final class SuratMasukController
{
    public function __construct(private readonly SuratMasukService $service, private readonly Session $session)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Agenda::index', [
            'title' => 'Surat Masuk',
            'result' => $this->service->list($request->query),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $detail = $this->service->detail($id);
        return Response::view('Agenda::detail', [
            'title' => 'Surat ' . SuratMasukService::nomor($detail['surat']),
            'surat' => $detail['surat'],
            'berkas' => $detail['berkas'],
            'isAdmin' => ($request->user()['role'] ?? '') === 'admin',
        ]);
    }

    public function create(Request $request): Response
    {
        return Response::view('Agenda::form', [
            'title' => 'Catat Surat',
            'item' => null,
            'klasifikasi' => $this->service->klasifikasiOptions(),
            'duplikat' => $this->duplicates(null),
            'maxMb' => $this->service->uploadLimitMb(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->service->create(
            $request->post,
            FileStore::uploads($request->files['berkas'] ?? null),
            $request->userId()
        );
        $this->toast('Surat ' . SuratMasukService::nomor($this->service->findOrFail($id)) . ' tersimpan.');
        return Response::redirect('/surat-masuk/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $item = $this->service->findOrFail($id);
        if ($item['status'] === 'dibatalkan') {
            $this->session->flash('toast', ['type' => 'danger', 'message' => SuratMasukService::PESAN_BATAL_TAK_DIUBAH]);
            return Response::redirect('/surat-masuk/' . $id);
        }
        return Response::view('Agenda::form', [
            'title' => 'Ubah Surat ' . SuratMasukService::nomor($item),
            'item' => $item,
            'klasifikasi' => $this->service->klasifikasiOptions($item),
            'duplikat' => $this->duplicates($id),
            'maxMb' => $this->service->uploadLimitMb(),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->service->update($id, $request->post, $request->userId());
        $this->toast('Surat ' . SuratMasukService::nomor($this->service->findOrFail($id)) . ' tersimpan.');
        return Response::redirect('/surat-masuk/' . $id);
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->service->cancel($id, $request->post, $request->userId());
        $this->toast('Surat ' . SuratMasukService::nomor($this->service->findOrFail($id)) . ' dibatalkan.');
        return Response::redirect('/surat-masuk/' . $id);
    }

    /**
     * Tautan surat mirip hanya dihitung saat formulir kembali dengan peringatan dugaan ganda.
     *
     * @return list<array<string,mixed>>
     */
    private function duplicates(?int $exceptId): array
    {
        $errors = View::shared('errors', []);
        if (!is_array($errors) || !isset($errors['tetap_simpan'])) {
            return [];
        }
        $old = View::shared('old', []);
        return $this->service->duplicatesFor(is_array($old) ? $old : [], $exceptId);
    }

    private function toast(string $message): void
    {
        $this->session->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
