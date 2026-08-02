<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Core\Http\Request;
use Core\Http\Response;

final class TestController
{
    public function show(Request $request): Response
    {
        return Response::json(['id' => $request->routeParam('id')]);
    }

    public function index(Request $request): Response
    {
        return Response::html('CONTROLLER');
    }
}
