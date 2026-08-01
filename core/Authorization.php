<?php

declare(strict_types=1);

namespace Core;

/**
 * Doc roles/permissions tu Session (namespace auth.roles/auth.permissions, da du tru tu CMS-007)
 * - thuan doc, khong ghi, khong DB, khong biet Auth. Du lieu duoc Module khac ghi vao Session truoc
 * (qua Session::set() cong khai san co), Authorization khong validate noi dung.
 */
final class Authorization
{
    private const ROLES_KEY = 'auth.roles';
    private const PERMISSIONS_KEY = 'auth.permissions';

    public function __construct(private readonly Session $session)
    {
    }

    /** @return list<string> */
    public function roles(): array
    {
        return (array) $this->session->get(self::ROLES_KEY, []);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return (array) $this->session->get(self::PERMISSIONS_KEY, []);
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles(), true);
    }

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        $current = $this->roles();

        foreach ($roles as $role) {
            if (\in_array($role, $current, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $roles */
    public function hasAllRoles(array $roles): bool
    {
        $current = $this->roles();

        foreach ($roles as $role) {
            if (!\in_array($role, $current, true)) {
                return false;
            }
        }

        return true;
    }

    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->permissions(), true);
    }

    /** @param list<string> $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        $current = $this->permissions();

        foreach ($permissions as $permission) {
            if (\in_array($permission, $current, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $permissions */
    public function hasAllPermissions(array $permissions): bool
    {
        $current = $this->permissions();

        foreach ($permissions as $permission) {
            if (!\in_array($permission, $current, true)) {
                return false;
            }
        }

        return true;
    }

    /** Alias thuan cua hasPermission() - khong them logic role/policy/gate. */
    public function can(string $permission): bool
    {
        return $this->hasPermission($permission);
    }
}
