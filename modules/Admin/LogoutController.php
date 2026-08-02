<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuditLogger;

/**
 * POST /admin/logout - CSRF bat buoc (qua route group), khong GET logout (Owner Decision CMS-045).
 *
 * Phase 16 (CMS-053): ghi "auth.logout" TRUOC khi goi Auth::logout() - logout() xoa Session nen
 * user_id se khong con doc duoc sau do.
 */
final class LogoutController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Auth $auth,
    ) {
    }

    public function handle(Request $request): Response
    {
        $userId = $this->auth->id();
        $this->auditLogger->log($request, 'auth.logout', 'user', $userId !== null ? (int) $userId : null);

        $this->auth->logout();

        return Response::redirect('/admin/login');
    }
}
