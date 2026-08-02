<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** POST /admin/comments/{id}/approve - Phase 14 (CMS-051). 404 cross-tenant (an danh su ton tai). */
final class CommentApproveController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('comment.moderate')) {
            return Response::html('403 Forbidden', 403);
        }

        $commentId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM comments WHERE id = ? AND tenant_id = ?',
            [$commentId, $siteId]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->statement(
            "UPDATE comments SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$commentId]
        );

        return Response::redirect('/admin/comments');
    }
}
