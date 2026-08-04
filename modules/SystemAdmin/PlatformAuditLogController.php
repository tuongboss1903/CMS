<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/platform-audit-logs - nhat ky hanh dong CUA CHINH Super Admin (platform_audit_logs). */
final class PlatformAuditLogController
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

        $event = \trim((string) ($request->query('event') ?? ''));
        $page = \max(1, (int) ($request->query('page') ?? 1));

        $conditions = [];
        $bindings = [];

        if ($event !== '') {
            $conditions[] = 'platform_audit_logs.event = ?';
            $bindings[] = $event;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . \implode(' AND ', $conditions);

        $total = (int) ($this->database->selectOne(
            "SELECT COUNT(*) as count FROM platform_audit_logs{$where}",
            $bindings
        )['count'] ?? 0);
        $totalPages = \max(1, (int) \ceil($total / self::PER_PAGE));
        $page = \min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $logs = $this->database->select(
            "SELECT platform_audit_logs.*, sites.name as site_name, platform_admins.name as admin_name
                FROM platform_audit_logs
                LEFT JOIN sites ON sites.id = platform_audit_logs.site_id
                LEFT JOIN platform_admins ON platform_admins.id = platform_audit_logs.platform_admin_id
                {$where}
             ORDER BY platform_audit_logs.created_at DESC, platform_audit_logs.id DESC
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $bindings
        );

        $eventRows = $this->database->select('SELECT DISTINCT event FROM platform_audit_logs ORDER BY event ASC');

        $html = $this->view->render('system_admin.pages.audit_logs.platform_list', [
            'logs' => $logs,
            'available_events' => \array_map(static fn (array $row): string => (string) $row['event'], $eventRows),
            'filters' => ['event' => $event],
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
