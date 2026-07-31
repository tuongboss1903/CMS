<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Immutable - moi with*() tra ve instance MOI (new self(...)), khong sua object hien tai.
 * fromGlobals() la noi DUY NHAT doc $_SERVER/$_GET/$_POST - co lap truy cap superglobal vao 1 diem.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly string $host,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
        private readonly array $routeParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $headers = self::extractHeaders();

        return new self(
            \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            \is_string($uri) && $uri !== '' ? $uri : '/',
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            $_GET,
            self::resolveBody($headers),
            $headers
        );
    }

    /** @return array<string, string> */
    private static function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }

            if (\str_starts_with($key, 'HTTP_')) {
                $headers[\str_replace('_', '-', \substr($key, 5))] = (string) $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                // PHP KHONG dien tien to HTTP_ cho 2 header nay trong $_SERVER (khac moi header khac).
                $headers[\str_replace('_', '-', $key)] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * $_POST chi duoc PHP tu dong dien khi Content-Type la application/x-www-form-urlencoded hoac
     * multipart/form-data (dung cho form SSR nhu POST /admin/login). JSON body (chuan cho /api/v1/*
     * theo api-document.md, vd POST /api/v1/auth/login) phai tu doc qua php://input + json_decode.
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private static function resolveBody(array $headers): array
    {
        $contentType = $headers['CONTENT-TYPE'] ?? '';

        if (!\str_contains($contentType, 'application/json')) {
            return $_POST;
        }

        $raw = \file_get_contents('php://input');
        $decoded = $raw === false || $raw === '' ? null : \json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtoupper($name)] ?? null;
    }

    /**
     * Chi tra ve chuoi tho, KHONG ep kieu/validate - do la trach nhiem Controller/Validation Layer.
     */
    public function routeParam(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    /** @param array<string, string> $params */
    public function withRouteParams(array $params): static
    {
        return new self(
            $this->method,
            $this->uri,
            $this->host,
            $this->query,
            $this->body,
            $this->headers,
            $params
        );
    }
}
