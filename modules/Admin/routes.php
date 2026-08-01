<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/admin/login', [\Modules\Admin\ShowLoginController::class, 'handle']);
$router->get('/admin/dashboard', [\Modules\Admin\DashboardController::class, 'handle']);
$router->get('/admin/users', [\Modules\Admin\UserListController::class, 'handle']);
$router->get('/admin/users/create', [\Modules\Admin\UserShowCreateController::class, 'handle']);
$router->get('/admin/users/{id}/edit', [\Modules\Admin\UserShowEditController::class, 'handle']);
$router->get('/admin/roles', [\Modules\Admin\RoleListController::class, 'handle']);
$router->get('/admin/roles/create', [\Modules\Admin\RoleShowCreateController::class, 'handle']);
$router->get('/admin/roles/{id}/edit', [\Modules\Admin\RoleShowEditController::class, 'handle']);
$router->get('/admin/roles/{id}/permissions', [\Modules\Admin\RoleShowPermissionsController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->post('/admin/login', [\Modules\Admin\LoginController::class, 'handle']);
    $router->post('/admin/logout', [\Modules\Admin\LogoutController::class, 'handle']);
    $router->post('/admin/users', [\Modules\Admin\UserCreateController::class, 'handle']);
    $router->post('/admin/users/{id}', [\Modules\Admin\UserUpdateController::class, 'handle']);
    $router->post('/admin/users/{id}/lock', [\Modules\Admin\UserLockController::class, 'handle']);
    $router->post('/admin/users/{id}/unlock', [\Modules\Admin\UserUnlockController::class, 'handle']);
    $router->post('/admin/users/{id}/role', [\Modules\Admin\UserAssignRoleController::class, 'handle']);
    $router->post('/admin/roles', [\Modules\Admin\RoleCreateController::class, 'handle']);
    $router->post('/admin/roles/{id}', [\Modules\Admin\RoleUpdateController::class, 'handle']);
    $router->post('/admin/roles/{id}/delete', [\Modules\Admin\RoleDeleteController::class, 'handle']);
    $router->post('/admin/roles/{id}/permissions', [\Modules\Admin\RoleAssignPermissionsController::class, 'handle']);
});
