<?php

declare(strict_types=1);

namespace Modules\Dasbor\Controller;

use App\Core\Request;
use App\Core\Response;

/** Dasbor sementara F1; kartu statistik dan tabel kerja dibangun di F4 (docs/03). */
final class DasborController
{
    public function index(Request $request): Response
    {
        return Response::view('Dasbor::index', ['title' => 'Dasbor', 'user' => $request->user()]);
    }
}
