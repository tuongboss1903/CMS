<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/users', [\Modules\User\ListUsersController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->post('/users', [\Modules\User\CreateUserController::class, 'handle']);
    $router->patch('/users/{id}', [\Modules\User\EditUserController::class, 'handle']);
    $router->post('/users/{id}/lock', [\Modules\User\LockUserController::class, 'handle']);
    $router->post('/users/{id}/unlock', [\Modules\User\UnlockUserController::class, 'handle']);
    $router->post('/users/{id}/role', [\Modules\User\AssignRoleController::class, 'handle']);
});
