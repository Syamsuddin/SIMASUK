<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface Middleware
{
    /** @param \Closure(Request):Response $next */
    public function handle(Request $request, \Closure $next): Response;
}
