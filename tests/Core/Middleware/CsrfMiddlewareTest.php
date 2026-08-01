<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Closure;
use Core\Config;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\CsrfMiddleware;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private const VALID_TOKEN = 'abc123validtoken';

    private Session $session;
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->session->set('csrf.token', self::VALID_TOKEN);

        $this->middleware = new CsrfMiddleware(new Csrf($this->session));
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

    public function testAllowsGetRequestWithoutTokenCheck(): void
    {
        $request = new Request('GET', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testAllowsHeadAndOptionsRequestsWithoutTokenCheck(): void
    {
        $headResponse = $this->middleware->process(new Request('HEAD', '/', 'example.com'), $this->next());
        $optionsResponse = $this->middleware->process(new Request('OPTIONS', '/', 'example.com'), $this->next());

        self::assertSame('NEXT-CALLED', $headResponse->getBody());
        self::assertSame('NEXT-CALLED', $optionsResponse->getBody());
    }

    public function testBlocksPostRequestWithMissingTokenReturning419WithExactMessage(): void
    {
        $request = new Request('POST', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(419, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('CSRF token mismatch.', $body['message']);
    }

    public function testBlocksPostRequestWithMismatchedToken(): void
    {
        $request = new Request('POST', '/', 'example.com', body: ['_token' => 'wrong-token']);

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(419, $response->getStatusCode());
    }

    public function testAllowsPostRequestWithValidTokenFromInputField(): void
    {
        $request = new Request('POST', '/', 'example.com', body: ['_token' => self::VALID_TOKEN]);

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testAllowsPutPatchDeleteRequestsWithValidToken(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $request = new Request($method, '/', 'example.com', body: ['_token' => self::VALID_TOKEN]);

            $response = $this->middleware->process($request, $this->next());

            self::assertSame('NEXT-CALLED', $response->getBody(), "Method {$method} phai duoc chap nhan voi token hop le.");
        }
    }

    public function testFallsBackToXCsrfTokenHeaderWhenInputFieldMissing(): void
    {
        $request = new Request('POST', '/', 'example.com', headers: ['X-CSRF-TOKEN' => self::VALID_TOKEN]);

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testFallsBackToXXsrfTokenHeaderWhenInputAndXCsrfTokenMissing(): void
    {
        $request = new Request('POST', '/', 'example.com', headers: ['X-XSRF-TOKEN' => self::VALID_TOKEN]);

        $response = $this->middleware->process($request, $this->next());

        self::assertSame('NEXT-CALLED', $response->getBody());
    }

    public function testFailResponseEnvelopeMatchesExactContract(): void
    {
        $request = new Request('POST', '/', 'example.com');

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(419, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'data' => null, 'message' => 'CSRF token mismatch.', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testEmptyTokenInInputFieldDoesNotFallBackToHeader(): void
    {
        $request = new Request(
            'POST',
            '/',
            'example.com',
            body: ['_token' => ''],
            headers: ['X-CSRF-TOKEN' => self::VALID_TOKEN]
        );

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(419, $response->getStatusCode());
    }

    public function testArrayTokenIsRejectedWithoutWarningOrError(): void
    {
        $request = new Request('POST', '/', 'example.com', body: ['_token' => ['a', 'b']]);

        $response = $this->middleware->process($request, $this->next());

        self::assertSame(419, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('CSRF token mismatch.', $body['message']);
    }
}
