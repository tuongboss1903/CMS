<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Database;
use Core\Http\Request;
use Core\Session;
use Core\TenantManager;
use Throwable;

/**
 * Phase 16 (Security & Audit Log System, CMS-053). Service inject qua constructor (KHONG static
 * facade) - dung dung mo hinh AnalyticsService/NotificationService da giu xuyen suot du an, tranh
 * them 1 ngoai le static/global thu 3 ngoai Session/Translator da co (xem core-architecture.md
 * muc 4 - "Khong static/global mutable state o bat ky dau").
 *
 * log() nhan Request TUONG MINH (khong the tu "bat" tu dau do toan cuc) - Request khong nam trong
 * Container, chi duoc truyen qua tham so handle(Request $request) cua tung Controller (xem
 * core/Application.php/core/Router.php) - Controller goi AuditLogger da co san bien nay.
 *
 * SILENT-FAIL TUYET DOI (bat buoc theo yeu cau Owner): ghi audit log KHONG BAO GIO duoc lam gian
 * doan request chinh cua nguoi dung, du DB loi tam thoi hay bang audit_logs chua ton tai.
 */
final class AuditLogger
{
    public function __construct(
        private readonly Database $database,
        private readonly Session $session,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldValues Gia tri TRUOC thay doi (null neu khong ap dung, vd tao moi)
     * @param array<string, mixed>|null $newValues Gia tri SAU thay doi (null neu khong ap dung, vd xoa/dang xuat)
     */
    public function log(
        Request $request,
        string $event,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        try {
            $userId = $this->session->isStarted() ? $this->session->get('auth.user_id') : null;

            $this->database->insert(
                'INSERT INTO audit_logs (tenant_id, user_id, event, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $this->tenantManager->id(),
                    $userId !== null ? (int) $userId : null,
                    $event,
                    $auditableType,
                    $auditableId,
                    $oldValues !== null ? \json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                    $newValues !== null ? \json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                    $request->ip(),
                    $request->userAgent(),
                ]
            );
        } catch (Throwable) {
            // Silent-fail co chu dich - xem docblock class.
        }
    }
}
