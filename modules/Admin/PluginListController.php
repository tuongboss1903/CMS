<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\TenantManager;
use Core\View;

/** GET /admin/plugins - Phase 19 (CMS-056), dong Technical Debt #9. Liet ke MOI plugin da discover() kem trang thai bat/tat cua DUNG tenant hien tai. */
final class PluginListController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly PluginActivationService $pluginActivation,
        private readonly PluginManager $pluginManager,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        if (!$this->authorization->can('plugin.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $tenantId = (string) $this->tenantManager->id();
        $enabledKeys = $this->pluginActivation->enabledKeysFor($tenantId);

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

        $html = $this->view->render('admin.pages.plugins.list', [
            'plugins' => $plugins,
            'breadcrumb_items' => [['label' => 'Plugins']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
