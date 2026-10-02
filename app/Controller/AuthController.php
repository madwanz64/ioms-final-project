<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Entity\User;
use App\Service\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function showLogin(Request $request, ?User $user): Response
    {
        if ($user !== null) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->view->render('auth/login', ['email' => '', 'error' => null], null));
    }

    public function login(Request $request): Response
    {
        $email = $request->input('email');
        $user = $this->authService->attempt($email, $request->input('password'));

        if ($user === null) {
            // Satu pesan untuk semua kegagalan (email tidak ada, password salah,
            // akun nonaktif) agar tidak membocorkan bagian yang salah (AUTH-01).
            return Response::html($this->view->render('auth/login', [
                'email' => $email,
                'error' => 'Email atau password salah, atau akun tidak aktif.',
            ], null), 401);
        }

        $this->auth->login($user);
        $this->session->flash('success', 'Selamat datang, ' . $user->name . '.');

        return Response::redirect('/dashboard');
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return Response::redirect('/login');
    }
}
