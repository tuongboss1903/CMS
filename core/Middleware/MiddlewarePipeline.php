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

    /** @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware */
    public function handle(Request $request, array $middleware, Closure $destination): Response
    {
        $chain = \array_reduce(
            \array_reverse($middleware),
            fn (Closure $next, mixed $middlewareEntry): Closure => function (Request $request) use ($next, $middlewareEntry): Response {
                return $this->resolve($middlewareEntry)->process($request, $next);
            },
            $destination
        );

        return $chain($request);
    }

    private function resolve(mixed $middlewareEntry): MiddlewareInterface
    {
        if (\is_string($middlewareEntry)) {
            /** @var MiddlewareInterface */
            return $this->container->get($middlewareEntry);
        }

        if ($middlewareEntry instanceof MiddlewareInterface) {
            return $middlewareEntry;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Middleware phai la class-string hoac instance cua MiddlewareInterface, nhan duoc: %s.',
            \get_debug_type($middlewareEntry)
        ));
    }
}
