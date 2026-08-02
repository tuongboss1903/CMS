<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Closure;
use Core\Auth;
use Core\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\AuthMiddleware;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private Session $session;
    private Auth $auth;
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->auth = new Auth($this->session);
        $this->middleware = new AuthMiddleware($this->auth);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    private function next(): Closure
    {
        return static fn (Request $request): Response => Response::html('NEXT-CALLED');
    }

    public function testUnauthenticatedRequestReturns401WithExactMessage(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'data' => null, 'message' => 'Unauthenticated.', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testAuthenticatedRequestCallsNext(): void
    {
        $this->auth->login(42, ['email' => 'a@example.com']);

        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testUnauthenticatedRequestNeverCallsNext(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertNotSame('NEXT-CALLED', $response->getBody());
    }
}
