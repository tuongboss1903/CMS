<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Http\Request;
use Core\Http\Response;
use Core\ModuleManager;
use Core\SystemAdminAuth;
use Core\View;

/**
 * GET /system-admin/modules - catalog CHI DOC toan bo Module da discover() tren he thong.
 * KHONG co toggle bat/tat - Module luon duoc ModuleManager::boot() cho MOI tenant (xem CLAUDE.md
 * "luon duoc ModuleManager boot cho moi tenant"), khac han Plugin (bat/tat duoc theo tung site).
 */
final class ModuleListController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly ModuleManager $moduleManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $modules = [];

        foreach ($this->moduleManager->discover() as $descriptor) {
            $modules[] = [
                'key' => $descriptor->key,
                'name' => $descriptor->name,
                'version' => $descriptor->version,
                'dependencies' => $descriptor->dependencies,
            ];
        }

        $html = $this->view->render('system_admin.pages.modules.list', [
            'modules' => $modules,
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
