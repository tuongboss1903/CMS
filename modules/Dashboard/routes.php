<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/dashboard', [\Modules\Dashboard\DashboardController::class, 'handle']);
