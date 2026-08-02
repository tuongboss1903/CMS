<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Settings\SiteSettingsManager;

/** GET /admin/settings - form Global Site Settings cho tenant hien tai. */
final class SettingShowEditController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly SiteSettingsManager $settings,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $images = $this->database->select(
            "SELECT id, file_name FROM media WHERE tenant_id = ? AND mime_type LIKE 'image/%' ORDER BY file_name ASC",
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.settings.edit', [
            'settings' => $this->settings->get(),
            'images' => $images,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
