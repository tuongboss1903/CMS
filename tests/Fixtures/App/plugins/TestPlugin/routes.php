<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/plugin-ping', [\Tests\Fixtures\App\Plugins\TestPlugin\PluginPingController::class, 'handle']);
