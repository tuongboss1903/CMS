<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/comments - Phase 14 (Comment/Review System, CMS-051). Loc theo ?status=
 * (pending|approved|rejected), mac dinh 'pending' (dung viec Admin can lam nhat truoc - moderation
 * queue). JOIN pages de hien thi tieu de trang - entity_type luon 'page' o MVP (dung tien le
 * seo_meta CMS-043).
 */
final class CommentListController
{
    /** @var list<string> */
    private const VALID_STATUSES = ['pending', 'approved', 'rejected'];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('comment.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $status = (string) ($request->query('status') ?? 'pending');

        if (!\in_array($status, self::VALID_STATUSES, true)) {
            $status = 'pending';
        }

        $comments = $this->database->select(
            "SELECT comments.id, comments.guest_name, comments.guest_email, comments.body,
                    comments.status, comments.created_at, pages.title as page_title
             FROM comments
             INNER JOIN pages ON pages.id = comments.entity_id AND comments.entity_type = 'page'
             WHERE comments.tenant_id = ? AND comments.status = ?
             ORDER BY comments.created_at DESC",
            [$this->tenantManager->id(), $status]
        );

        $html = $this->view->render('admin.pages.comments.list', [
            'breadcrumb_items' => [['label' => 'Bình luận']],
            'comments' => $comments,
            'status' => $status,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
