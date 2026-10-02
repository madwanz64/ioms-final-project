<?php

declare(strict_types=1);

/**
 * Front controller & composition root. Seluruh object graph dirakit manual di
 * sini lewat constructor injection (tanpa DI container, sesuai brief §4).
 */

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\ProductController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Entity\Role;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlUserRepository;
use App\Service\AuthService;
use App\Service\DashboardService;
use App\Service\LocalImageStorage;
use App\Service\ProductService;

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
