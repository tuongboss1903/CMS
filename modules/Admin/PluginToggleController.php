<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\TenantManager;

/** POST /admin/plugins/{key}/toggle - Phase 19 (CMS-056). Chi cho phep toggle plugin da THAT SU discover() duoc (khong nhan key tuy y tu client). */
final class PluginToggleController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly PluginActivationService $pluginActivation,
        private readonly PluginManager $pluginManager,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('plugin.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $pluginKey = (string) $request->routeParam('key');

        if (!isset($this->pluginManager->discover()[$pluginKey])) {
            return Response::html('404 Not Found', 404);
        }

        $tenantId = (string) $this->tenantManager->id();

        if ($this->pluginActivation->isActive($tenantId, $pluginKey)) {
            $this->pluginActivation->deactivate($tenantId, $pluginKey);
        } else {
            $this->pluginActivation->activate($tenantId, $pluginKey);
        }

        return Response::redirect('/admin/plugins');
    }
}
