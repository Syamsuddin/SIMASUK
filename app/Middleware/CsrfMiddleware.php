<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Dijalankan App untuk SEMUA POST sebelum pencocokan rute, sehingga POST apa pun tanpa token = 419. */
final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if ($request->method === 'POST') {
            $given = $request->input(Csrf::FIELD) ?? $request->header('x-csrf-token');
            if (!Csrf::valid($this->session, $given)) {
                throw new HttpException(419);
            }
        }
        return $next($request);
    }
}
