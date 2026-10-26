<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements Middleware
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || !in_array($user['role'], $this->roles, true)) {
            throw new HttpException(403);
        }
        return $next($request);
    }
}
