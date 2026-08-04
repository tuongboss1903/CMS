<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/**
 * GET /system-admin/audit-logs - xem audit_logs (hanh dong Site Admin/User) XUYEN TAT CA site,
 * dong Technical Debt ghi trong modules/Admin/AuditLogController.php ("co che Super Admin
 * xuyen-tenant chua ton tai o bat ky dau"). Day la 1 trong 2 NGOAI LE duy nhat duoc phep doc
 * audit_logs khong loc theo tenant_id (Super Admin) - moi Controller khac (Modules\Admin) van
 * PHAI cach ly tenant tuyet doi nhu truoc.
 */
final class AuditLogController
{
    private const PER_PAGE = 20;

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

        $siteId = \trim((string) ($request->query('site_id') ?? ''));
        $event = \trim((string) ($request->query('event') ?? ''));
        $dateFrom = \trim((string) ($request->query('date_from') ?? ''));
        $dateTo = \trim((string) ($request->query('date_to') ?? ''));
        $page = \max(1, (int) ($request->query('page') ?? 1));

        $conditions = [];
        $bindings = [];

        if ($siteId !== '') {
            $conditions[] = 'audit_logs.tenant_id = ?';
            $bindings[] = (int) $siteId;
        }

        if ($event !== '') {
            $conditions[] = 'audit_logs.event = ?';
            $bindings[] = $event;
        }

        if ($dateFrom !== '') {
            $conditions[] = 'audit_logs.created_at >= ?';
            $bindings[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $conditions[] = 'audit_logs.created_at <= ?';
            $bindings[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . \implode(' AND ', $conditions);

        $total = (int) ($this->database->selectOne(
            "SELECT COUNT(*) as count FROM audit_logs{$where}",
            $bindings
        )['count'] ?? 0);
        $totalPages = \max(1, (int) \ceil($total / self::PER_PAGE));
        $page = \min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $logs = $this->database->select(
            "SELECT audit_logs.*, sites.name as site_name
                FROM audit_logs LEFT JOIN sites ON sites.id = audit_logs.tenant_id
                {$where}
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $bindings
        );

        $sites = $this->database->select('SELECT id, name FROM sites ORDER BY name ASC');
        $eventRows = $this->database->select('SELECT DISTINCT event FROM audit_logs ORDER BY event ASC');

        $html = $this->view->render('system_admin.pages.audit_logs.list', [
            'logs' => $logs,
            'sites' => $sites,
            'available_events' => \array_map(static fn (array $row): string => (string) $row['event'], $eventRows),
            'filters' => ['site_id' => $siteId, 'event' => $event, 'date_from' => $dateFrom, 'date_to' => $dateTo],
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
