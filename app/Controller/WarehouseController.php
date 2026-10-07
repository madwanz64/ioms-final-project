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
use App\Entity\Warehouse;
use App\Service\ValidationException;
use App\Service\WarehouseService;

final class WarehouseController
{
    public function __construct(
        private readonly WarehouseService $warehouses,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('warehouses/index', [
            'title' => 'Gudang',
            'warehouses' => $this->warehouses->all(),
            'stockTotals' => $this->warehouses->stockTotals(),
        ]));
    }

    public function create(): Response
    {
        return $this->form(null, ['active' => '1']);
    }

    public function store(Request $request): Response
    {
        try {
            $warehouse = $this->warehouses->create($request->allInput());
        } catch (ValidationException $e) {
            return $this->form(null, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', 'Gudang "' . $warehouse->name . '" berhasil ditambahkan dengan stok 0 untuk setiap produk.');

        return Response::redirect('/warehouses');
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, User $user, array $params): Response
    {
        $warehouse = $this->findOr404($params);

        return $this->form($warehouse, [
            'name' => $warehouse->name,
            'location' => $warehouse->location,
            'active' => $warehouse->active ? '1' : '0',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, User $user, array $params): Response
    {
        $warehouse = $this->findOr404($params);
        try {
            $updated = $this->warehouses->update($warehouse, $request->allInput());
        } catch (ValidationException $e) {
            return $this->form($warehouse, $request->allInput(), $e->errors);
        }
        $this->session->flash('success', 'Gudang "' . $updated->name . '" berhasil diperbarui.');

        return Response::redirect('/warehouses');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?Warehouse $warehouse, array $old, array $errors = []): Response
    {
        return Response::html($this->view->render('warehouses/form', [
            'title' => $warehouse === null ? 'Tambah Gudang' : 'Edit Gudang',
            'warehouse' => $warehouse,
            'old' => $old,
            'errors' => $errors,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, string> $params
     */
    private function findOr404(array $params): Warehouse
    {
        return $this->warehouses->find(Router::intParam($params, 'id')) ?? throw HttpException::notFound();
    }
}
