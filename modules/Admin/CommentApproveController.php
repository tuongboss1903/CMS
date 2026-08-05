<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\Security\AuditLogger;
use Core\TenantManager;

/**
 * POST /admin/comments/{id}/approve - Phase 14 (CMS-051). 404 cross-tenant (an danh su ton tai).
 *
 * Phase 15 (CMS-052): JOIN pages de lay guest_name/guest_email/page_title/page_slug, gui email
 * qua Mailer sau khi doi status. Mailer::send() tu than da silent-fail (khong throw), nen khong
 * can boc try/catch o day.
 *
 * Phase 16 (CMS-053): ghi "comment.approved".
 */
final class CommentApproveController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
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
            "SELECT comments.id, comments.guest_name, comments.guest_email, pages.title as page_title, pages.slug as page_slug
             FROM comments
             INNER JOIN pages ON pages.id = comments.entity_id AND comments.entity_type = 'page'
             WHERE comments.id = ? AND comments.tenant_id = ?",
            [$commentId, $siteId]
        );

        if ($comment === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->statement(
            "UPDATE comments SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$commentId]
        );

        $this->mailer->send(
            (string) $comment['guest_email'],
            'Bình luận của bạn đã được duyệt',
            'emails.comment_approved',
            [
                'guest_name' => $comment['guest_name'],
                'page_title' => $comment['page_title'],
                'page_url' => '/' . $comment['page_slug'],
            ]
        );

        $this->auditLogger->log($request, 'comment.approved', 'comment', $commentId, ['status' => 'pending'], ['status' => 'approved']);

        return Response::redirect('/admin/comments');
    }
}
