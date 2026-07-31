<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/alpha', fn (\Core\Http\Request $request): \Core\Http\Response => \Core\Http\Response::html('alpha'));
