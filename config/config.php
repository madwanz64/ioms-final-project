<?php

declare(strict_types=1);

use App\Core\Env;

$root = dirname(__DIR__);
$env = Env::load($root . '/.env');

return [
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($env['DB_PORT'] ?? 3306),
        'name' => $env['DB_NAME'] ?? 'ioms',
        'user' => $env['DB_USER'] ?? 'root',
        'pass' => $env['DB_PASS'] ?? '',
    ],
    'db_test_name' => $env['DB_TEST_NAME'] ?? 'ioms_test',
    'upload' => [
        'dir' => $root . '/public/uploads/products',
        'url_prefix' => '/uploads/products',
        'max_bytes' => (int) ($env['UPLOAD_MAX_BYTES'] ?? 2_097_152),
    ],
    'views' => $root . '/views',
];
