<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/ping', fn (\Core\Http\Request $request): \Core\Http\Response => \Core\Http\Response::html('pong'));

$router->get('/boom', function (\Core\Http\Request $request): \Core\Http\Response {
    throw new \RuntimeException('boom-test');
});
