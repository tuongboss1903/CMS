<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Core\Http\Request;
use Core\Http\Response;

/** Controller co constructor dependency - kiem tra ControllerResolver van auto-wiring dung qua Container. */
final class DependentController
{
    public function __construct(private readonly GreetingService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->service->greet());
    }
}
