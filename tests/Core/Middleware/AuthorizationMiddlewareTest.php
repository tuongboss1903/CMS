<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Closure;
use Core\Authorization;
use Core\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\AuthorizationMiddleware;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class AuthorizationMiddlewareTest extends TestCase
{
    private Session $session;
    private Authorization $authorization;
    private AuthorizationMiddleware $middleware;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->authorization = new Authorization($this->session);
        $this->middleware = new AuthorizationMiddleware($this->authorization);
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

    public function testBlocksRequestWhenNoRolesAndNoPermissionsReturning403(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'data' => null, 'message' => 'Forbidden.', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testAllowsRequestWhenHasAtLeastOneRole(): void
    {
        $this->session->set('auth.roles', ['editor']);

        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testAllowsRequestWhenHasAtLeastOnePermission(): void
    {
        $this->session->set('auth.permissions', ['post.view']);

        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testUnauthorizedRequestNeverCallsNext(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertNotSame('NEXT-CALLED', $response->getBody());
    }
}
