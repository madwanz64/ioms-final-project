<?php

declare(strict_types=1);

/**
 * Front controller & composition root. Seluruh object graph dirakit manual di
 * sini lewat constructor injection (tanpa DI container, sesuai brief §4).
 */

use App\Controller\AuthController;
use App\Controller\CategoryController;
use App\Controller\DashboardController;
use App\Controller\PartyController;
use App\Controller\ProductController;
use App\Controller\PurchaseOrderController;
use App\Controller\SalesOrderController;
use App\Controller\UserController;
use App\Controller\WarehouseController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\PartyType;
use App\Entity\Role;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlPartyRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Repository\PdoTransactionManager;
use App\Service\AuthService;
use App\Service\CategoryService;
use App\Service\DashboardService;
use App\Service\LocalImageStorage;
use App\Service\PartyService;
use App\Service\ProductService;
use App\Service\PurchaseOrderService;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use App\Service\StockService;
use App\Service\UserService;
use App\Service\WarehouseService;

// Built-in server PHP (`composer serve`): biarkan file statis dilayani langsung.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_file($file) && basename($file) !== 'index.php') {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

// ERR-01: error PHP & stack trace tidak pernah tampil ke user, hanya ke log server.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$config = require dirname(__DIR__) . '/config/config.php';

$session = new Session();
$session->start(($_SERVER['HTTPS'] ?? '') === 'on');
$csrf = new Csrf($session);
$view = new View($config['views']);
$request = Request::fromGlobals();

