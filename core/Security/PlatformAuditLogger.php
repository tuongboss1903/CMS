<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Database;
use Core\Http\Request;
use Core\SystemAdminAuth;
use Throwable;

/**
 * Ban sao co chu dich cua Core\Security\AuditLogger, danh RIENG cho hanh dong cua Super Admin
 * (platform_admins) - KHONG tai su dung AuditLogger vi bang audit_logs gan user_id vao FK users,
 * khong the luu platform_admin_id vao do ma khong vi pham FK/lan nghia 2 khai niem khac nhau
 * (dung tien le tach identity da chot tu Buoc 1 - CMS-062). Ghi vao platform_audit_logs rieng.
 *
 * SILENT-FAIL TUYET DOI, giong nguyen AuditLogger - ghi audit log KHONG BAO GIO duoc lam gian
 * doan request chinh cua Super Admin.
 */
final class PlatformAuditLogger
{
    public function __construct(
        private readonly Database $database,
        private readonly SystemAdminAuth $auth,
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function log(
        Request $request,
        string $event,
        ?int $siteId = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        try {
            $adminId = $this->auth->check() ? $this->auth->id() : null;

            $this->database->insert(
                'INSERT INTO platform_audit_logs (platform_admin_id, site_id, event, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $adminId !== null ? (int) $adminId : null,
                    $siteId,
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
