<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Audit;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use Modules\Pengguna\Repository\PenggunaRepository;

final class AuthMiddleware implements Middleware
{
    public const SESSION_KEY = '_auth';
    public const IDLE_DETIK = 60 * 60;
    public const ABSOLUT_DETIK = 10 * 60 * 60;

    public function __construct(
        private readonly Session $session,
        private readonly PenggunaRepository $users,
        private readonly Audit $audit,
        private readonly Logger $logger
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $auth = $this->session->get(self::SESSION_KEY);
        if (!is_array($auth) || !isset($auth['id'], $auth['login_at'], $auth['last_activity'])) {
            return Response::redirect('/login');
        }

        $now = time();
        if ($now - (int) $auth['last_activity'] > self::IDLE_DETIK || $now - (int) $auth['login_at'] > self::ABSOLUT_DETIK) {
            return $this->expel('Sesi berakhir. Silakan masuk kembali.');
        }

        // Diambil ulang dari DB tiap request agar penonaktifan akun langsung berlaku.
        $user = $this->users->findById((int) $auth['id']);
        if ($user === null || (int) $user['is_active'] !== 1) {
            return $this->expel('Akun Anda tidak aktif. Hubungi Admin.');
        }

        $auth['last_activity'] = $now;
        $this->session->set(self::SESSION_KEY, $auth);

        unset($user['password_hash']);
        $request->setUser($user);
        $this->audit->setContext((int) $user['id'], $request->ip);
        $this->logger->setRequestContext([
            'user_id' => (int) $user['id'],
            'method' => $request->method,
            'path' => $request->path,
        ]);
        View::share('authUser', $user);

        return $next($request);
    }

    private function expel(string $message): Response
    {
        $this->session->invalidate();
        $this->session->flash('toast', ['type' => 'info', 'message' => $message]);
        return Response::redirect('/login');
    }
}
