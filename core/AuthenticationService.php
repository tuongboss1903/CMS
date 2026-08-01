<?php

declare(strict_types=1);

namespace Core;

/**
 * Verify email/password that tu database, goi Auth::login() (khong doi API), nap roles/permissions
 * that vao Session theo site hien tai (TenantManager::id()). Khong Repository, khong RateLimiter,
 * khong route/Controller - Module tuong lai tu goi attempt() truc tiep.
 *
 * Chong user enumeration: email khong ton tai van chay password_verify() voi DUMMY_HASH, tra ve
 * dung 1 diem false giong het truong hop sai password - khong phan biet ly do that bai.
 */
final class AuthenticationService
{
    /** Hash bcrypt hop le co dinh, khong tuong ung password that nao - chi de password_verify() luon thuc thi cong viec CPU that (chong timing side-channel khi email khong ton tai). */
    private const DUMMY_HASH = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

    public function __construct(
        private readonly Database $database,
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        if (!$this->tenantManager->check()) {
            throw new \LogicException(
                'AuthenticationService::attempt() yeu cau tenant hien tai da duoc resolve (TenantManager::check() === false).'
            );
        }

        $user = $this->database->selectOne(
            'SELECT id, password, status FROM users WHERE email = ?',
            [$email]
        );

        $passwordHash = $user !== null ? (string) $user['password'] : self::DUMMY_HASH;

        if (!\password_verify($password, $passwordHash)) {
            return false;
        }

        if ($user === null || $user['status'] !== 'active') {
            return false;
        }

        $userId = (int) $user['id'];

        $this->auth->login($userId, [
            'id' => $userId,
            'email' => $email,
        ]);

        $siteId = $this->tenantManager->id();

        $roles = $this->database->select(
            'SELECT DISTINCT roles.name
             FROM user_site_roles
             INNER JOIN roles ON roles.id = user_site_roles.role_id
             WHERE user_site_roles.user_id = ? AND user_site_roles.site_id = ?',
            [$userId, $siteId]
        );

        $permissions = $this->database->select(
            'SELECT DISTINCT permissions.key
             FROM user_site_roles
             INNER JOIN roles ON roles.id = user_site_roles.role_id
             INNER JOIN role_permissions ON role_permissions.role_id = roles.id
             INNER JOIN permissions ON permissions.id = role_permissions.permission_id
             WHERE user_site_roles.user_id = ? AND user_site_roles.site_id = ?',
            [$userId, $siteId]
        );

        $this->session->set('auth.roles', \array_map(static fn (array $row): string => (string) $row['name'], $roles));
        $this->session->set('auth.permissions', \array_map(static fn (array $row): string => (string) $row['key'], $permissions));

        return true;
    }
}
