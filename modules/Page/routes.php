<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/pages', [\Modules\Page\ListPagesController::class, 'handle']);
$router->post('/pages', [\Modules\Page\CreatePageController::class, 'handle']);
$router->patch('/pages/{id}', [\Modules\Page\EditPageController::class, 'handle']);
$router->delete('/pages/{id}', [\Modules\Page\DeletePageController::class, 'handle']);
$router->post('/pages/{id}/publish', [\Modules\Page\PublishPageController::class, 'handle']);
$router->post('/pages/{id}/homepage', [\Modules\Page\SetHomepageController::class, 'handle']);
