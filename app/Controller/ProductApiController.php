<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Entity\Role;
use App\Entity\StockLevel;
use App\Entity\User;
use App\Service\ProductService;

/**
 * Endpoint JSON (API-01) — kontrak:
 *
 *   GET /api/products/{sku}/availability
 *   200 {"sku", "name", "unit", "active", "reorderPoint", "totalStock", "lowStock",
 *        "warehouses": [{"id", "name", "quantity"}]}
 *   401 {"error": "unauthenticated"}  — tanpa session login (dicek router, sama seperti halaman)
 *   404 {"error": "not_found"}        — SKU tidak ada (atau produk nonaktif untuk Sales)
 */
final class ProductApiController
{
    public function __construct(private readonly ProductService $products)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function availability(Request $request, User $user, array $params): Response
    {
        $product = $this->products->find(strtoupper($params['sku']));
        if ($product === null || (!$product->active && $user->role === Role::Sales)) {
            return Response::json(['error' => 'not_found', 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $levels = $this->products->stockLevels($product->sku);
        $total = array_sum(array_map(static fn (StockLevel $l): int => $l->quantity, $levels));

        return Response::json([
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'active' => $product->active,
            'reorderPoint' => $product->reorderPoint,
            'totalStock' => $total,
            'lowStock' => $total < $product->reorderPoint,
            'warehouses' => array_map(
                static fn (StockLevel $l): array => ['id' => $l->warehouseId, 'name' => $l->warehouseName, 'quantity' => $l->quantity],
                $levels,
            ),
        ]);
    }
}
