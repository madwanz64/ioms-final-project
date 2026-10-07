<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\Category;
use App\Entity\User;
use App\Service\CategoryService;
use App\Service\ValidationException;

final class CategoryController
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('categories/index', [
            'title' => 'Kategori',
            'categories' => $this->categories->all(),
        ]));
    }

    public function create(): Response
    {
        return $this->form(null, []);
    }

    public function store(Request $request): Response
    {
        try {
            $category = $this->categories->create($request->allInput());
        } catch (ValidationException $e) {
            return $this->form(null, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', 'Kategori "' . $category->name . '" berhasil ditambahkan.');

        return Response::redirect('/categories');
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, User $user, array $params): Response
    {
        $category = $this->findOr404($params);

        return $this->form($category, ['name' => $category->name, 'description' => (string) $category->description]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, User $user, array $params): Response
    {
        $category = $this->findOr404($params);
        try {
            $updated = $this->categories->update($category, $request->allInput());
        } catch (ValidationException $e) {
            return $this->form($category, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', 'Kategori "' . $updated->name . '" berhasil diperbarui.');

        return Response::redirect('/categories');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?Category $category, array $old, array $errors = []): Response
    {
        return Response::html($this->view->render('categories/form', [
            'title' => $category === null ? 'Tambah Kategori' : 'Edit Kategori',
            'category' => $category,
            'old' => $old,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $params
     */
    private function findOr404(array $params): Category
    {
        return $this->categories->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();
    }
}
