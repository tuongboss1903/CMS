<?php

declare(strict_types=1);

namespace Core;

/**
 * Quan ly trang thai "da dang nhap hay chua" trong Session - khong verify credential, khong DB,
 * khong biet roles/permissions (thuoc CMS-022 Authorization). Khong tu Session::start() - caller
 * (Controller/Middleware) phai dam bao Session da start.
 */
final class Auth
{
    private const USER_ID_KEY = 'auth.user_id';
    private const USER_KEY = 'auth.user';
    private const CSRF_TOKEN_KEY = 'csrf.token';

    public function __construct(private readonly Session $session)
    {
    }

    /** @param array<string, mixed> $user */
    public function login(int|string $userId, array $user = []): void
    {
        $this->session->regenerate();
        $this->session->remove(self::CSRF_TOKEN_KEY);
        $this->session->set(self::USER_ID_KEY, $userId);
        $this->session->set(self::USER_KEY, $user);
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function check(): bool
    {
        return $this->session->has(self::USER_ID_KEY);
    }

    public function id(): int|string|null
    {
        return $this->session->get(self::USER_ID_KEY);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $this->session->get(self::USER_KEY);
    }
}
