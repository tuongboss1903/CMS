<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class PermissionServiceFixture
{
    public function __construct(public readonly UserServiceFixture $userService)
    {
    }
}
