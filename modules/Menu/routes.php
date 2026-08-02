<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/menus', [\Modules\Menu\ListMenusController::class, 'handle']);
$router->post('/menus', [\Modules\Menu\CreateMenuController::class, 'handle']);
$router->get('/menus/{id}', [\Modules\Menu\ShowMenuController::class, 'handle']);
$router->patch('/menus/{id}', [\Modules\Menu\UpdateMenuController::class, 'handle']);
$router->delete('/menus/{id}', [\Modules\Menu\DeleteMenuController::class, 'handle']);
$router->post('/menus/{id}/items', [\Modules\Menu\CreateMenuItemController::class, 'handle']);
$router->patch('/menu-items/{id}', [\Modules\Menu\UpdateMenuItemController::class, 'handle']);
$router->delete('/menu-items/{id}', [\Modules\Menu\DeleteMenuItemController::class, 'handle']);
