<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class RoleServiceFixture
{
    public function __construct(public readonly PermissionServiceFixture $permissionService)
    {
    }
}
