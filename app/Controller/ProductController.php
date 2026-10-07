<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\ProductSearchCriteria;
use App\Service\ProductService;
use App\Service\ValidationException;

final class ProductController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request, User $user): Response
    {
        $criteria = ProductSearchCriteria::fromQuery($request->allQuery());
        if ($user->role === Role::Sales) {
            // Sales hanya melihat katalog yang bisa dijual (§1.2).
            $criteria = $criteria->withOnlyActive();
        }

        return Response::html($this->view->render('products/index', [
            'title' => 'Produk',
            'result' => $this->products->search($criteria),
            'criteria' => $criteria,
            'categories' => $this->products->categories(),
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, User $user, array $params): Response
    {
        $product = $this->findOr404($params['sku']);
        if (!$product->active && $user->role === Role::Sales) {
            throw HttpException::notFound();
        }
        $canSeeBuyPrice = $user->hasRole(Role::Admin, Role::WarehouseStaff);

        return Response::html($this->view->render('products/show', [
            'title' => $product->name,
            'product' => $product,
            'category' => $this->categoryName($product->categoryId),
            'stockLevels' => $this->products->stockLevels($product->sku),
            'movements' => $this->products->recentMovements($product->sku),
            'priceHistory' => $this->products->priceHistory($product->sku, $canSeeBuyPrice),
            'canSeeBuyPrice' => $canSeeBuyPrice,
        ]));
    }

    public function create(): Response
    {
        return $this->form(null, ['active' => '1']);
    }

    public function store(Request $request, User $user): Response
    {
        try {
            $product = $this->products->create($request->allInput(), $request->file('image'), $user);
        } catch (ValidationException $e) {
            return $this->form(null, $request->allInput(), $e->errors);
        }

        $this->session->flash('success', 'Produk ' . $product->sku . ' berhasil ditambahkan.');

        return Response::redirect('/products/' . rawurlencode($product->sku));
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, User $user, array $params): Response
    {
        $product = $this->findOr404($params['sku']);

        return $this->form($product, [
            'name' => $product->name,
            'category_id' => (string) $product->categoryId,
            'unit' => $product->unit,
            'buy_price' => (string) $product->buyPrice,
            'sell_price' => (string) $product->sellPrice,
            'reorder_point' => (string) $product->reorderPoint,
            'active' => $product->active ? '1' : '0',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, User $user, array $params): Response
    {
        $product = $this->findOr404($params['sku']);
        try {
            $this->products->update($product, $request->allInput(), $request->file('image'), $user);
        } catch (ValidationException $e) {
            return $this->form($product, $request->allInput(), $e->errors);
        }

        $this->session->flash('success', 'Produk ' . $product->sku . ' berhasil diperbarui.');

        return Response::redirect('/products/' . rawurlencode($product->sku));
    }

    /**
     * @param array<string, string> $old input yang dipertahankan saat validasi gagal (VAL-01)
     * @param array<string, string> $errors
     */
    private function form(?Product $product, array $old, array $errors = []): Response
    {
        $html = $this->view->render('products/form', [
            'title' => $product === null ? 'Tambah Produk' : 'Edit Produk',
            'product' => $product,
            'old' => $old,
            'errors' => $errors,
            'categories' => $this->products->categories(),
        ]);

        return Response::html($html, $errors === [] ? 200 : 422);
    }

    private function findOr404(string $sku): Product
    {
        return $this->products->find($sku) ?? throw HttpException::notFound();
    }

    private function categoryName(int $categoryId): string
    {
        foreach ($this->products->categories() as $category) {
            if ($category->id === $categoryId) {
                return $category->name;
            }
        }

        return '-';
    }
}
