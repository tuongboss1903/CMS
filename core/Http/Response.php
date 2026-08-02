<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Immutable. send() la noi DUY NHAT goi header()/echo that - co lap I/O dau ra, giup Router::dispatch()
 * test duoc ma khong can gui HTTP that (chi assert vao object Response). Moi with*() tra ve instance
 * MOI (new self(...)), khong clone, khong mutable state - cung pattern Request::withRouteParams().
 *
 * CMS-016: bo sung additive (withHeader/withHeaders/withStatus/withCookie/withCache/noCache).
 * KHONG apiSuccess()/apiError() (business convention, khong phai HTTP contract - Module/Helper tu
 * dung body roi truyen Response::json()). KHONG download()/file() (filesystem la chu de rieng).
 * Van giu 0 dependency - withCookie() KHONG doc Config, caller tu truyen secure/httponly/samesite.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies key la ten cookie, value la chuoi Set-Cookie DA dinh
     *   dang day du (bao gom attributes) - tach rieng khoi $headers vi HTTP cho phep NHIEU
     *   Set-Cookie header cung luc (moi cookie 1 dong), trong khi $headers chi giu 1 gia tri/ten.
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
        private readonly array $cookies = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($body === false ? '{}' : $body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return array<string, string> key la ten cookie, value la chuoi Set-Cookie da dinh dang day du */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function withHeader(string $name, string $value): static
    {
        return new self($this->body, $this->statusCode, [...$this->headers, $name => $value], $this->cookies);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): static
    {
        return new self($this->body, $this->statusCode, [...$this->headers, ...$headers], $this->cookies);
    }

    public function withStatus(int $status): static
    {
        return new self($this->body, $status, $this->headers, $this->cookies);
    }

    /**
     * @param array{
     *     expires?: int,
     *     path?: string,
     *     domain?: string,
     *     secure?: bool,
     *     httponly?: bool,
     *     samesite?: string
     * } $options
     */
    public function withCookie(string $name, string $value, array $options = []): static
    {
        $cookie = \rawurlencode($name) . '=' . \rawurlencode($value);

        if (isset($options['expires'])) {
            $cookie .= '; Expires=' . \gmdate('D, d-M-Y H:i:s T', $options['expires']);
        }

        $cookie .= '; Path=' . ($options['path'] ?? '/');

        if (isset($options['domain'])) {
            $cookie .= '; Domain=' . $options['domain'];
        }

        if ($options['secure'] ?? false) {
            $cookie .= '; Secure';
        }

        if ($options['httponly'] ?? true) {
            $cookie .= '; HttpOnly';
        }

        if (isset($options['samesite'])) {
            $cookie .= '; SameSite=' . $options['samesite'];
        }

        return new self($this->body, $this->statusCode, $this->headers, [...$this->cookies, $name => $cookie]);
    }

    /** Chi thao tac header Cache-Control - khong ETag/Last-Modified/Vary. */
    public function withCache(int $seconds, bool $public = true): static
    {
        $visibility = $public ? 'public' : 'private';

        return $this->withHeader('Cache-Control', "{$visibility}, max-age={$seconds}");
    }

    public function noCache(): static
    {
        return $this->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function send(): void
    {
        \http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            \header("{$name}: {$value}");
        }

        foreach ($this->cookies as $cookie) {
            \header("Set-Cookie: {$cookie}", false);
        }

        echo $this->body;
    }
}
