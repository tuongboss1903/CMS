<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/seo/{entity_type}/{entity_id}', [\Modules\Seo\ShowSeoMetaController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->patch('/seo/{entity_type}/{entity_id}', [\Modules\Seo\UpdateSeoMetaController::class, 'handle']);
});
