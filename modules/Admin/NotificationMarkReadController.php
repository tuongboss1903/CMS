<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/notifications/{id}/read - kiem tra so huu (tenant_id + user_id) truoc khi goi
 * NotificationService::markAsRead() (ham do chi loc theo tenant_id, KHONG loc user_id - kiem tra
 * o day de tranh 1 user danh dau "da doc" thong bao cua user khac cung tenant).
 */
final class NotificationMarkReadController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly NotificationService $notificationService,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $notificationId = (int) $request->routeParam('id');
        $notification = $this->database->selectOne(
            'SELECT id FROM notifications WHERE id = ? AND tenant_id = ? AND user_id = ?',
            [$notificationId, $this->tenantManager->id(), $this->auth->id()]
        );

        if ($notification === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->notificationService->markAsRead($notificationId);

        return Response::redirect('/admin/notifications');
    }
}
