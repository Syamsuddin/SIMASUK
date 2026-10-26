<?php

declare(strict_types=1);

namespace Modules\Auth\Controller;

use App\Core\Csrf;
use App\Core\DomainRuleException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use Modules\Auth\Service\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth, private readonly Session $session)
    {
    }

    public function showLogin(Request $request): Response
    {
        return Response::view('Auth::login', ['title' => 'Masuk'], 'layout/auth');
    }

    public function login(Request $request): Response
    {
        try {
            $user = $this->auth->attempt($request->post);
        } catch (DomainRuleException $e) {
            $this->session->flash('errors', ['_form' => $e->getMessage()]);
            $this->session->flash('old', ['username' => (string) $request->input('username', '')]);
            return Response::redirect('/login');
        }

        $this->session->regenerate();
        Csrf::rotate($this->session);
        $now = time();
        $this->session->set(AuthMiddleware::SESSION_KEY, [
            'id' => (int) $user['id'],
            'login_at' => $now,
            'last_activity' => $now,
        ]);
        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout((int) $request->userId());
        $this->session->invalidate();
        $this->session->flash('toast', ['type' => 'info', 'message' => 'Anda telah keluar.']);
        return Response::redirect('/login');
    }

    public function showPassword(Request $request): Response
    {
        return Response::view('Auth::password', ['title' => 'Ubah Password']);
    }

    public function updatePassword(Request $request): Response
    {
        $this->auth->changePassword((int) $request->userId(), $request->post);
        $this->session->flash('toast', ['type' => 'success', 'message' => 'Password berhasil diubah.']);
        return Response::redirect('/');
    }
}
