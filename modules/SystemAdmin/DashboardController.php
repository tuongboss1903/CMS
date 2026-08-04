<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/**
 * GET /system-admin/dashboard - Buoc 3. Thong ke XUYEN toan he thong (khong loc theo 1 site nao -
 * dung y muc dich cua Super Admin, khac han modules/Admin/DashboardController chi thay 1 tenant).
 * Hoat dong gan day UNION 2 nguon: audit_logs (hanh dong cua Site Admin/User tren TAT CA site) va
 * platform_audit_logs (hanh dong cua chinh Super Admin) - kem ten site khi co the JOIN duoc.
 */
final class DashboardController
{
    private const ACTIVITY_LIMIT = 10;

    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Database $database,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $siteCountsByStatus = $this->fetchSiteCountsByStatus();

        $html = $this->view->render('system_admin.pages.dashboard', [
            'total_sites' => \array_sum($siteCountsByStatus),
            'site_counts_by_status' => $siteCountsByStatus,
            'total_users' => $this->fetchScalarCount('SELECT COUNT(*) as c FROM users'),
            'total_platform_admins' => $this->fetchScalarCount('SELECT COUNT(*) as c FROM platform_admins'),
            'activity' => $this->fetchActivity(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }

    /** @return array<string, int> */
    private function fetchSiteCountsByStatus(): array
    {
        $rows = $this->database->select('SELECT status, COUNT(*) as c FROM sites GROUP BY status');
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        return $counts;
    }

    private function fetchScalarCount(string $sql): int
    {
        try {
            return (int) ($this->database->selectOne($sql)['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array{source: string, event: string, label: string, event_at: string|null}> */
    private function fetchActivity(): array
    {
        try {
            return $this->database->select(
                "SELECT 'site' as source, audit_logs.event as event,
                        COALESCE(sites.name, 'Khong xac dinh') as label,
                        audit_logs.created_at as event_at
                    FROM audit_logs LEFT JOIN sites ON sites.id = audit_logs.tenant_id
                 UNION ALL
                 SELECT 'platform' as source, platform_audit_logs.event as event,
                        COALESCE(sites.name, 'He thong') as label,
                        platform_audit_logs.created_at as event_at
                    FROM platform_audit_logs LEFT JOIN sites ON sites.id = platform_audit_logs.site_id
                 ORDER BY event_at DESC
                 LIMIT " . self::ACTIVITY_LIMIT
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
