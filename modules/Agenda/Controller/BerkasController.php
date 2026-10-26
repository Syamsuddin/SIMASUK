<?php

declare(strict_types=1);

namespace Modules\Agenda\Controller;

use App\Core\Request;
use App\Core\Response;
use Modules\Agenda\Service\SuratMasukService;

/** Unduh berkas scan; tidak ada URL statis ke STORAGE_PATH (docs/21). */
final class BerkasController
{
    public function __construct(private readonly SuratMasukService $service)
    {
    }

    public function download(Request $request, int $id): Response
    {
        $file = $this->service->berkasForDownload($id, (string) ($request->user()['role'] ?? ''));
        return new Response($file['isi'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => self::disposition($file['nama_asli']),
            'Content-Length' => (string) strlen($file['isi']),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Nama ASCII sebagai cadangan + nama UTF-8 asli (RFC 6266). */
    public static function disposition(string $name): string
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]/u', '_', $name);
        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
