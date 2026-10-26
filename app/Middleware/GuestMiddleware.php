<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Halaman login hanya untuk yang belum masuk. */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if (is_array($this->session->get(AuthMiddleware::SESSION_KEY))) {
            return Response::redirect('/');
        }
        return $next($request);
    }
}
