<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Entity\Role;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryUserRepository;

/**
 * Guard terpusat Router (§4.2): tamu, role, CSRF, 404/405, dan parameter route.
 * Session memakai $_SESSION biasa sehingga bisa diuji di CLI tanpa session_start().
 */
final class RouterTest extends TestCase
{
    private Router $router;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session();
        $users = new InMemoryUserRepository();
        $users->add(new User(1, 'Admin', 'admin@ioms.test', 'x', Role::Admin, true));
        $users->add(new User(2, 'Sinta', 'sinta@ioms.test', 'x', Role::Sales, true));
        $this->csrf = new Csrf($session);
        $this->router = new Router(new Auth($session, $users), $this->csrf);

        $echo = static fn (Request $r, ?User $user, array $params): Response
            => Response::json(['user' => $user?->email, 'params' => $params]);
        $this->router->get('/login', $echo, public: true);
        $this->router->get('/dashboard', $echo);
        $this->router->get('/users', $echo, [Role::Admin]);
        $this->router->post('/users', $echo, [Role::Admin]);
        $this->router->get('/products/{sku}', $echo);
        $this->router->get('/reports/{type}.csv', $echo);
        $this->router->get('/api/products/{sku}/availability', $echo);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testPublicRouteIsOpenForGuests(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/login'));

        self::assertSame(200, $response->status);
        self::assertSame(['user' => null, 'params' => []], $this->json($response));
    }

    public function testGuestIsRedirectedToLoginOnProtectedPage(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/dashboard'));

        self::assertSame(302, $response->status);
        self::assertSame(Router::LOGIN_PATH, $response->headers['Location']);
    }

    public function testGuestOnApiGets401JsonInsteadOfRedirect(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/products/SKU-0001/availability'));

        self::assertSame(401, $response->status);
        self::assertSame('unauthenticated', $this->json($response)['error']);
    }

    public function testLoggedInUserWithoutRequiredRoleIsForbidden(): void
    {
        $this->loginAs(2);

        $this->expectExceptionObject(HttpException::forbidden());
        $this->router->dispatch(new Request('GET', '/users'));
    }

    public function testUserWithRequiredRoleReachesHandler(): void
    {
        $this->loginAs(1);

        $response = $this->router->dispatch(new Request('GET', '/users'));

        self::assertSame('admin@ioms.test', $this->json($response)['user']);
    }

    public function testPostWithoutValidCsrfTokenIsRejectedBeforeHandler(): void
    {
        $this->loginAs(1);
        $this->csrf->token();

        try {
            $this->router->dispatch(new Request('POST', '/users', body: [Csrf::FIELD => 'salah']));
            self::fail('POST tanpa token CSRF valid harus ditolak.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
    }

    public function testPostWithValidCsrfTokenReachesHandler(): void
    {
        $this->loginAs(1);

        $response = $this->router->dispatch(new Request('POST', '/users', body: [Csrf::FIELD => $this->csrf->token()]));

        self::assertSame(200, $response->status);
    }

    public function testUnknownPathIs404AndWrongMethodIs405(): void
    {
        $this->loginAs(1);

        foreach ([['GET', '/tidak-ada', 404], ['POST', '/dashboard', 405]] as [$method, $path, $status]) {
            try {
                $this->router->dispatch(new Request($method, $path, body: [Csrf::FIELD => $this->csrf->token()]));
                self::fail(sprintf('%s %s harus menghasilkan %d.', $method, $path, $status));
            } catch (HttpException $e) {
                self::assertSame($status, $e->status);
            }
        }
    }

    public function testRouteParamsAreUrlDecodedAndLiteralPartsAreEscaped(): void
    {
        $this->loginAs(1);

        $product = $this->router->dispatch(new Request('GET', '/products/SKU%2D0001'));
        self::assertSame(['sku' => 'SKU-0001'], $this->json($product)['params']);

        $csv = $this->router->dispatch(new Request('GET', '/reports/stock.csv'));
        self::assertSame(['type' => 'stock'], $this->json($csv)['params']);

        // Titik pada "{type}.csv" literal: "stockXcsv" tidak boleh cocok.
        $this->expectExceptionObject(HttpException::notFound());
        $this->router->dispatch(new Request('GET', '/reports/stockXcsv'));
    }

    public function testIntParamAcceptsOnlyPositiveDigits(): void
    {
        self::assertSame(12, Router::intParam(['id' => '12'], 'id'));

        foreach (['0', '-1', '1.5', 'abc', ''] as $invalid) {
            try {
                Router::intParam(['id' => $invalid], 'id');
                self::fail(sprintf('"%s" harus ditolak sebagai id.', $invalid));
            } catch (HttpException $e) {
                self::assertSame(404, $e->status);
            }
        }
    }

    private function loginAs(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $data = json_decode($response->body, true);
        self::assertIsArray($data);

        return $data;
    }
}
