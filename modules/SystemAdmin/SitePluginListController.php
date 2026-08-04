<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\SystemAdminAuth;
use Core\View;

/**
 * GET /system-admin/sites/{id}/plugins - catalog plugin cho DUNG 1 site, xem tu Super Admin (khong
 * can dang nhap vao domain cua site do). PluginActivationService nhan tenantId qua tham so tuong
 * minh (khong doc TenantManager) nen dung duoc thang tai day, giong cach modules/Admin/
 * PluginListController da dung cho Site Admin.
 */
final class SitePluginListController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly PluginActivationService $pluginActivation,
        private readonly PluginManager $pluginManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $siteId = (int) $request->routeParam('id');
        $site = $this->database->selectOne('SELECT id, name FROM sites WHERE id = ?', [$siteId]);

        if ($site === null) {
            return Response::html('404 Not Found', 404);
        }

        $enabledKeys = $this->pluginActivation->enabledKeysFor($siteId);

        $plugins = [];

        foreach ($this->pluginManager->discover() as $descriptor) {
            $plugins[] = [
                'key' => $descriptor->key,
                'name' => $descriptor->name,
                'version' => $descriptor->version,
                'description' => $descriptor->description,
                'is_active' => \in_array($descriptor->key, $enabledKeys, true),
            ];
        }

        $html = $this->view->render('system_admin.pages.sites.plugins', [
            'site' => $site,
            'plugins' => $plugins,
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
