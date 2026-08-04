<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/system-admin/login', [\Modules\SystemAdmin\ShowLoginController::class, 'handle']);
$router->get('/system-admin/dashboard', [\Modules\SystemAdmin\DashboardController::class, 'handle']);
$router->get('/system-admin/audit-logs', [\Modules\SystemAdmin\AuditLogController::class, 'handle']);
$router->get('/system-admin/platform-audit-logs', [\Modules\SystemAdmin\PlatformAuditLogController::class, 'handle']);
$router->get('/system-admin/sites', [\Modules\SystemAdmin\SiteListController::class, 'handle']);
$router->get('/system-admin/sites/create', [\Modules\SystemAdmin\SiteShowCreateController::class, 'handle']);
$router->get('/system-admin/sites/{id}/edit', [\Modules\SystemAdmin\SiteShowEditController::class, 'handle']);
$router->get('/system-admin/sites/{id}/plugins', [\Modules\SystemAdmin\SitePluginListController::class, 'handle']);
$router->get('/system-admin/modules', [\Modules\SystemAdmin\ModuleListController::class, 'handle']);
$router->get('/system-admin/themes', [\Modules\SystemAdmin\ThemeListController::class, 'handle']);
$router->get('/system-admin/plans', [\Modules\SystemAdmin\PlanListController::class, 'handle']);
$router->get('/system-admin/plans/create', [\Modules\SystemAdmin\PlanShowCreateController::class, 'handle']);
$router->get('/system-admin/plans/{id}/edit', [\Modules\SystemAdmin\PlanShowEditController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->post('/system-admin/login', [\Modules\SystemAdmin\LoginController::class, 'handle']);
    $router->post('/system-admin/logout', [\Modules\SystemAdmin\LogoutController::class, 'handle']);
    $router->post('/system-admin/plans', [\Modules\SystemAdmin\PlanCreateController::class, 'handle']);
    $router->post('/system-admin/plans/{id}', [\Modules\SystemAdmin\PlanUpdateController::class, 'handle']);
    $router->post('/system-admin/plans/{id}/toggle', [\Modules\SystemAdmin\PlanToggleActiveController::class, 'handle']);
    $router->post('/system-admin/sites', [\Modules\SystemAdmin\SiteCreateController::class, 'handle']);
    $router->post('/system-admin/sites/{id}', [\Modules\SystemAdmin\SiteUpdateController::class, 'handle']);
    $router->post('/system-admin/sites/{id}/suspend', [\Modules\SystemAdmin\SiteSuspendController::class, 'handle']);
    $router->post('/system-admin/sites/{id}/activate', [\Modules\SystemAdmin\SiteActivateController::class, 'handle']);
    $router->post('/system-admin/sites/{id}/domains', [\Modules\SystemAdmin\SiteDomainAddController::class, 'handle']);
    $router->post('/system-admin/site-domains/{id}/delete', [\Modules\SystemAdmin\SiteDomainDeleteController::class, 'handle']);
    $router->post('/system-admin/sites/{id}/plugins/{key}/toggle', [\Modules\SystemAdmin\SitePluginToggleController::class, 'handle']);
});
