<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** POST /admin/notifications/read-all - danh dau toan bo thong bao CUA CHINH user hien tai (trong tenant hien tai) la da doc. */
final class NotificationMarkAllReadController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $this->database->statement(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL',
            [$this->tenantManager->id(), $this->auth->id()]
        );

        return Response::redirect('/admin/notifications');
    }
}
