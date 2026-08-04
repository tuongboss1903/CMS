<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/roles', [\Modules\Role\ListRolesController::class, 'handle']);
$router->get('/permissions', [\Modules\Role\ListPermissionsController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->post('/roles', [\Modules\Role\CreateRoleController::class, 'handle']);
    $router->patch('/roles/{id}', [\Modules\Role\EditRoleController::class, 'handle']);
    $router->delete('/roles/{id}', [\Modules\Role\DeleteRoleController::class, 'handle']);
    $router->post('/roles/{id}/permissions', [\Modules\Role\AssignPermissionController::class, 'handle']);
});
