<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\ThemeManager;
use Core\View;

/** GET /system-admin/themes - catalog toan bo Theme da discover() tren he thong (gan cho site qua SiteUpdateController). */
final class ThemeListController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly ThemeManager $themeManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $themes = [];

        foreach ($this->themeManager->discover() as $descriptor) {
            $themes[] = [
                'key' => $descriptor->key,
                'name' => $descriptor->name,
                'version' => $descriptor->version,
                'screenshot' => $descriptor->screenshot,
            ];
        }

        $html = $this->view->render('system_admin.pages.themes.list', [
            'themes' => $themes,
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
