<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class UserServiceFixture
{
    public function __construct(public readonly RoleServiceFixture $roleService)
    {
    }
}
