<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Config;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\RateLimiter;
use Core\Session;
use Core\TenantManager;
use Core\Validator;
use Modules\Admin\NotificationService;

/**
 * POST /{slug}/comments - Phase 14 (Comment/Review System, CMS-051). Khach KHONG can dang nhap
 * (guest_name/guest_email nhap tay, khong tao users moi). status luon 'pending' khi tao moi -
 * moderation-first (Owner Decision Architecture Analysis), KHONG hien thi cong khai cho toi khi
 * Admin duyet qua modules/Admin/CommentApproveController.php.
 *
 * Anti-spam: tai dung nguyen core/RateLimiter.php (Session-based, cung mo hinh
 * AuthenticationService dung cho login throttle) - 5 lan/10 phut/session, khong CAPTCHA/dependency
 * ngoai (Zero-dependency). ip_hash dung ky thuat sha256+app.key giong AnalyticsService (Phase 12).
 *
 * Redirect kem Flash Message - dung quy uoc chinh thuc CMS-017 (khong tu sinh JSON API o day,
 * Public form la SSR thuan).
 *
 * Phase 15 (Notification & Email System, CMS-052): sau khi INSERT comments thanh cong, goi
 * NotificationService::notifyAdmins() (in-app + email cho moi Admin thuoc tenant). Tu than
 * notifyAdmins() da silent-fail noi bo (xem docblock NotificationService) - KHONG boc them try/catch
 * o day, dung nguyen tac "1 diem chiu trach nhiem silent-fail" da ap dung cho Mailer.
 */
final class CommentSubmitController
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 600;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly NotificationService $notificationService,
        private readonly RateLimiter $rateLimiter,
        private readonly Session $session,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug');
        $tenantId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND slug = ? AND status = ? AND deleted_at IS NULL',
            [$tenantId, $slug, 'published']
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $rateLimitKey = 'comment:' . $tenantId;

        if ($this->rateLimiter->tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            $this->session->flash('comment_errors', ['rate_limit' => ['Bạn gửi quá nhiều bình luận, vui lòng thử lại sau.']]);

            return Response::redirect('/' . $slug);
        }

        $data = $request->all();
        $result = $this->validator->validate($data, [
            'guest_name' => 'required|string',
            'guest_email' => 'required|email',
            'body' => 'required|string',
        ]);

        if ($result->fails()) {
            $this->session->flash('comment_errors', $result->errors());

            return Response::redirect('/' . $slug);
        }

        $this->rateLimiter->hit($rateLimitKey, self::MAX_ATTEMPTS, self::DECAY_SECONDS);

        $this->database->insert(
            'INSERT INTO comments (tenant_id, entity_type, entity_id, guest_name, guest_email, body, status, ip_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                'page',
                (int) $page['id'],
                (string) $data['guest_name'],
                (string) $data['guest_email'],
                (string) $data['body'],
                'pending',
                $this->hashIp($request->ip()),
            ]
        );

        $commentId = (int) $this->database->connection()->lastInsertId();

        $this->notificationService->notifyAdmins(
            'comment.new',
            'comment',
            $commentId,
            'Bình luận mới từ ' . (string) $data['guest_name'],
            (string) $data['body'],
            'Bình luận mới cần duyệt',
            'emails.comment_new',
            [
                'guest_name' => $data['guest_name'],
                'page_title' => $page['title'],
                'body' => $data['body'],
                'admin_url' => '/admin/comments',
            ]
        );

        $this->session->flash('comment_success', 'Bình luận của bạn đã được gửi, đang chờ Quản trị viên duyệt.');

        return Response::redirect('/' . $slug);
    }

    private function hashIp(string $ip): string
    {
        return \hash('sha256', $ip . (string) $this->config->get('app.key', ''));
    }
}
