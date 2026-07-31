<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\MiddlewarePipeline;
use Core\Router\ControllerResolver;
use Core\Router\DuplicateRouteException;
use Core\Router\MethodNotAllowedException;
use Core\Router\RouteNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * CHI chiu trach nhiem: dang ky Route, match Route, trich xuat Route Parameter, chay Middleware
 * Pipeline, resolve Handler qua Container (uy quyen ControllerResolver), tra ve Response.
 *
 * KHONG doc Database, KHONG render View, KHONG check Auth/Authorization, KHONG validate input -
 * tat ca thuoc Middleware cu the (task sau) hoac Controller/Validation Layer.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, true> */
    private array $signatures = [];

    private readonly ControllerResolver $controllerResolver;
    private readonly MiddlewarePipeline $middlewarePipeline;

    /** @var list<class-string> */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';
    private ?string $groupDomain = null;

    public function __construct(ContainerInterface $container)
    {
        $this->controllerResolver = new ControllerResolver($container);
        $this->middlewarePipeline = new MiddlewarePipeline($container);
    }

    /** @param Closure|array{class-string, string} $handler */
    public function get(string $uri, Closure|array $handler): Route
    {
        return $this->addRoute('GET', $uri, $handler);
    }

    /** @param Closure|array{class-string, string} $handler */
    public function post(string $uri, Closure|array $handler): Route
    {
        return $this->addRoute('POST', $uri, $handler);
    }

    /** @param Closure|array{class-string, string} $handler */
    public function put(string $uri, Closure|array $handler): Route
    {
        return $this->addRoute('PUT', $uri, $handler);
    }

    /** @param Closure|array{class-string, string} $handler */
    public function patch(string $uri, Closure|array $handler): Route
    {
        return $this->addRoute('PATCH', $uri, $handler);
    }

    /** @param Closure|array{class-string, string} $handler */
    public function delete(string $uri, Closure|array $handler): Route
    {
        return $this->addRoute('DELETE', $uri, $handler);
    }

    /**
     * Chi merge prefix/middleware/domain - khong tu dong merge bat ky cau hinh nao khac (giu API
     * don gian theo dung thiet ke da chot). Ho tro group long nhau (luu/khoi phuc trang thai truoc).
     *
     * @param array{prefix?: string, middleware?: list<class-string>, domain?: string} $attributes
     */
    public function group(array $attributes, Closure $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousDomain = $this->groupDomain;

        $this->groupPrefix = $previousPrefix . ($attributes['prefix'] ?? '');
        $this->groupMiddleware = [...$previousMiddleware, ...($attributes['middleware'] ?? [])];
        $this->groupDomain = $attributes['domain'] ?? $previousDomain;

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupDomain = $previousDomain;
    }

    public function dispatch(Request $request): Response
    {
        $route = $this->match($request);
        $requestWithParams = $request->withRouteParams($route->extractParams($request->getUri()));

        return $this->middlewarePipeline->handle(
            $requestWithParams,
            $route->getMiddleware(),
            fn (Request $req): Response => $this->controllerResolver->resolve($route->getHandler(), $req)
        );
    }

    /** @param Closure|array{class-string, string} $handler */
    private function addRoute(string $method, string $uri, Closure|array $handler): Route
    {
        $route = new Route($method, $this->groupPrefix . $uri, $handler, $this->groupMiddleware, $this->groupDomain);
        $signature = $route->signature();

        if (isset($this->signatures[$signature])) {
            throw DuplicateRouteException::forSignature($signature);
        }

        $this->signatures[$signature] = true;
        $this->routes[] = $route;

        return $route;
    }

    private function match(Request $request): Route
    {
        $uri = $request->getUri();
        $method = $request->getMethod();
        $host = $request->getHost();

        /** @var list<string> $allowedMethods */
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!$route->matchesUri($uri) || !$route->matchesDomain($host)) {
                continue;
            }

            if ($route->getMethod() === $method) {
                return $route;
            }

            $allowedMethods[] = $route->getMethod();
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException($allowedMethods, $method, $uri);
        }

        throw RouteNotFoundException::forUri($method, $uri);
    }
}
