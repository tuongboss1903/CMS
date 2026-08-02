<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Auth;
use Core\Config;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private Session $session;
    private Auth $auth;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
        $this->auth = new Auth($this->session);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testLoginStoresUserIdAndUserData(): void
    {
        $this->session->start();

        $this->auth->login(42, ['email' => 'a@example.com']);

        self::assertSame(42, $this->auth->id());
        self::assertSame(['email' => 'a@example.com'], $this->auth->user());
    }

    public function testLoginRegeneratesSessionId(): void
    {
        $this->session->start();
        $idBeforeLogin = \session_id();

        $this->auth->login(42);

        self::assertNotSame($idBeforeLogin, \session_id());
    }

    public function testLoginRemovesExistingCsrfToken(): void
    {
        $this->session->start();
        $this->session->set('csrf.token', 'old-token');

        $this->auth->login(42);

        self::assertFalse($this->session->has('csrf.token'));
    }

    public function testCheckReturnsFalseBeforeLogin(): void
    {
        $this->session->start();

        self::assertFalse($this->auth->check());
    }

    public function testCheckReturnsTrueAfterLogin(): void
    {
        $this->session->start();
        $this->auth->login(42);

        self::assertTrue($this->auth->check());
    }

    public function testIdReturnsNullBeforeLogin(): void
    {
        $this->session->start();

        self::assertNull($this->auth->id());
    }

    public function testIdReturnsStoredUserIdAfterLogin(): void
    {
        $this->session->start();
        $this->auth->login('user-abc');

        self::assertSame('user-abc', $this->auth->id());
    }

    public function testUserReturnsNullBeforeLogin(): void
    {
        $this->session->start();

        self::assertNull($this->auth->user());
    }

    public function testUserReturnsStoredArrayAfterLogin(): void
    {
        $this->session->start();
        $this->auth->login(42, ['name' => 'Alice', 'email' => 'alice@example.com']);

        self::assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $this->auth->user());
    }

    public function testLoginWithEmptyUserArrayReturnsEmptyArrayNotNull(): void
    {
        $this->session->start();
        $this->auth->login(42);

        self::assertSame([], $this->auth->user());
        self::assertNotNull($this->auth->user());
    }

    public function testLogoutClearsAuthenticationState(): void
    {
        $this->session->start();
        $this->auth->login(42, ['email' => 'a@example.com']);

        $this->auth->logout();

        // Session::destroy() ket thuc phien hien tai (isStarted() tro ve false) - dung vong doi
        // 1 request MOI se tu start() lai qua cookie; mo phong dieu do o day truoc khi doc lai
        // trang thai, thay vi doc ngay sau logout() trong cung 1 session da bi huy.
        $this->session->start();

        self::assertFalse($this->auth->check());
        self::assertNull($this->auth->id());
    }

    public function testLogoutDestroysEntireSessionNotJustAuthKeys(): void
    {
        $this->session->start();
        $this->session->set('locale.current', 'vi');
        $this->auth->login(42);

        $this->auth->logout();

        self::assertSame([], $_SESSION);
    }

    public function testLogoutIsSafeWhenSessionNeverStarted(): void
    {
        self::assertFalse($this->session->isStarted());

        $this->auth->logout();

        self::assertFalse($this->session->isStarted());
    }
}
