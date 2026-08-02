<?php

declare(strict_types=1);

namespace Core;

/**
 * Wrapper DUY NHAT quanh $_SESSION/session_*() - khong noi nao khac trong he thong duoc dung
 * truc tiep. CHI la storage: start/doc-ghi/flash/regenerate/destroy - KHONG login/logout/kiem tra
 * quyen (thuoc AuthService, Phase sau).
 *
 * Lazy start: KHONG tu goi session_start() trong constructor - chi start() khi SessionMiddleware
 * hoac Controller goi, tranh tao session khong can thiet cho request public/API (dung JWT).
 *
 * Namespace: get()/set()/has()/remove() dung dot-notation long nhau GIONG HET Config::get() (nhat
 * quan kien truc) - vd 'auth.user_id' luu $_SESSION['auth']['user_id']. Quy uoc namespace da chuan
 * hoa: auth.user_id/auth.roles/auth.permissions, csrf.token, locale.current, tenant.current.
 * flash()/getFlash() dung bucket rieng (khong qua dot-notation) vi co vong doi khac (1 request).
 */
final class Session
{
    private const FLASH_OLD_KEY = '_flash_old';
    private const FLASH_NEW_KEY = '_flash_new';

    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        $this->applyCookieParams();
        \session_start();
        $this->ageFlashData();
    }

    public function isStarted(): bool
    {
        return \session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertStarted();

        $value = $_SESSION;

        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertStarted();

        $segments = \explode('.', $key);
        $lastSegment = \array_pop($segments);

        $target = &$_SESSION;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !\is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[$lastSegment] = $value;
    }

    public function has(string $key): bool
    {
        $this->assertStarted();

        $sentinel = new \stdClass();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function remove(string $key): void
    {
        $this->assertStarted();

        $segments = \explode('.', $key);
        $lastSegment = \array_pop($segments);

        $target = &$_SESSION;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !\is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$lastSegment]);
    }

    /** Ghi vao bucket se doc duoc o request KE TIEP (khong phai request hien tai). */
    public function flash(string $key, mixed $value): void
    {
        $this->assertStarted();

        $_SESSION[self::FLASH_NEW_KEY][$key] = $value;
    }

    /** Doc du lieu flash cua request truoc - tu dong het han o request sau do (xem ageFlashData()). */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->assertStarted();

        return $_SESSION[self::FLASH_OLD_KEY][$key] ?? $default;
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->assertStarted();

        \session_regenerate_id($deleteOldSession);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (!\headers_sent() && \ini_get('session.use_cookies')) {
            $params = \session_get_cookie_params();
            \setcookie(
                \session_name(),
                '',
                \time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    private function applyCookieParams(): void
    {
        \session_set_cookie_params([
            'lifetime' => ((int) $this->config->get('auth.session.lifetime_minutes', 120)) * 60,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) $this->config->get('auth.session.secure', false),
            'httponly' => (bool) $this->config->get('auth.session.http_only', true),
            'samesite' => (string) $this->config->get('auth.session.same_site', 'Lax'),
        ]);

        \session_name((string) $this->config->get('auth.session.cookie_name', 'cms_session'));
    }

    /**
     * Doi bucket flash cua request truoc (_flash_new) thanh bucket doc duoc cua request hien tai
     * (_flash_old), roi mo bucket moi rong cho request hien tai ghi. Dam bao flash chi song dung
     * 1 request ke tiep du co doc hay khong (khong phai "xoa khi doc").
     */
    private function ageFlashData(): void
    {
        $_SESSION[self::FLASH_OLD_KEY] = $_SESSION[self::FLASH_NEW_KEY] ?? [];
        $_SESSION[self::FLASH_NEW_KEY] = [];
    }

    private function assertStarted(): void
    {
        if (!$this->isStarted()) {
            throw new SessionException('Phai goi Session::start() truoc khi doc/ghi du lieu session.');
        }
    }
}
