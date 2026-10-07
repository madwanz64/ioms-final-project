<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Entity\User;
use App\Service\UserService;
use App\Service\ValidationException;

/**
 * Profil milik user yang sedang login (§1.2: semua role). Selalu memakai user dari
 * session — tidak ada parameter id, sehingga tidak bisa dipakai mengubah akun lain.
 */
final class ProfileController
{
    public function __construct(
        private readonly UserService $users,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function show(Request $request, User $user): Response
    {
        return $this->form($user, ['name' => $user->name]);
    }

    public function update(Request $request, User $user): Response
    {
        try {
            $result = $this->users->updateOwnProfile($user, $request->allInput());
        } catch (ValidationException $e) {
            // Password (lama maupun baru) tidak pernah dikirim balik ke browser.
            return $this->form($user, ['name' => $request->input('name')], $e->errors);
        }

        if ($result['passwordChanged']) {
            // Kredensial berubah -> ID session baru, seperti saat login.
            $this->session->regenerate();
        }
        $this->session->flash('success', $result['passwordChanged'] ? 'Profil dan password berhasil diperbarui.' : 'Profil berhasil diperbarui.');

        return Response::redirect('/profile');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(User $user, array $old, array $errors = []): Response
    {
        return Response::html($this->view->render('profile/index', [
            'title' => 'Profil Saya',
            'profile' => $user,
            'old' => $old,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }
}
