<?php

declare(strict_types=1);

namespace Modules\Audit\Controller;

use App\Core\Request;
use App\Core\Response;
use Modules\Audit\Service\AuditService;

final class AuditController
{
    public function __construct(private readonly AuditService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::view('Audit::index', [
            'title' => 'Log Audit',
            'result' => $this->service->list($request->query),
        ]);
    }
}
