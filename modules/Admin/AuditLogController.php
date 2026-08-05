<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/audit-logs - Phase 16 (Security & Audit Log System, CMS-053). Cach ly tenant TUYET
 * DOI, khong ngoai le SuperAdmin (co che Super Admin xuyen-tenant chua ton tai o bat ky dau trong
 * du an - config/tenants.php chi khai bao route_prefix '/system-admin' du kien, chua co
 * Route/Controller/Authorization nao dung den - Technical Debt da ghi nhan tu truoc CMS-030).
 *
 * Phan trang thu cong dau tien cua du an (chua co helper Paginator chung - MVP toi gian, chi
 * LIMIT/OFFSET tinh tay, dung tien le "khong tao abstraction khi chi co 1 noi dung").
 */
final class AuditLogController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('audit_log.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $tenantId = $this->tenantManager->id();
        $event = \trim((string) ($request->query('event') ?? ''));
        $dateFrom = \trim((string) ($request->query('date_from') ?? ''));
        $dateTo = \trim((string) ($request->query('date_to') ?? ''));
        $page = \max(1, (int) ($request->query('page') ?? 1));

        $conditions = ['tenant_id = ?'];
        $bindings = [$tenantId];

        if ($event !== '') {
            $conditions[] = 'event = ?';
            $bindings[] = $event;
        }

        if ($dateFrom !== '') {
            $conditions[] = 'created_at >= ?';
            $bindings[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $conditions[] = 'created_at <= ?';
            $bindings[] = $dateTo . ' 23:59:59';
        }

        $where = \implode(' AND ', $conditions);

        $total = (int) ($this->database->selectOne("SELECT COUNT(*) as count FROM audit_logs WHERE {$where}", $bindings)['count'] ?? 0);
        $totalPages = \max(1, (int) \ceil($total / self::PER_PAGE));
        $page = \min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $logs = $this->database->select(
            "SELECT * FROM audit_logs WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $bindings
        );

        $eventRows = $this->database->select(
            'SELECT DISTINCT event FROM audit_logs WHERE tenant_id = ? ORDER BY event ASC',
            [$tenantId]
        );

        $html = $this->view->render('admin.pages.audit_logs.list', [
            'breadcrumb_items' => [['label' => 'Nhật ký hoạt động']],
            'logs' => $logs,
            'available_events' => \array_map(static fn (array $row): string => (string) $row['event'], $eventRows),
            'filters' => ['event' => $event, 'date_from' => $dateFrom, 'date_to' => $dateTo],
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);

        return Response::html($html);
    }
}
