<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Entity\StockLevel;
use App\Entity\StockMovement;
use PDO;

final class MySqlProductRepository implements ProductRepositoryInterface
{
    /**
     * ORDER BY tidak bisa di-bind sebagai parameter, jadi diambil dari peta
     * whitelist ini — input user hanya memilih kunci, tidak pernah masuk SQL.
     */
    private const ORDER_BY = [
        'name_asc' => 'p.name ASC, p.sku ASC',
        'name_desc' => 'p.name DESC, p.sku ASC',
        'stock_asc' => 'total_stock ASC, p.name ASC',
    ];

    private const PRODUCT_COLUMNS = 'sku, name, category_id, unit, buy_price, sell_price, reorder_point, image_url, active';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(ProductSearchCriteria $criteria): PaginatedResult
    {
        $where = [];
        $params = [];
        if ($criteria->search !== '') {
            // Escape wildcard LIKE agar input "%" atau "_" dicari sebagai karakter biasa.
            $like = '%' . addcslashes($criteria->search, '%_\\') . '%';
            $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($criteria->categoryId !== null) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $criteria->categoryId;
        }
        if ($criteria->onlyActive) {
            $where[] = 'p.active = 1';
        }
        $having = match ($criteria->stockStatus) {
            'low' => ' HAVING total_stock < p.reorder_point',
            'normal' => ' HAVING total_stock >= p.reorder_point',
            default => '',
        };

        $baseSql = 'SELECT p.sku, p.name, c.name AS category_name, p.sell_price, p.reorder_point, p.active,
                           COALESCE(SUM(ps.quantity), 0) AS total_stock
                    FROM products p
                    JOIN categories c ON c.id = p.category_id
                    LEFT JOIN product_stock ps ON ps.product_sku = p.sku'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' GROUP BY p.sku, c.name' . $having;

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM (' . $baseSql . ') AS filtered');
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        // Halaman di luar jangkauan (mis. ?page=99) dijepit ke halaman terakhir.
        $lastPage = max(1, (int) ceil($total / $criteria->perPage));
        $page = min($criteria->page, $lastPage);

        $stmt = $this->pdo->prepare($baseSql . ' ORDER BY ' . self::ORDER_BY[$criteria->sort] . ' LIMIT :limit OFFSET :offset');
        $this->bindAll($stmt, $params);
        $stmt->bindValue('limit', $criteria->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $criteria->perPage, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(static fn (array $row): ProductSummary => new ProductSummary(
            (string) $row['sku'],
            (string) $row['name'],
            (string) $row['category_name'],
            self::money($row['sell_price']),
            (int) $row['reorder_point'],
            (int) $row['total_stock'],
            (bool) $row['active'],
        ), $stmt->fetchAll());

        return new PaginatedResult(array_values($items), $total, $page, $criteria->perPage);
    }

    public function findBySku(string $sku): ?Product
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::PRODUCT_COLUMNS . ' FROM products WHERE sku = :sku');
        $stmt->execute(['sku' => $sku]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return new Product(
            (string) $row['sku'],
            (string) $row['name'],
            (int) $row['category_id'],
            (string) $row['unit'],
            self::money($row['buy_price']),
            self::money($row['sell_price']),
            (int) $row['reorder_point'],
            $row['image_url'] === null ? null : (string) $row['image_url'],
            (bool) $row['active'],
        );
    }

    public function skuExists(string $sku): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM products WHERE sku = :sku');
        $stmt->execute(['sku' => $sku]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(Product $product): void
    {
        Database::transactional($this->pdo, function () use ($product): void {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (' . self::PRODUCT_COLUMNS . ')
                 VALUES (:sku, :name, :category_id, :unit, :buy_price, :sell_price, :reorder_point, :image_url, :active)'
            );
            $stmt->execute($this->productParams($product));

            // Setiap produk wajib punya baris stok per gudang (WH-01). Quantity
            // awal 0 — stok hanya bertambah lewat goods receipt yang menulis ledger.
            $stock = $this->pdo->prepare(
                'INSERT INTO product_stock (product_sku, warehouse_id, quantity)
                 SELECT :sku, id, 0 FROM warehouses'
            );
            $stock->execute(['sku' => $product->sku]);
        });
    }

    public function update(Product $product): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET name = :name, category_id = :category_id, unit = :unit, buy_price = :buy_price,
                 sell_price = :sell_price, reorder_point = :reorder_point, image_url = :image_url, active = :active
             WHERE sku = :sku'
        );
        $stmt->execute($this->productParams($product));
    }

    public function stockLevels(string $sku): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.name, ps.quantity
             FROM product_stock ps
             JOIN warehouses w ON w.id = ps.warehouse_id
             WHERE ps.product_sku = :sku
             ORDER BY w.name'
        );
        $stmt->execute(['sku' => $sku]);

        return array_values(array_map(
            static fn (array $row): StockLevel => new StockLevel((int) $row['id'], (string) $row['name'], (int) $row['quantity']),
            $stmt->fetchAll()
        ));
    }

    public function recentMovements(string $sku, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.created_at, w.name AS warehouse_name, l.movement_type, l.quantity, l.ref_id
             FROM stock_ledger l
             JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.product_sku = :sku
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('sku', $sku);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map(static fn (array $row): StockMovement => new StockMovement(
            (string) $row['created_at'],
            (string) $row['warehouse_name'],
            (string) $row['movement_type'],
            (int) $row['quantity'],
            (string) $row['ref_id'],
        ), $stmt->fetchAll()));
    }

    /**
     * @param array<string, string|int> $params
     */
    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /**
     * @return array<string, string|int|null>
     */
    private function productParams(Product $product): array
    {
        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->categoryId,
            'unit' => $product->unit,
            'buy_price' => $product->buyPrice,
            'sell_price' => $product->sellPrice,
            'reorder_point' => $product->reorderPoint,
            'image_url' => $product->imageUrl,
            'active' => $product->active ? 1 : 0,
        ];
    }

    /**
     * Kolom harga DECIMAL(14,2) dikembalikan PDO sebagai string ("45000.00").
     */
    private static function money(mixed $value): int
    {
        return (int) round((float) $value);
    }
}
