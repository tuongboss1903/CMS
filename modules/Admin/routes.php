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
$router->get('/admin/pages', [\Modules\Admin\PageListController::class, 'handle']);
$router->get('/admin/pages/create', [\Modules\Admin\PageShowCreateController::class, 'handle']);
$router->get('/admin/pages/{id}/edit', [\Modules\Admin\PageShowEditController::class, 'handle']);
$router->get('/admin/media', [\Modules\Admin\MediaListController::class, 'handle']);
$router->get('/admin/media/{id}/file', [\Modules\Admin\MediaFileController::class, 'handle']);
$router->get('/admin/menus', [\Modules\Admin\MenuListController::class, 'handle']);
$router->get('/admin/menus/{id}', [\Modules\Admin\MenuShowController::class, 'handle']);
$router->get('/admin/seo', [\Modules\Admin\SeoListController::class, 'handle']);
$router->get('/admin/seo/pages/{id}', [\Modules\Admin\SeoShowEditController::class, 'handle']);
$router->get('/admin/settings', [\Modules\Admin\SettingShowEditController::class, 'handle']);
$router->get('/admin/comments', [\Modules\Admin\CommentListController::class, 'handle']);
$router->get('/admin/audit-logs', [\Modules\Admin\AuditLogController::class, 'handle']);
$router->get('/admin/system-settings', [\Modules\Admin\SystemSettingListController::class, 'handle']);

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
    $router->post('/admin/pages', [\Modules\Admin\PageCreateController::class, 'handle']);
    $router->post('/admin/pages/{id}', [\Modules\Admin\PageUpdateController::class, 'handle']);
    $router->post('/admin/pages/{id}/delete', [\Modules\Admin\PageDeleteController::class, 'handle']);
    $router->post('/admin/pages/{id}/publish', [\Modules\Admin\PagePublishController::class, 'handle']);
    $router->post('/admin/pages/{id}/homepage', [\Modules\Admin\PageSetHomepageController::class, 'handle']);
    $router->post('/admin/media', [\Modules\Admin\MediaUploadController::class, 'handle']);
    $router->post('/admin/media/{id}', [\Modules\Admin\MediaUpdateController::class, 'handle']);
    $router->post('/admin/media/{id}/delete', [\Modules\Admin\MediaDeleteController::class, 'handle']);
    $router->post('/admin/menus', [\Modules\Admin\MenuCreateController::class, 'handle']);
    $router->post('/admin/menus/{id}', [\Modules\Admin\MenuUpdateController::class, 'handle']);
    $router->post('/admin/menus/{id}/delete', [\Modules\Admin\MenuDeleteController::class, 'handle']);
    $router->post('/admin/menus/{id}/items', [\Modules\Admin\MenuItemCreateController::class, 'handle']);
    $router->post('/admin/menu-items/{id}', [\Modules\Admin\MenuItemUpdateController::class, 'handle']);
    $router->post('/admin/menu-items/{id}/delete', [\Modules\Admin\MenuItemDeleteController::class, 'handle']);
    $router->post('/admin/seo/pages/{id}', [\Modules\Admin\SeoUpdateController::class, 'handle']);
    $router->post('/admin/settings', [\Modules\Admin\SettingUpdateController::class, 'handle']);
    $router->post('/admin/comments/{id}/approve', [\Modules\Admin\CommentApproveController::class, 'handle']);
    $router->post('/admin/comments/{id}/reject', [\Modules\Admin\CommentRejectController::class, 'handle']);
    $router->post('/admin/comments/{id}/delete', [\Modules\Admin\CommentDeleteController::class, 'handle']);
    $router->post('/admin/system-settings', [\Modules\Admin\SystemSettingSaveController::class, 'handle']);
    $router->post('/admin/system-settings/{id}/delete', [\Modules\Admin\SystemSettingDeleteController::class, 'handle']);
});
