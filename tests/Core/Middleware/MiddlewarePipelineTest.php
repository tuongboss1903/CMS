<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Http\MiddlewareA;
use Tests\Fixtures\Http\MiddlewareB;
use Tests\Fixtures\Http\ShortCircuitMiddleware;

final class MiddlewarePipelineTest extends TestCase
{
    private MiddlewarePipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = new MiddlewarePipeline(new Container());
    }

    public function testResolvesMiddlewareFromClassStringViaContainer(): void
    {
        $response = $this->pipeline->handle(
            new Request('GET', '/', 'example.com'),
            [MiddlewareA::class],
            fn (Request $request): Response => Response::html('CONTROLLER')
        );

        self::assertSame('A(before)CONTROLLERA(after)', $response->getBody());
    }

    public function testResolvesPreConfiguredMiddlewareInstanceDirectly(): void
    {
        $response = $this->pipeline->handle(
            new Request('GET', '/', 'example.com'),
            [new MiddlewareA()],
            fn (Request $request): Response => Response::html('CONTROLLER')
        );

        self::assertSame('A(before)CONTROLLERA(after)', $response->getBody());
    }

    public function testMixesClassStringAndInstanceInSameChain(): void
    {
        $response = $this->pipeline->handle(
            new Request('GET', '/', 'example.com'),
            [MiddlewareA::class, new MiddlewareB()],
            fn (Request $request): Response => Response::html('CONTROLLER')
        );

        self::assertSame('A(before)B(before)CONTROLLERB(after)A(after)', $response->getBody());
    }

    public function testShortCircuitStillWorksWithInstanceMiddleware(): void
    {
        $response = $this->pipeline->handle(
            new Request('GET', '/', 'example.com'),
            [new ShortCircuitMiddleware()],
            fn (Request $request): Response => Response::html('CONTROLLER')
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('BLOCKED', $response->getBody());
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function invalidMiddlewareEntryProvider(): iterable
    {
        yield 'int' => [123];
        yield 'array' => [['not', 'a', 'middleware']];
        yield 'object not implementing MiddlewareInterface' => [new \stdClass()];
    }

    /** @dataProvider invalidMiddlewareEntryProvider */
    public function testThrowsInvalidArgumentExceptionForNonStringNonMiddlewareEntry(mixed $invalidEntry): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pipeline->handle(
            new Request('GET', '/', 'example.com'),
            [$invalidEntry],
            fn (Request $request): Response => Response::html('CONTROLLER')
        );
    }
}
