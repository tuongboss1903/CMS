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

/**
 * GET /admin/system-settings - Phase 17 (CMS-054). Danh sach setting key-value cua tenant, gom
 * nhom theo setting_group. Gia tri is_encrypted KHONG BAO GIO giai ma hien thi o day (chi hien
 * "********") - tranh lo bi mat (vd SMTP password) qua man hinh danh sach, du Admin co quyen xem.
 */
final class SystemSettingListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $rows = $this->database->select(
            'SELECT id, setting_group, `key`, value, is_encrypted FROM settings WHERE tenant_id = ? ORDER BY setting_group ASC, `key` ASC',
            [$this->tenantManager->id()]
        );

        $grouped = [];

        foreach ($rows as $row) {
            $group = (string) $row['setting_group'];
            $grouped[$group][] = [
                'id' => (int) $row['id'],
                'key' => $row['key'],
                'value' => ((int) $row['is_encrypted']) === 1 ? '********' : $row['value'],
                'is_encrypted' => ((int) $row['is_encrypted']) === 1,
            ];
        }

        $html = $this->view->render('admin.pages.system_settings.list', [
            'breadcrumb_items' => [['label' => 'Cấu hình hệ thống']],
            'grouped' => $grouped,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
