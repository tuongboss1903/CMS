<?php

declare(strict_types=1);

namespace Core;

/**
 * Quan ly vong doi token CSRF (sinh/luu/doc/so khop) - thuan logic, khong biet HTTP.
 * Khong tu goi Session::start() - caller (Controller/Middleware) phai dam bao Session da start.
 * Token song theo Session, khong regenerate moi request (chi Session::regenerate() moi doi ID,
 * khong tu dong xoa token - viec rotate token theo su kien dang nhap thuoc pham vi Authentication).
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf.token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, \bin2hex(\random_bytes(32)));
        }

        return (string) $this->session->get(self::SESSION_KEY);
    }

    public function verify(string $submitted): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if (!\is_string($stored) || $stored === '') {
            return false;
        }

        return \hash_equals($stored, $submitted);
    }
}
