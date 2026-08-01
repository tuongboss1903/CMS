<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\ExceptionHandler;
use Core\Router\MethodNotAllowedException;
use Core\Router\RouteNotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionHandlerTest extends TestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ExceptionHandler();
    }

    public function testMapsRouteNotFoundExceptionTo404(): void
    {
        $response = $this->handler->handle(RouteNotFoundException::forUri('GET', '/missing'), false);

        self::assertSame(404, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('Not Found', $body['message']);
    }

    public function testMapsMethodNotAllowedExceptionTo405(): void
    {
        $exception = new MethodNotAllowedException(['GET'], 'POST', '/only-get');

        $response = $this->handler->handle($exception, false);

        self::assertSame(405, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('Method Not Allowed', $body['message']);
    }

    public function testMapsGenericThrowableTo500WithGenericMessageWhenNotDebug(): void
    {
        $response = $this->handler->handle(new RuntimeException('secret-internal-detail'), false);

        self::assertSame(500, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('Internal Server Error', $body['message']);
        self::assertStringNotContainsString('secret-internal-detail', $response->getBody());
    }

    public function testMapsGenericThrowableTo500WithRealMessageWhenDebug(): void
    {
        $response = $this->handler->handle(new RuntimeException('boom-test'), true);

        self::assertSame(500, $response->getStatusCode());

        $body = \json_decode($response->getBody(), true);
        self::assertSame('boom-test', $body['message']);
    }

    public function testDebugModeIncludesExceptionClassFileLineAndTrace(): void
    {
        $exception = new RuntimeException('boom-test');

        $response = $this->handler->handle($exception, true);

        $body = \json_decode($response->getBody(), true);

        self::assertArrayHasKey('debug', $body);
        self::assertSame(RuntimeException::class, $body['debug']['exception']);
        self::assertSame($exception->getFile(), $body['debug']['file']);
        self::assertSame($exception->getLine(), $body['debug']['line']);
        self::assertIsArray($body['debug']['trace']);
    }

    public function testProductionModeNeverIncludesDebugKeyEvenForGenericThrowable(): void
    {
        $response = $this->handler->handle(new RuntimeException('boom-test'), false);

        $body = \json_decode($response->getBody(), true);

        self::assertArrayNotHasKey('debug', $body);
    }

    public function test404And405NeverIncludeDebugBlockEvenWhenDebugTrue(): void
    {
        $notFoundBody = \json_decode(
            $this->handler->handle(RouteNotFoundException::forUri('GET', '/x'), true)->getBody(),
            true
        );
        $methodNotAllowedBody = \json_decode(
            $this->handler->handle(new MethodNotAllowedException(['GET'], 'POST', '/x'), true)->getBody(),
            true
        );

        self::assertArrayNotHasKey('debug', $notFoundBody);
        self::assertArrayNotHasKey('debug', $methodNotAllowedBody);
    }

    public function testResponseEnvelopeAlwaysHasSuccessFalseDataNullErrorsEmpty(): void
    {
        $body = \json_decode($this->handler->handle(new RuntimeException('x'), false)->getBody(), true);

        self::assertFalse($body['success']);
        self::assertNull($body['data']);
        self::assertSame([], $body['errors']);
    }
}
