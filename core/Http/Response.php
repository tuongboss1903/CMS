<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Immutable. send() la noi DUY NHAT goi header()/echo that - co lap I/O dau ra, giup Router::dispatch()
 * test duoc ma khong can gui HTTP that (chi assert vao object Response). Neu bo sung withStatus()/
 * withHeader() sau nay, phai theo dung pattern "tra ve instance moi" nhu Request::withRouteParams().
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
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

    public function send(): void
    {
        \http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            \header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
