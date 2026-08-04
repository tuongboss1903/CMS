<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\PlatformAuditLogger;
use Core\SystemAdminAuth;

/**
 * POST /system-admin/plans/{id}/toggle - an/hien goi khoi danh sach gan MOI (khong xoa, khong anh
 * huong site dang dung goi nay - xem docblock migration create_plans_table).
 */
final class PlanToggleActiveController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Database $database,
        private readonly PlatformAuditLogger $platformAuditLogger,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $planId = (int) $request->routeParam('id');
        $plan = $this->database->selectOne('SELECT id, is_active FROM plans WHERE id = ?', [$planId]);

        if ($plan === null) {
            return Response::html('404 Not Found', 404);
        }

        $newIsActive = (bool) $plan['is_active'] ? 0 : 1;
        $this->database->statement('UPDATE plans SET is_active = ? WHERE id = ?', [$newIsActive, $planId]);
        $this->platformAuditLogger->log($request, 'plan.toggle_active', null, 'plan', $planId, newValues: ['is_active' => $newIsActive]);

        return Response::redirect('/system-admin/plans');
    }
}