try {
    $pdo = Database::connect($config['db']);

    $users = new MySqlUserRepository($pdo);
    $categories = new MySqlCategoryRepository($pdo);
    $productRepository = new MySqlProductRepository($pdo);

    $auth = new Auth($session, $users);
    $productService = new ProductService(
        $productRepository,
        $categories,
        new LocalImageStorage($config['upload']['dir'], $config['upload']['url_prefix'], $config['upload']['max_bytes']),
    );

    $view->share('currentUser', $auth->user());
    $view->share('csrfToken', $csrf->token());
    $view->share('currentPath', $request->path);
    $view->share('flashes', $session->pullFlashes());
    $view->share('uploadMaxBytes', $config['upload']['max_bytes']);

    $authController = new AuthController(new AuthService($users), $auth, $session, $view);
    $dashboardController = new DashboardController(new DashboardService($productRepository), $view);
    $productController = new ProductController($productService, $session, $view);
    $warehouseRepository = new MySqlWarehouseRepository($pdo);
    $supplierRepository = new MySqlPartyRepository($pdo, PartyType::Supplier);
    $customerRepository = new MySqlPartyRepository($pdo, PartyType::Customer);
    $stockRepository = new MySqlStockRepository($pdo);
    $stockService = new StockService($stockRepository);
    $transactions = new PdoTransactionManager($pdo);
    $today = new DateTimeImmutable('today');
    $salesOrderPolicy = new SalesOrderPolicy();

    $categoryController = new CategoryController(new CategoryService($categories), $session, $view);
    $warehouseController = new WarehouseController(new WarehouseService($warehouseRepository), $session, $view);
    $supplierController = new PartyController(PartyType::Supplier, new PartyService($supplierRepository), $session, $view);
    $customerController = new PartyController(PartyType::Customer, new PartyService($customerRepository), $session, $view);
    $userController = new UserController(new UserService($users), $session, $view);
    $purchaseOrderController = new PurchaseOrderController(
        new PurchaseOrderService(
            new MySqlPurchaseOrderRepository($pdo),
            $supplierRepository,
            $warehouseRepository,
            $productRepository,
            $stockRepository,
            $stockService,
            $transactions,
            $today,
        ),
        $session,
        $view,
    );
    $salesOrderController = new SalesOrderController(
        new SalesOrderService(
            new MySqlSalesOrderRepository($pdo),
            $customerRepository,
            $warehouseRepository,
            $productRepository,
            $stockRepository,
            $stockService,
            $transactions,
            $salesOrderPolicy,
            $today,
        ),
        $salesOrderPolicy,
        $session,
        $view,
    );

    $router = new Router($auth, $csrf);
    $router->get('/', fn (Request $r, $user) => Response::redirect($user === null ? '/login' : '/dashboard'), public: true);
    $router->get('/login', [$authController, 'showLogin'], public: true);
    $router->post('/login', [$authController, 'login'], public: true);
    $router->post('/logout', [$authController, 'logout']);
    $router->get('/dashboard', [$dashboardController, 'index']);

    $router->get('/products', [$productController, 'index']);
    // "/products/create" harus didaftarkan sebelum "/products/{sku}".
    $router->get('/products/create', [$productController, 'create'], [Role::Admin]);
    $router->post('/products', [$productController, 'store'], [Role::Admin]);
    $router->get('/products/{sku}', [$productController, 'show']);
    $router->get('/products/{sku}/edit', [$productController, 'edit'], [Role::Admin]);
    $router->post('/products/{sku}', [$productController, 'update'], [Role::Admin]);

    // Master data & user: seluruhnya khusus Admin (§1.2). Pola route sama untuk tiap modul.
    $adminCrud = static function (string $base, CategoryController|WarehouseController|PartyController|UserController $controller) use ($router): void {
        $router->get($base, [$controller, 'index'], [Role::Admin]);
        $router->get($base . '/create', [$controller, 'create'], [Role::Admin]);
        $router->post($base, [$controller, 'store'], [Role::Admin]);
        $router->get($base . '/{id}/edit', [$controller, 'edit'], [Role::Admin]);
        $router->post($base . '/{id}', [$controller, 'update'], [Role::Admin]);
    };
    $adminCrud('/categories', $categoryController);
    $adminCrud('/warehouses', $warehouseController);
    $adminCrud(PartyType::Supplier->path(), $supplierController);
    $adminCrud(PartyType::Customer->path(), $customerController);
    $adminCrud('/users', $userController);

    // Purchase Order (§1.2): Admin & Warehouse Staff membuat (Warehouse = mengusulkan Draft)
    // dan menerima barang; menandai Ordered dan membatalkan khusus Admin. Sales: tidak ada akses.
    $poRoles = [Role::Admin, Role::WarehouseStaff];
    $router->get('/purchase-orders', [$purchaseOrderController, 'index'], $poRoles);
    $router->get('/purchase-orders/create', [$purchaseOrderController, 'create'], $poRoles);
    $router->post('/purchase-orders', [$purchaseOrderController, 'store'], $poRoles);
    $router->get('/purchase-orders/{id}', [$purchaseOrderController, 'show'], $poRoles);
    $router->post('/purchase-orders/{id}/order', [$purchaseOrderController, 'markOrdered'], [Role::Admin]);
    $router->post('/purchase-orders/{id}/cancel', [$purchaseOrderController, 'cancel'], [Role::Admin]);
    $router->post('/purchase-orders/{id}/receive', [$purchaseOrderController, 'receive'], $poRoles);

    // Sales Order: router menyaring role per endpoint; aturan per order (pemilik,
    // pembuat != penyetuju, status) ditegakkan lagi di SalesOrderService -> 403.
    $allRoles = [Role::Admin, Role::Sales, Role::WarehouseStaff];
    $router->get('/sales-orders', [$salesOrderController, 'index'], $allRoles);
    $router->get('/sales-orders/create', [$salesOrderController, 'create'], [Role::Admin, Role::Sales]);
    $router->post('/sales-orders', [$salesOrderController, 'store'], [Role::Admin, Role::Sales]);
    $router->get('/sales-orders/{id}', [$salesOrderController, 'show'], $allRoles);
    $router->post('/sales-orders/{id}/submit', [$salesOrderController, 'submit'], [Role::Admin, Role::Sales]);
    // Sengaja TIDAK dibatasi ke Admin di router: endpoint approve tetap "tersedia" (brief §1.2)
    // dan penolakan untuk Sales dibuktikan berasal dari authorization layer di service.
    $router->post('/sales-orders/{id}/approve', [$salesOrderController, 'approve'], $allRoles);
    $router->post('/sales-orders/{id}/reject', [$salesOrderController, 'reject'], $allRoles);
    $router->post('/sales-orders/{id}/cancel', [$salesOrderController, 'cancel'], [Role::Admin, Role::Sales]);
    $router->post('/sales-orders/{id}/fulfill', [$salesOrderController, 'fulfill'], [Role::Admin, Role::WarehouseStaff]);

    $response = $router->dispatch($request);
} catch (HttpException $e) {
    $response = Response::html($view->render('errors/error', ['status' => $e->status, 'message' => $e->getMessage()], null), $e->status);
} catch (Throwable $e) {
    // Detail teknis (termasuk PDOException) hanya masuk log server.
    error_log((string) $e);
    $response = Response::html($view->render('errors/error', [
        'status' => 500,
        'message' => 'Terjadi kesalahan pada server. Silakan coba lagi beberapa saat lagi.',
    ], null), 500);
}

$response->send();
