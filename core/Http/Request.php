<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Immutable - moi with*() tra ve instance MOI (new self(...)), khong sua object hien tai.
 * fromGlobals() la noi DUY NHAT doc $_SERVER/$_GET/$_POST/$_FILES/$_COOKIE - co lap truy cap
 * superglobal vao 1 diem.
 *
 * CMS-015: bo sung additive (files/cookies/server + method()/uri()/path()/all()/has()/filled()/
 * cookie()/file()/ip()/userAgent()/isMethod()/ajax()/json()) - KHONG doi/xoa method cu, KHONG
 * Method Spoofing (_method), KHONG Trusted Proxy (ip() chi doc REMOTE_ADDR).
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $files
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly string $host,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
        private readonly array $routeParams = [],
        private readonly array $files = [],
        private readonly array $cookies = [],
        private readonly array $server = [],
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
            $headers,
            [],
            $_FILES,
            $_COOKIE,
            $_SERVER
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
            $params,
            $this->files,
            $this->cookies,
            $this->server
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** Hien tai tra cung gia tri voi uri() - thiet ke khong luu URI day du kem query string. */
    public function path(): string
    {
        return $this->uri;
    }

    /** @return array<string, mixed> query + body, body ghi de query khi trung key */
    public function all(): array
    {
        return \array_merge($this->query, $this->body);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        $value = $this->all()[$key];

        return $value !== null && $value !== '' && $value !== [];
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /** Tra ve THO $_FILES[$key], khong co UploadedFile abstraction (ngoai pham vi). */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /** KHONG doc X-Forwarded-For/X-Real-IP - khong Trusted Proxy (Decision #8 CMS-015). */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    public function isMethod(string $method): bool
    {
        return $this->method === \strtoupper($method);
    }

    public function ajax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /** True neu request GUI LEN co Content-Type JSON (khong phai client muon nhan gi ve). */
    public function json(): bool
    {
        return \str_contains($this->header('Content-Type') ?? '', 'application/json');
    }
}
