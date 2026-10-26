<?php

declare(strict_types=1);

namespace Modules\Dasbor\Controller;

use App\Core\Request;
use App\Core\Response;
use Modules\Dasbor\Service\DasborService;

final class DasborController
{
    public function __construct(private readonly DasborService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Dasbor::index', [
            'title' => 'Dasbor',
            'user' => $request->user(),
            'ringkasan' => $this->service->ringkasan(),
        ]);
    }
}
