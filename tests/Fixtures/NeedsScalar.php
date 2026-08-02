<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class NeedsScalar
{
    public function __construct(public readonly string $value)
    {
    }
}
