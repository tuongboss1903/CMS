<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\SystemAdminAuth;

/** POST /system-admin/sites/{id}/plugins/{key}/toggle - chi cho phep toggle plugin da THAT SU discover() duoc. */
final class SitePluginToggleController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Database $database,
        private readonly PluginActivationService $pluginActivation,
        private readonly PluginManager $pluginManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $siteId = (int) $request->routeParam('id');
        $site = $this->database->selectOne('SELECT id FROM sites WHERE id = ?', [$siteId]);

        if ($site === null) {
            return Response::html('404 Not Found', 404);
        }

        $pluginKey = (string) $request->routeParam('key');

        if (!isset($this->pluginManager->discover()[$pluginKey])) {
            return Response::html('404 Not Found', 404);
        }

        if ($this->pluginActivation->isActive($siteId, $pluginKey)) {
            $this->pluginActivation->deactivate($siteId, $pluginKey);
        } else {
            $this->pluginActivation->activate($siteId, $pluginKey);
        }

        return Response::redirect("/system-admin/sites/{$siteId}/plugins");
    }
}
