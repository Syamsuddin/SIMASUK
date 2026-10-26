<?php

declare(strict_types=1);

namespace Modules\Disposisi\Controller;

use App\Core\DomainRuleException;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Modules\Agenda\Service\SuratMasukService;
use Modules\Disposisi\Service\DisposisiService;

final class DisposisiController
{
    public function __construct(
        private readonly DisposisiService $service,
        private readonly SuratMasukService $surat,
        private readonly Session $session
    ) {
    }

    /** Formulir Catat Disposisi; dengan ?induk={id} menjadi Disposisi Lanjutan. */
    public function create(Request $request, int $id): Response
    {
        $induk = $request->query['induk'] ?? null;
        $indukId = is_string($induk) && preg_match('/^\d{1,10}$/', $induk) === 1 ? (int) $induk : null;
        if ($induk !== null && $indukId === null) {
            throw new HttpException(404);
        }
        try {
            $context = $this->service->formContext($id, $indukId);
        } catch (DomainRuleException $e) {
            $this->session->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
            return Response::redirect('/surat-masuk/' . $id);
        }
        $judul = $context['induk'] === null ? 'Catat Disposisi' : 'Disposisi Lanjutan';
        return Response::view('Disposisi::form', [
            'title' => $judul . ' — Surat ' . SuratMasukService::nomor($context['surat']),
            'judul' => $judul,
            'surat' => $context['surat'],
            'induk' => $context['induk'],
            'units' => $context['units'],
            'isAdmin' => ($request->user()['role'] ?? '') === 'admin',
        ]);
    }

    public function store(Request $request, int $id): Response
    {
        $ids = $this->service->create($id, $request->post, $request->userId());
        $nomor = SuratMasukService::nomor($this->surat->findOrFail($id));
        $this->toast('Disposisi surat ' . $nomor . ' tersimpan untuk ' . count($ids) . ' unit.');
        return Response::redirect('/surat-masuk/' . $id);
    }

    public function markDone(Request $request, int $id): Response
    {
        $suratId = $this->service->markDone($id, $request->post);
        $this->toast('Disposisi ditandai selesai.');
        return Response::redirect('/surat-masuk/' . $suratId);
    }

    public function closeSurat(Request $request, int $id): Response
    {
        $ditutup = $this->service->closeSurat($id, $request->userId());
        $nomor = SuratMasukService::nomor($this->surat->findOrFail($id));
        $this->toast('Surat ' . $nomor . ' ditandai selesai.' . ($ditutup > 0 ? ' ' . $ditutup . ' disposisi ikut ditutup.' : ''));
        return Response::redirect('/surat-masuk/' . $id);
    }

    private function toast(string $message): void
    {
        $this->session->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
