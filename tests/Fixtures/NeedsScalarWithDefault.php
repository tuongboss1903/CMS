<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class NeedsScalarWithDefault
{
    public function __construct(public readonly string $value = 'default')
    {
    }
}
