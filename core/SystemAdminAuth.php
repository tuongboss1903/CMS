<?php

declare(strict_types=1);

namespace Core;

/**
 * Ban sao co chu dich cua Core\Auth, danh RIENG cho Super Admin (platform_admins) - session key
 * khac hoan toan ("system_admin.*" vs "auth.*") de khong bao gio lan voi trang thai dang nhap
 * cua Site Admin/User thuong tren cung 1 trinh duyet. Chap nhan trung lap thay vi chia se
 * abstraction voi Auth - dung tien le CMS-012 (PluginManager doc lap ModuleManager).
 */
final class SystemAdminAuth
{
    private const ADMIN_ID_KEY = 'system_admin.admin_id';
    private const ADMIN_KEY = 'system_admin.admin';
    private const CSRF_TOKEN_KEY = 'csrf.token';

    public function __construct(private readonly Session $session)
    {
    }

    /** @param array<string, mixed> $admin */
    public function login(int|string $adminId, array $admin = []): void
    {
        $this->session->regenerate();
        $this->session->remove(self::CSRF_TOKEN_KEY);
        $this->session->set(self::ADMIN_ID_KEY, $adminId);
        $this->session->set(self::ADMIN_KEY, $admin);
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function check(): bool
    {
        return $this->session->has(self::ADMIN_ID_KEY);
    }

    public function id(): int|string|null
    {
        return $this->session->get(self::ADMIN_ID_KEY);
    }

    /** @return array<string, mixed>|null */
    public function admin(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $this->session->get(self::ADMIN_KEY);
    }
}
