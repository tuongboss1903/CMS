<?php

declare(strict_types=1);

namespace Tests\Core\Router;

use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\ControllerResolver;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Http\DependentController;
use Tests\Fixtures\Http\TestController;

final class ControllerResolverTest extends TestCase
{
    public function testResolvesClosureHandlerDirectlyWithoutContainer(): void
    {
        $resolver = new ControllerResolver(new Container());
        $request = new Request('GET', '/', 'example.com');

        $response = $resolver->resolve(fn (Request $r): Response => Response::html('closure'), $request);

        self::assertSame('closure', $response->getBody());
    }

    public function testResolvesClassMethodHandlerThroughContainer(): void
    {
        $resolver = new ControllerResolver(new Container());
        $request = new Request('GET', '/users/42', 'example.com', routeParams: ['id' => '42']);

        $response = $resolver->resolve([TestController::class, 'show'], $request);

        self::assertSame('{"id":"42"}', $response->getBody());
    }

    public function testResolvesControllerWithConstructorDependencyThroughContainer(): void
    {
        $resolver = new ControllerResolver(new Container());
        $request = new Request('GET', '/greet', 'example.com');

        $response = $resolver->resolve([DependentController::class, 'index'], $request);

        self::assertSame('hello-from-service', $response->getBody());
    }
}
