<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Modules\Settings\SettingManager;

/** POST /admin/system-settings/{id}/delete - Phase 17 (CMS-054). 404 cross-tenant. */
final class SystemSettingDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly SettingManager $settingManager,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $id = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $row = $this->database->selectOne(
            'SELECT `key` FROM settings WHERE id = ? AND tenant_id = ?',
            [$id, $siteId]
        );

        if ($row === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->settingManager->forget((string) $row['key']);

        return Response::redirect('/admin/system-settings');
    }
}
