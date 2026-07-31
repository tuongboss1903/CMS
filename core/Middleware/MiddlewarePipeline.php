<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Psr\Container\ContainerInterface;

/**
 * Chay tuan tu middleware kieu "onion" (dung mo hinh Before/After): moi middleware duoc resolve
 * qua Container, boc lop ngoai cung la middleware DAU TIEN trong danh sach, lop trong cung la
 * $destination (Controller). Middleware co the goi $next() de di tiep hoac tu tra Response ngay
 * (short-circuit) ma khong can toi $destination.
 */
final class MiddlewarePipeline
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param list<class-string<MiddlewareInterface>> $middleware */
    public function handle(Request $request, array $middleware, Closure $destination): Response
    {
        $chain = \array_reduce(
            \array_reverse($middleware),
            fn (Closure $next, string $middlewareClass): Closure => function (Request $request) use ($next, $middlewareClass): Response {
                /** @var MiddlewareInterface $instance */
                $instance = $this->container->get($middlewareClass);

                return $instance->process($request, $next);
            },
            $destination
        );

        return $chain($request);
    }
}
