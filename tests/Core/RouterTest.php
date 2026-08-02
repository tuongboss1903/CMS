<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router;
use Core\Router\DuplicateRouteException;
use Core\Router\MethodNotAllowedException;
use Core\Router\RouteNotFoundException;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Fixtures\Http\DependentController;
use Tests\Fixtures\Http\MiddlewareA;
use Tests\Fixtures\Http\MiddlewareB;
use Tests\Fixtures\Http\MiddlewareC;
use Tests\Fixtures\Http\ShortCircuitMiddleware;
use Tests\Fixtures\Http\TestController;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new Container());
    }

    public function testRegistersAndMatchesSimpleRoute(): void
    {
        $this->router->get('/ping', fn (Request $request): Response => Response::html('pong'));

        $response = $this->router->dispatch(new Request('GET', '/ping', 'example.com'));

        self::assertSame('pong', $response->getBody());
    }

    public function testDynamicRouteExtractsRouteParameterAsRawStringWithoutCoercion(): void
    {
        $this->router->get('/users/{id}', [TestController::class, 'show']);

        $response = $this->router->dispatch(new Request('GET', '/users/123', 'example.com'));

        self::assertSame('{"id":"123"}', $response->getBody());
    }

    public function testGroupMergesPrefixAndMiddleware(): void
    {
        $this->router->group(['prefix' => '/api', 'middleware' => [MiddlewareA::class]], function (Router $router): void {
            $router->get('/ping', fn (Request $request): Response => Response::html('pong'));
        });

        $response = $this->router->dispatch(new Request('GET', '/api/ping', 'example.com'));

        self::assertSame('A(before)pongA(after)', $response->getBody());
    }

    public function testDomainRouteOnlyMatchesConfiguredHost(): void
    {
        $this->router->group(['domain' => 'admin.example.com'], function (Router $router): void {
            $router->get('/dashboard', fn (Request $request): Response => Response::html('admin-dashboard'));
        });

        $matched = $this->router->dispatch(new Request('GET', '/dashboard', 'admin.example.com'));
        self::assertSame('admin-dashboard', $matched->getBody());

        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch(new Request('GET', '/dashboard', 'other.example.com'));
    }

    public function testMiddlewarePipelineRunsInOnionOrder(): void
    {
        $this->router->group(['middleware' => [MiddlewareA::class, MiddlewareB::class]], function (Router $router): void {
            $router->get('/onion', [TestController::class, 'index']);
        });

        $response = $this->router->dispatch(new Request('GET', '/onion', 'example.com'));

        self::assertSame('A(before)B(before)CONTROLLERB(after)A(after)', $response->getBody());
    }

    public function testShortCircuitMiddlewareStopsPipelineBeforeController(): void
    {
        $this->router->group(['middleware' => [ShortCircuitMiddleware::class]], function (Router $router): void {
            $router->get('/blocked', [TestController::class, 'index']);
        });

        $response = $this->router->dispatch(new Request('GET', '/blocked', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('BLOCKED', $response->getBody());
    }

    public function testControllerWithConstructorDependencyResolvedThroughContainer(): void
    {
        $this->router->get('/greet', [DependentController::class, 'index']);

        $response = $this->router->dispatch(new Request('GET', '/greet', 'example.com'));

        self::assertSame('hello-from-service', $response->getBody());
    }

    public function testThrowsRouteNotFoundWhenNoRouteMatchesUri(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->router->dispatch(new Request('GET', '/does-not-exist', 'example.com'));
    }

    public function testThrowsMethodNotAllowedWhenUriMatchesButMethodDoesNot(): void
    {
        $this->router->get('/only-get', fn (Request $request): Response => Response::html('ok'));

        try {
            $this->router->dispatch(new Request('POST', '/only-get', 'example.com'));
            self::fail('Expected MethodNotAllowedException was not thrown.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET'], $exception->getAllowedMethods());
        }
    }

    public function testDoesNotConfuseNotFoundWithMethodNotAllowed(): void
    {
        $this->router->get('/only-get', fn (Request $request): Response => Response::html('ok'));

        // Truong hop 1: URI khong ton tai o bat ky route nao -> phai la 404, KHONG duoc la 405.
        try {
            $this->router->dispatch(new Request('GET', '/completely-different-uri', 'example.com'));
            self::fail('Expected RouteNotFoundException was not thrown.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(RouteNotFoundException::class, $exception);
            self::assertNotInstanceOf(MethodNotAllowedException::class, $exception);
        }

        // Truong hop 2: URI ton tai nhung sai method -> phai la 405, KHONG duoc la 404.
        try {
            $this->router->dispatch(new Request('DELETE', '/only-get', 'example.com'));
            self::fail('Expected MethodNotAllowedException was not thrown.');
        } catch (Throwable $exception) {
            self::assertInstanceOf(MethodNotAllowedException::class, $exception);
            self::assertNotInstanceOf(RouteNotFoundException::class, $exception);
        }
    }

    public function testRegisteringDuplicateMethodUriDomainThrowsImmediatelyAtRegistration(): void
    {
        $this->router->get('/dup', fn (Request $request): Response => Response::html('a'));

        $this->expectException(DuplicateRouteException::class);

        $this->router->get('/dup', fn (Request $request): Response => Response::html('b'));
    }

    public function testSameUriDifferentMethodIsNotConsideredDuplicate(): void
    {
        $this->router->get('/resource', fn (Request $request): Response => Response::html('get'));
        $this->router->post('/resource', fn (Request $request): Response => Response::html('post'));

        self::assertSame('get', $this->router->dispatch(new Request('GET', '/resource', 'example.com'))->getBody());
        self::assertSame('post', $this->router->dispatch(new Request('POST', '/resource', 'example.com'))->getBody());
    }

    public function testSameMethodUriDifferentDomainIsNotConsideredDuplicate(): void
    {
        $this->router->group(['domain' => 'a.example.com'], function (Router $router): void {
            $router->get('/dashboard', fn (Request $request): Response => Response::html('a-site'));
        });

        // Khong throw DuplicateRouteException vi domain khac nhau.
        $this->router->group(['domain' => 'b.example.com'], function (Router $router): void {
            $router->get('/dashboard', fn (Request $request): Response => Response::html('b-site'));
        });

        self::assertSame('a-site', $this->router->dispatch(new Request('GET', '/dashboard', 'a.example.com'))->getBody());
        self::assertSame('b-site', $this->router->dispatch(new Request('GET', '/dashboard', 'b.example.com'))->getBody());
    }

    public function testGlobalMiddlewareRunsOnEveryRegisteredRoute(): void
    {
        $this->router->middleware([MiddlewareA::class]);
        $this->router->get('/one', fn (Request $request): Response => Response::html('ONE'));
        $this->router->get('/two', fn (Request $request): Response => Response::html('TWO'));

        $first = $this->router->dispatch(new Request('GET', '/one', 'example.com'));
        $second = $this->router->dispatch(new Request('GET', '/two', 'example.com'));

        self::assertSame('A(before)ONEA(after)', $first->getBody());
        self::assertSame('A(before)TWOA(after)', $second->getBody());
    }

    public function testGlobalGroupAndRouteMiddlewareRunInCorrectOnionOrder(): void
    {
        $this->router->middleware([MiddlewareA::class]);
        $this->router->group(['middleware' => [MiddlewareB::class]], function (Router $router): void {
            $router->get('/nested', [TestController::class, 'index'], [MiddlewareC::class]);
        });

        $response = $this->router->dispatch(new Request('GET', '/nested', 'example.com'));

        self::assertSame('A(before)B(before)C(before)CONTROLLERC(after)B(after)A(after)', $response->getBody());
    }

    public function testRouteLevelMiddlewareOnlyAppliesToThatSpecificRoute(): void
    {
        $this->router->get('/with-mw', fn (Request $request): Response => Response::html('WITH'), [MiddlewareA::class]);
        $this->router->get('/without-mw', fn (Request $request): Response => Response::html('WITHOUT'));

        $withResponse = $this->router->dispatch(new Request('GET', '/with-mw', 'example.com'));
        $withoutResponse = $this->router->dispatch(new Request('GET', '/without-mw', 'example.com'));

        self::assertSame('A(before)WITHA(after)', $withResponse->getBody());
        self::assertSame('WITHOUT', $withoutResponse->getBody());
    }

    public function testRouteLevelMiddlewareCombinesWithGroupMiddleware(): void
    {
        $this->router->group(['middleware' => [MiddlewareA::class]], function (Router $router): void {
            $router->get('/combo', [TestController::class, 'index'], [MiddlewareB::class]);
        });

        $response = $this->router->dispatch(new Request('GET', '/combo', 'example.com'));

        self::assertSame('A(before)B(before)CONTROLLERB(after)A(after)', $response->getBody());
    }

    public function testGlobalMiddlewareCanShortCircuitBeforeGroupAndRouteMiddleware(): void
    {
        $this->router->middleware([ShortCircuitMiddleware::class]);
        $this->router->group(['middleware' => [MiddlewareA::class]], function (Router $router): void {
            $router->get('/blocked-by-global', [TestController::class, 'index'], [MiddlewareB::class]);
        });

        $response = $this->router->dispatch(new Request('GET', '/blocked-by-global', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('BLOCKED', $response->getBody());
    }

    public function testRouteWithoutAnyMiddlewareStillDispatchesNormallyWhenGlobalMiddlewareRegisteredElsewhere(): void
    {
        $otherRouter = new Router(new Container());
        $otherRouter->middleware([MiddlewareA::class]);

        $this->router->get('/plain', fn (Request $request): Response => Response::html('PLAIN'));

        $response = $this->router->dispatch(new Request('GET', '/plain', 'example.com'));

        self::assertSame('PLAIN', $response->getBody());
    }
}
