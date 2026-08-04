<?php

declare(strict_types=1);

namespace Core;

/**
 * Verify email/password that tu database, goi Auth::login() (khong doi API), nap roles/permissions
 * that vao Session theo site hien tai (TenantManager::id()). Khong Repository, khong route/Controller
 * - Module tuong lai tu goi attempt() truc tiep.
 *
 * Chong user enumeration: email khong ton tai van chay password_verify() voi DUMMY_HASH, tra ve
 * dung 1 diem false giong het truong hop sai password - khong phan biet ly do that bai.
 *
 * Rate limit brute-force qua RateLimiter (key "login:{lowercase_email}", config
 * auth.login_throttle.*): tooManyAttempts() kiem tra TRUOC password_verify(); hit() CHI goi khi
 * password_verify() that bai; clear() khi password_verify() thanh cong - khong hit() cho lan
 * goi thanh cong (dung dinh nghia "brute-force" la doan mat khau sai lien tuc, khong phai
 * status khong active sau khi da dung mat khau).
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
        private readonly RateLimiter $rateLimiter,
        private readonly Config $config,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        if (!$this->tenantManager->check()) {
            throw new \LogicException(
                'AuthenticationService::attempt() yeu cau tenant hien tai da duoc resolve (TenantManager::check() === false).'
            );
        }

        $rateLimitKey = 'login:' . \strtolower($email);
        $maxAttempts = (int) $this->config->get('auth.login_throttle.max_attempts', 5);
        $decaySeconds = (int) $this->config->get('auth.login_throttle.decay_seconds', 900);

        if ($this->rateLimiter->tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return false;
        }

        $user = $this->database->selectOne(
            'SELECT id, name, password, status FROM users WHERE email = ?',
            [$email]
        );

        $passwordHash = $user !== null ? (string) $user['password'] : self::DUMMY_HASH;

        if (!\password_verify($password, $passwordHash)) {
            $this->rateLimiter->hit($rateLimitKey, $maxAttempts, $decaySeconds);

            return false;
        }

        $this->rateLimiter->clear($rateLimitKey);

        if ($user === null || $user['status'] !== 'active') {
            return false;
        }

        $userId = (int) $user['id'];

        $this->auth->login($userId, [
            'id' => $userId,
            'email' => $email,
            'name' => (string) $user['name'],
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
