<?php

declare(strict_types=1);

namespace App\Core;

use App\Entity\Role;
use App\Entity\User;

/**
 * Router sederhana dengan guard terpusat. Setiap route terlindungi secara
 * default; pengecualian harus dinyatakan eksplisit lewat $public = true.
 * Pemeriksaan role dilakukan di server untuk setiap request (§4.2).
 */
final class Router
{
    /**
     * @var list<array{method: string, regex: string, handler: callable, roles: list<Role>, public: bool}>
     */
    private array $routes = [];

    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
    ) {
    }

    /**
     * @param callable(Request, User|null, array<string, string>): Response $handler
     * @param list<Role> $roles kosong = semua user yang sudah login
     */
    public function get(string $pattern, callable $handler, array $roles = [], bool $public = false): void
    {
        $this->add('GET', $pattern, $handler, $roles, $public);
    }

    /**
     * @param callable(Request, User|null, array<string, string>): Response $handler
     * @param list<Role> $roles
     */
    public function post(string $pattern, callable $handler, array $roles = [], bool $public = false): void
    {
        $this->add('POST', $pattern, $handler, $roles, $public);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            if ($request->method === 'POST' && !$this->csrf->isValid($request->input(Csrf::FIELD))) {
                throw new HttpException(403, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            }

            $user = $this->auth->user();
            if (!$route['public']) {
                if ($user === null) {
                    // API-01: klien API mendapat kode status yang tepat, bukan redirect ke halaman HTML.
                    return $request->isApi()
                        ? Response::json(['error' => 'unauthenticated', 'message' => 'Silakan login terlebih dahulu.'], 401)
                        : Response::redirect('/login');
                }
                if ($route['roles'] !== [] && !$user->hasRole(...$route['roles'])) {
                    throw HttpException::forbidden();
                }
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $user, array_map('rawurldecode', $params));
        }

        throw $pathMatched ? new HttpException(405, 'Metode request tidak didukung untuk URL ini.') : HttpException::notFound();
    }

    /**
     * Parameter route numerik (mis. {id}); selain angka positif dianggap 404.
     *
     * @param array<string, string> $params
     */
    public static function intParam(array $params, string $key): int
    {
        $value = $params[$key] ?? '';
        if (!ctype_digit($value) || (int) $value < 1) {
            throw HttpException::notFound();
        }

        return (int) $value;
    }

    /**
     * @param list<Role> $roles
     */
    private function add(string $method, string $pattern, callable $handler, array $roles, bool $public): void
    {
        // "/products/{sku}" -> "#^/products/(?<sku>[^/]+)$#"; bagian literal di-escape
        // (mis. titik pada "/reports/{type}.csv" berarti titik, bukan "karakter apa pun").
        $regex = '#^' . preg_replace_callback(
            '#\{(\w+)\}|[^{]+#',
            static fn (array $m): string => isset($m[1]) ? '(?<' . $m[1] . '>[^/]+)' : preg_quote($m[0], '#'),
            $pattern,
        ) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'roles' => $roles, 'public' => $public];
    }
}
