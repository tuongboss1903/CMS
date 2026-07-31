<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/boom', function (\Core\Http\Request $request): \Core\Http\Response {
    throw new \RuntimeException('secret-internal-detail');
});
