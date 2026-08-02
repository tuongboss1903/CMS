<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Database;
use Core\Mail\Mailer;
use Core\TenantManager;
use Throwable;

/**
 * Phase 15 (Notification & Email System, CMS-052). Dat trong modules/Admin/ theo dung duong dan
 * Owner da chi dinh - Ghi nhan: notifyAdmins() duoc goi tu Modules\Public\CommentSubmitController
 * (cross-module reference, khac tien le AnalyticsService dat o modules/Analytics/ khong thuoc
 * module nao ca) - khong sai ky thuat (PSR-4 khong quan tam module.json), chi la 1 lua chon to
 * chuc thu muc khac tien le truoc, giu nguyen theo yeu cau Owner.
 *
 * notifyAdmins() tao 1 dong notifications (in-app) + goi Mailer cho MOI user thuoc tenant hien tai
 * (JOIN user_site_roles, dung pattern DashboardController::user_count). Toan bo boc try/catch NOI
 * BO - SILENT-FAIL tuyet doi (nguyen tac bat buoc Phase 15): loi ghi bang notifications (vd bang
 * chua ton tai o fixture test cu) KHONG duoc lam gian doan luong nghiep vu chinh (gui Comment).
 * Mailer::send() ban than da tu silent-fail (xem Core\Mail\Mailer) nen khong can boc rieng.
 */
final class NotificationService
{
    public function __construct(
        private readonly Database $database,
        private readonly Mailer $mailer,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @param array<string, mixed> $emailData */
    public function notifyAdmins(
        string $type,
        string $notifiableType,
        int $notifiableId,
        string $title,
        string $body,
        string $emailSubject,
        string $emailTemplate,
        array $emailData = [],
    ): void {
        try {
            $tenantId = $this->tenantManager->id();

            $admins = $this->database->select(
                'SELECT users.id, users.email FROM users
                 INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
                 WHERE user_site_roles.site_id = ?',
                [$tenantId]
            );

            foreach ($admins as $admin) {
                $this->database->insert(
                    'INSERT INTO notifications (tenant_id, user_id, type, notifiable_type, notifiable_id, title, body)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$tenantId, (int) $admin['id'], $type, $notifiableType, $notifiableId, $title, $body]
                );

                $this->mailer->send((string) $admin['email'], $emailSubject, $emailTemplate, $emailData);
            }
        } catch (Throwable) {
            // Silent-fail co chu dich - xem docblock class.
        }
    }

    public function markAsRead(int $notificationId): void
    {
        $this->database->statement(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [$notificationId, $this->tenantManager->id()]
        );
    }

    public function unreadCount(int $userId): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM notifications WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL',
            [$this->tenantManager->id(), $userId]
        );

        return (int) ($row['count'] ?? 0);
    }
}
