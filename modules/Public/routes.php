<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/', [\Modules\Public\HomeController::class, 'handle']);
$router->get('/search', [\Modules\Public\SearchController::class, 'handle']);
$router->get('/{slug}', [\Modules\Public\PublicPageController::class, 'handle']);
