<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\User;
use App\Service\UserService;
use App\Service\ValidationException;

/**
 * Administrasi user (USR-01) — seluruh route di-guard Role::Admin di router.
 */
final class UserController
{
    public function __construct(
        private readonly UserService $users,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('users/index', [
            'title' => 'User',
            'users' => $this->users->all(),
        ]));
    }

    public function create(): Response
    {
        return $this->form(null, ['active' => '1']);
    }

    public function store(Request $request): Response
    {
        try {
            $user = $this->users->create($request->allInput());
        } catch (ValidationException $e) {
            return $this->form(null, $this->withoutPasswords($request->allInput()), $e->errors);
        }
        $this->session->flash('success', 'User ' . $user->email . ' berhasil ditambahkan.');

        return Response::redirect('/users');
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, User $actor, array $params): Response
    {
        $user = $this->findOr404($params);

        return $this->form($user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'active' => $user->active ? '1' : '0',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, User $actor, array $params): Response
    {
        $user = $this->findOr404($params);
        try {
            $updated = $this->users->update($user, $request->allInput(), $actor);
        } catch (ValidationException $e) {
            return $this->form($user, $this->withoutPasswords($request->allInput()), $e->errors);
        }
        $this->session->flash('success', 'User ' . $updated->email . ' berhasil diperbarui.');

        return Response::redirect('/users');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?User $user, array $old, array $errors = []): Response
    {
        return Response::html($this->view->render('users/form', [
            'title' => $user === null ? 'Tambah User' : 'Edit User',
            'user' => $user,
            'old' => $old,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * Password tidak pernah dikirim balik ke browser saat validasi gagal.
     *
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function withoutPasswords(array $input): array
    {
        unset($input['password'], $input['password_confirmation']);

        return $input;
    }

    /**
     * @param array<string, string> $params
     */
    private function findOr404(array $params): User
    {
        return $this->users->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();
    }
}
