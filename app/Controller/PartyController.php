<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\Party;
use App\Entity\PartyType;
use App\Entity\User;
use App\Service\PartyService;
use App\Service\ValidationException;

/**
 * Halaman Supplier dan Customer. Composition root membuat dua instance:
 * satu dengan PartyType::Supplier, satu dengan PartyType::Customer.
 */
final class PartyController
{
    public function __construct(
        private readonly PartyType $type,
        private readonly PartyService $parties,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('parties/index', [
            'title' => $this->type->label(),
            'type' => $this->type,
            'parties' => $this->parties->all(),
        ]));
    }

    public function create(): Response
    {
        return $this->form(null, ['active' => '1']);
    }

    public function store(Request $request): Response
    {
        try {
            $party = $this->parties->create($request->allInput());
        } catch (ValidationException $e) {
            return $this->form(null, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', $this->type->label() . ' "' . $party->name . '" berhasil ditambahkan.');

        return Response::redirect($this->type->path());
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, User $user, array $params): Response
    {
        $party = $this->findOr404($params);

        return $this->form($party, [
            'name' => $party->name,
            'contact' => $party->contact,
            'address' => $party->address,
            'active' => $party->active ? '1' : '0',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, User $user, array $params): Response
    {
        $party = $this->findOr404($params);
        try {
            $updated = $this->parties->update($party, $request->allInput());
        } catch (ValidationException $e) {
            return $this->form($party, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', $this->type->label() . ' "' . $updated->name . '" berhasil diperbarui.');

        return Response::redirect($this->type->path());
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?Party $party, array $old, array $errors = []): Response
    {
        return Response::html($this->view->render('parties/form', [
            'title' => ($party === null ? 'Tambah ' : 'Edit ') . $this->type->label(),
            'type' => $this->type,
            'party' => $party,
            'old' => $old,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $params
     */
    private function findOr404(array $params): Party
    {
        return $this->parties->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();
    }
}
