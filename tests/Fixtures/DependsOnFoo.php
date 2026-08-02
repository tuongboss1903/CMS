<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class DependsOnFoo
{
    public function __construct(public readonly FooInterface $foo)
    {
    }
}
