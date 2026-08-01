<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Authorization;
use Core\Config;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    private Session $session;
    private Authorization $authorization;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
        $this->authorization = new Authorization($this->session);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testRolesReturnsEmptyArrayByDefault(): void
    {
        $this->session->start();

        self::assertSame([], $this->authorization->roles());
    }

    public function testRolesReturnsStoredValues(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['admin', 'editor']);

        self::assertSame(['admin', 'editor'], $this->authorization->roles());
    }

    public function testPermissionsReturnsEmptyArrayByDefault(): void
    {
        $this->session->start();

        self::assertSame([], $this->authorization->permissions());
    }

    public function testPermissionsReturnsStoredValues(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.edit', 'post.delete']);

        self::assertSame(['post.edit', 'post.delete'], $this->authorization->permissions());
    }

    public function testHasRoleTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['admin']);

        self::assertTrue($this->authorization->hasRole('admin'));
    }

    public function testHasRoleFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['editor']);

        self::assertFalse($this->authorization->hasRole('admin'));
    }

    public function testHasAnyRoleTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['editor']);

        self::assertTrue($this->authorization->hasAnyRole(['admin', 'editor']));
    }

    public function testHasAnyRoleFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['viewer']);

        self::assertFalse($this->authorization->hasAnyRole(['admin', 'editor']));
    }

    public function testHasAllRolesTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['admin', 'editor', 'viewer']);

        self::assertTrue($this->authorization->hasAllRoles(['admin', 'editor']));
    }

    public function testHasAllRolesFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.roles', ['admin']);

        self::assertFalse($this->authorization->hasAllRoles(['admin', 'editor']));
    }

    public function testHasPermissionTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.edit']);

        self::assertTrue($this->authorization->hasPermission('post.edit'));
    }

    public function testHasPermissionFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.view']);

        self::assertFalse($this->authorization->hasPermission('post.edit'));
    }

    public function testHasAnyPermissionTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.view']);

        self::assertTrue($this->authorization->hasAnyPermission(['post.edit', 'post.view']));
    }

    public function testHasAnyPermissionFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.view']);

        self::assertFalse($this->authorization->hasAnyPermission(['post.edit', 'post.delete']));
    }

    public function testHasAllPermissionsTrue(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.edit', 'post.delete', 'post.view']);

        self::assertTrue($this->authorization->hasAllPermissions(['post.edit', 'post.delete']));
    }

    public function testHasAllPermissionsFalse(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.edit']);

        self::assertFalse($this->authorization->hasAllPermissions(['post.edit', 'post.delete']));
    }

    public function testCanIsAliasOfHasPermission(): void
    {
        $this->session->start();
        $this->session->set('auth.permissions', ['post.edit']);

        self::assertTrue($this->authorization->can('post.edit'));
        self::assertFalse($this->authorization->can('post.delete'));
    }
}
