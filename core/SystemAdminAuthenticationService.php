<?php

declare(strict_types=1);

namespace Core;

/**
 * Ban sao co chu dich cua Core\AuthenticationService, danh RIENG cho platform_admins - KHONG
 * yeu cau TenantManager::check() (Super Admin khong thuoc ve 1 site nao), KHONG tra roles/
 * permissions theo site (Buoc 1 chi can 1 vai tro phang "Super Admin", chua co bang permission
 * rieng cho platform - YAGNI, bo sung khi co nhu cau that).
 *
 * Giu nguyen 2 bien phap chong brute-force/user enumeration da co trong AuthenticationService:
 * dummy hash cho email khong ton tai, rate limit qua RateLimiter (key "system_admin_login:{email}").
 */
final class SystemAdminAuthenticationService
{
    private const DUMMY_HASH = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

    public function __construct(
        private readonly Database $database,
        private readonly SystemAdminAuth $auth,
        private readonly RateLimiter $rateLimiter,
        private readonly Config $config,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $rateLimitKey = 'system_admin_login:' . \strtolower($email);
        $maxAttempts = (int) $this->config->get('auth.login_throttle.max_attempts', 5);
        $decaySeconds = (int) $this->config->get('auth.login_throttle.decay_seconds', 900);

        if ($this->rateLimiter->tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return false;
        }

        $admin = $this->database->selectOne(
            'SELECT id, name, password, status FROM platform_admins WHERE email = ?',
            [$email]
        );

        $passwordHash = $admin !== null ? (string) $admin['password'] : self::DUMMY_HASH;

        if (!\password_verify($password, $passwordHash)) {
            $this->rateLimiter->hit($rateLimitKey, $maxAttempts, $decaySeconds);

            return false;
        }

        $this->rateLimiter->clear($rateLimitKey);

        if ($admin === null || $admin['status'] !== 'active') {
            return false;
        }

        $adminId = (int) $admin['id'];

        $this->auth->login($adminId, [
            'id' => $adminId,
            'email' => $email,
            'name' => (string) $admin['name'],
        ]);

        return true;
    }
}
