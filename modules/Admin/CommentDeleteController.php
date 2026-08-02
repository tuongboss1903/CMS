<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** POST /admin/comments/{id}/delete - Phase 14 (CMS-051). Hard delete (khong deleted_at - day la log/UGC, khong phai entity nghiep vu can soft-delete, dung tien le analytics_views). */
final class CommentDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('comment.delete')) {
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

        $this->database->statement('DELETE FROM comments WHERE id = ?', [$commentId]);

        return Response::redirect('/admin/comments');
    }
}
