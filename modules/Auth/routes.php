<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->post('/login', [\Modules\Auth\LoginController::class, 'handle']);
$router->post('/logout', [\Modules\Auth\LogoutController::class, 'handle']);
