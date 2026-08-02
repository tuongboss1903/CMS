<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

final class GreetingService
{
    public function greet(): string
    {
        return 'hello-from-service';
    }
}
