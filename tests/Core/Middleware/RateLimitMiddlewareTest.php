<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Closure;
use Core\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\RateLimitMiddleware;
use Core\RateLimiter;
use Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * RateLimitMiddleware la placeholder thuan tuy (Owner Decision CMS-023) - KHONG kiem tra quota,
 * KHONG goi hit(), KHONG bao gio tra 429. Test o day chi xac nhan dung hanh vi passthrough do,
 * khong test hanh vi rate-limit thuc te (thuoc trach nhiem Module tuong lai tu goi RateLimiter).
 */
final class RateLimitMiddlewareTest extends TestCase
{
    private Session $session;
    private RateLimiter $rateLimiter;
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->rateLimiter = new RateLimiter($this->session);
        $this->middleware = new RateLimitMiddleware($this->rateLimiter);
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

    public function testAlwaysCallsNextAndReturnsItsResponse(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testCallsNextEvenWhenUnderlyingKeyIsAlreadyExhausted(): void
    {
        // Du da "het quota" theo RateLimiter (neu co ai do goi hit() tu ben ngoai), Middleware
        // van khong duoc phep tu kiem tra/chan - no khong doc bat ky key nao ca.
        $this->rateLimiter->hit('some-key', 1, 60);
        $this->rateLimiter->hit('some-key', 1, 60);

        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testDoesNotMutateAnyRateLimiterState(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $this->middleware->process($request, $this->next());

        self::assertSame(0, $this->rateLimiter->attempts('login'));
        self::assertSame(0, $this->rateLimiter->attempts('contact'));
        self::assertSame(0, $this->rateLimiter->attempts('api'));
    }

    public function testNeverReturns429(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertNotSame(429, $response->getStatusCode());
    }
}
