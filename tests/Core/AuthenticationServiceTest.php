<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Auth;
use Core\AuthenticationService;
use Core\Config;
use Core\Database;
use Core\RateLimiter;
use Core\Session;
use Core\TenantManager;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    private Database $database;
    private Session $session;
    private Auth $auth;
    private TenantManager $tenantManager;
    private RateLimiter $rateLimiter;
    private AuthenticationService $service;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->session = new Session($config);
        $this->session->start();
        $this->auth = new Auth($this->session);
        $this->tenantManager = new TenantManager();
        $this->rateLimiter = new RateLimiter($this->session);
        $this->service = new AuthenticationService(
            $this->database,
            $this->auth,
            $this->session,
            $this->tenantManager,
            $this->rateLimiter,
            $config
        );

        $this->migrate();
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE TABLE site_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id BIGINT NOT NULL,
            domain VARCHAR(255) NOT NULL
        )');
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE TABLE roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            name VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE TABLE permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            `key` VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE TABLE role_permissions (
            role_id BIGINT NOT NULL,
            permission_id BIGINT NOT NULL
        )');
        $this->database->statement('CREATE TABLE user_site_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL
        )');
    }

    /** @return array{siteId: int, userId: int} */
    private function seedActiveUserWithRole(
        string $email = 'user@example.com',
        string $password = 'correct-password',
        string $status = 'active',
        string $roleName = 'editor',
        string $permissionKey = 'post.create',
    ): array {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO site_domains (site_id, domain) VALUES (?, ?)', [$siteId, 'example.com']);

        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', $email, \password_hash($password, PASSWORD_DEFAULT), $status]
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteId, $roleName]);
        $roleId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO permissions (`key`) VALUES (?)', [$permissionKey]);
        $permissionId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, $permissionId]
        );
        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );

        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);

        return ['siteId' => $siteId, 'userId' => $userId];
    }

    public function testAttemptSucceedsWithCorrectCredentials(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password');

        $result = $this->service->attempt('user@example.com', 'correct-password');

        self::assertTrue($result);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password');

        $result = $this->service->attempt('user@example.com', 'wrong-password');

        self::assertFalse($result);
    }

    public function testAttemptFailsWithUnknownEmail(): void
    {
        $this->seedActiveUserWithRole();

        $result = $this->service->attempt('unknown@example.com', 'any-password');

        self::assertFalse($result);
    }

    public function testAttemptFailsWhenUserStatusIsNotActive(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password', status: 'locked');

        $result = $this->service->attempt('user@example.com', 'correct-password');

        self::assertFalse($result);
    }

    public function testAttemptLoadsRolesIntoSession(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password', roleName: 'editor');

        $this->service->attempt('user@example.com', 'correct-password');

        self::assertSame(['editor'], $this->session->get('auth.roles'));
    }

    public function testAttemptLoadsPermissionsIntoSession(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password', permissionKey: 'post.create');

        $this->service->attempt('user@example.com', 'correct-password');

        self::assertSame(['post.create'], $this->session->get('auth.permissions'));
    }

    public function testAttemptUsesTenantManagerCurrentSiteId(): void
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteAId = (int) $this->database->connection()->lastInsertId();
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site B']);
        $siteBId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', 'user@example.com', \password_hash('correct-password', PASSWORD_DEFAULT), 'active']
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteAId, 'admin-a']);
        $roleAId = (int) $this->database->connection()->lastInsertId();
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteBId, 'admin-b']);
        $roleBId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)', [$userId, $siteAId, $roleAId]);
        $this->database->insert('INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)', [$userId, $siteBId, $roleBId]);

        $this->tenantManager->setCurrent($siteBId, ['id' => $siteBId]);

        $this->service->attempt('user@example.com', 'correct-password');

        self::assertSame(['admin-b'], $this->session->get('auth.roles'));
    }

    public function testAttemptCallsAuthLoginOnSuccess(): void
    {
        $seed = $this->seedActiveUserWithRole(password: 'correct-password');

        $this->service->attempt('user@example.com', 'correct-password');

        self::assertTrue($this->auth->check());
        self::assertSame($seed['userId'], $this->auth->id());
    }

    public function testAttemptThrowsLogicExceptionWhenNoTenantResolved(): void
    {
        $this->expectException(\LogicException::class);

        $this->service->attempt('user@example.com', 'any-password');
    }

    public function testAttemptUnknownEmailBehavesSameAsWrongPasswordWithoutThrowing(): void
    {
        $this->seedActiveUserWithRole();

        $unknownResult = $this->service->attempt('unknown@example.com', 'any-password');
        $wrongPasswordResult = $this->service->attempt('user@example.com', 'wrong-password');

        self::assertSame($wrongPasswordResult, $unknownResult);
        self::assertFalse($unknownResult);
    }

    public function testAttemptFailsAfterExceedingMaxAttempts(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password');

        // Fixture: auth.login_throttle.max_attempts = 3
        $this->service->attempt('user@example.com', 'wrong-password');
        $this->service->attempt('user@example.com', 'wrong-password');
        $this->service->attempt('user@example.com', 'wrong-password');

        // Da dat max_attempts (3 lan sai) - lan thu 4 bi khoa NGAY CA VOI PASSWORD DUNG.
        $result = $this->service->attempt('user@example.com', 'correct-password');

        self::assertFalse($result);
        self::assertFalse($this->auth->check());
    }

    public function testAttemptSucceedsBeforeReachingMaxAttempts(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password');

        $this->service->attempt('user@example.com', 'wrong-password');
        $this->service->attempt('user@example.com', 'wrong-password');

        // Chi 2/3 lan sai - van con quota, login dung phai thanh cong.
        $result = $this->service->attempt('user@example.com', 'correct-password');

        self::assertTrue($result);
    }

    public function testRateLimitKeyIsScopedPerEmail(): void
    {
        $this->seedActiveUserWithRole(email: 'victim@example.com', password: 'correct-password');

        $this->service->attempt('victim@example.com', 'wrong-password');
        $this->service->attempt('victim@example.com', 'wrong-password');
        $this->service->attempt('victim@example.com', 'wrong-password');

        // "victim@example.com" da bi khoa, nhung email khac (chua ton tai) khong bi anh huong.
        $otherEmailResult = $this->service->attempt('other@example.com', 'any-password');
        $victimResult = $this->service->attempt('victim@example.com', 'correct-password');

        self::assertFalse($victimResult, 'victim@example.com phai dang bi khoa.');
        self::assertFalse($otherEmailResult, 'other@example.com sai (unknown email), khong lien quan rate limit cua victim.');
        self::assertFalse($this->rateLimiter->tooManyAttempts('login:other@example.com', 3));
    }

    public function testAttemptClearsRateLimitOnSuccessfulPasswordVerify(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password');

        $this->service->attempt('user@example.com', 'wrong-password');
        $this->service->attempt('user@example.com', 'wrong-password');
        $this->service->attempt('user@example.com', 'correct-password');

        self::assertSame(0, $this->rateLimiter->attempts('login:user@example.com'));
    }

    /**
     * Hanh vi da duoc Owner chap thuan (khong phai bug): clear() goi ngay khi password_verify()
     * dung, TRUOC status check - tai khoan inactive dung dung password se luon reset ve 0, khong
     * bao gio bi tooManyAttempts() chan du goi vuot qua max_attempts (fixture: 3). Test nay khoa
     * hanh vi lai de tranh refactor sau nay vo tinh doi thu tu clear()/status check.
     */
    public function testRateLimitClearsEvenWhenInactiveAccountUsesCorrectPassword(): void
    {
        $this->seedActiveUserWithRole(password: 'correct-password', status: 'locked');

        for ($i = 0; $i < 6; $i++) {
            $result = $this->service->attempt('user@example.com', 'correct-password');

            self::assertFalse($result, "Lan goi thu {$i}: phai return false vi account inactive.");
        }

        self::assertFalse(
            $this->rateLimiter->tooManyAttempts('login:user@example.com', 3),
            'Khong bao gio bi rate-limit vi clear() luon reset counter sau moi lan password_verify() dung.'
        );
        self::assertSame(0, $this->rateLimiter->attempts('login:user@example.com'));
        self::assertFalse($this->auth->check());
    }
}
