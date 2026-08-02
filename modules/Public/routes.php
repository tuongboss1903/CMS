<?php

declare(strict_types=1);

use Core\Middleware\AnalyticsTrackingMiddleware;

/** @var \Core\Router $router */
$router->get('/', [\Modules\Public\HomeController::class, 'handle'], [AnalyticsTrackingMiddleware::class]);
$router->get('/search', [\Modules\Public\SearchController::class, 'handle']);
$router->get('/{slug}', [\Modules\Public\PublicPageController::class, 'handle'], [AnalyticsTrackingMiddleware::class]);
