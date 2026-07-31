<?php

declare(strict_types=1);

namespace Core;

use Closure;

/**
 * 1 route da dang ky. Chi parse {param} thanh ten - KHONG ep kieu, KHONG validate gia tri
 * (thuoc Controller/Validation Layer).
 */
final class Route
{
    private const PARAM_PATTERN = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';

    private readonly string $regex;

    /** @var list<string> */
    private readonly array $paramNames;

    /**
     * @param Closure|array{class-string, string} $handler
     * @param list<class-string> $middleware
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly Closure|array $handler,
        private readonly array $middleware = [],
        private readonly ?string $domain = null,
    ) {
        [$this->regex, $this->paramNames] = self::compile($uri);
    }

    /** @return array{0: string, 1: list<string>} */
    private static function compile(string $uri): array
    {
        $paramNames = [];

        $pattern = \preg_replace_callback(
            self::PARAM_PATTERN,
            static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];

                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $uri
        );

        return ['#^' . $pattern . '$#', $paramNames];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /** @return Closure|array{class-string, string} */
    public function getHandler(): Closure|array
    {
        return $this->handler;
    }

    /** @return list<class-string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function matchesUri(string $uri): bool
    {
        return \preg_match($this->regex, $uri) === 1;
    }

    public function matchesDomain(string $host): bool
    {
        return $this->domain === null || $this->domain === $host;
    }

    /** @return array<string, string> */
    public function extractParams(string $uri): array
    {
        \preg_match($this->regex, $uri, $matches);

        $params = [];

        foreach ($this->paramNames as $name) {
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    /** Dung de phat hien dang ky trung Method+URI+Domain ngay luc dang ky (khong doi toi runtime). */
    public function signature(): string
    {
        return $this->method . ' ' . $this->uri . ' ' . ($this->domain ?? '*');
    }
}
