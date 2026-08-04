<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/notifications - Buoc 5 (Notification UI, CMS-066), dong gap NotificationService
 * (CMS-052) chua co Controller/View nao dung den. Danh cho ca nhan (user dang nhap), khong qua
 * Authorization::can() nhu cac list Controller khac - moi user chi xem duoc thong bao CUA CHINH
 * MINH (loc them user_id ngoai tenant_id, khac AuditLogController xem toan tenant).
 */
final class NotificationListController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $tenantId = $this->tenantManager->id();
        $userId = $this->auth->id();
        $page = \max(1, (int) ($request->query('page') ?? 1));

        $total = (int) ($this->database->selectOne(
            'SELECT COUNT(*) as count FROM notifications WHERE tenant_id = ? AND user_id = ?',
            [$tenantId, $userId]
        )['count'] ?? 0);
        $totalPages = \max(1, (int) \ceil($total / self::PER_PAGE));
        $page = \min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $notifications = $this->database->select(
            'SELECT id, type, notifiable_type, notifiable_id, title, body, read_at, created_at
                FROM notifications WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . self::PER_PAGE . " OFFSET {$offset}",
            [$tenantId, $userId]
        );

        $html = $this->view->render('admin.pages.notifications.list', [
            'notifications' => $notifications,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'csrf_token' => $this->csrf->token(),
            'breadcrumb_items' => [['label' => 'Thong bao']],
        ]);

        return Response::html($html);
    }
}
