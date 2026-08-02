<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\TenantManager;

/**
 * POST /admin/comments/{id}/reject - Phase 14 (CMS-051). 'rejected' la trang thai an vinh vien
 * (khong xoa) - phuc vu chong gui lai/dieu tra spam sau nay, khac voi Delete (xoa that).
 *
 * Phase 15 (CMS-052): xem docblock CommentApproveController.php - cung logic JOIN + gui mail,
 * trung lap co chu dich.
 */
final class CommentRejectController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly Mailer $mailer,
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

        $comment = $this->database->selectOne(
            "SELECT comments.id, comments.guest_name, comments.guest_email, pages.title as page_title
             FROM comments
             INNER JOIN pages ON pages.id = comments.entity_id AND comments.entity_type = 'page'
             WHERE comments.id = ? AND comments.tenant_id = ?",
            [$commentId, $siteId]
        );

        if ($comment === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->statement(
            "UPDATE comments SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$commentId]
        );

        $this->mailer->send(
            (string) $comment['guest_email'],
            'Binh luan cua ban khong duoc duyet',
            'emails.comment_rejected',
            [
                'guest_name' => $comment['guest_name'],
                'page_title' => $comment['page_title'],
            ]
        );

        return Response::redirect('/admin/comments');
    }
}
