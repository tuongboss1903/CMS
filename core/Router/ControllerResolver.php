<?php

declare(strict_types=1);

namespace Core\Router;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Psr\Container\ContainerInterface;

/**
 * Buoc cuoi cung cua pipeline (Middleware Pipeline -> Controller Resolver -> Controller): resolve
 * Controller qua Container roi goi method tuong ung. Tach rieng khoi Router de test doc lap duoc.
 */
final class ControllerResolver
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param Closure|array{class-string, string} $handler */
    public function resolve(Closure|array $handler, Request $request): Response
    {
        if ($handler instanceof Closure) {
            return $handler($request);
        }

        [$class, $method] = $handler;

        $controller = $this->container->get($class);

        return $controller->$method($request);
    }
}
