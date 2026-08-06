<?php

declare(strict_types=1);

namespace Modules\Admin;

/**
 * Helper hien thi cho Audit Log (Dashboard + /admin/audit-logs) - gom 3 trach nhiem trinh bay lien
 * quan: dich ma su kien sang tieng Viet, mau badge theo muc do, va rut gon IP noi bo thanh nhan
 * than thien (Design Audit Phase 24 - truoc day hien "auth.login_success"/"127.0.0.1" tho, kho doc
 * voi nguoi dung khong ky thuat).
 */
final class AuditLogPresenter
{
    private const EVENT_LABELS = [
        'auth.login_success' => 'Đăng nhập thành công',
        'auth.login_failed' => 'Đăng nhập thất bại',
        'auth.logout' => 'Đăng xuất',
        'page.created' => 'Tạo trang mới',
        'page.updated' => 'Cập nhật trang',
        'page.deleted' => 'Xóa trang',
        'comment.approved' => 'Duyệt bình luận',
        'comment.rejected' => 'Từ chối bình luận',
        'settings.updated' => 'Cập nhật cấu hình',
    ];

    private const EVENT_VARIANTS = [
        'auth.login_success' => 'success',
        'auth.login_failed' => 'danger',
        'auth.logout' => 'neutral',
        'page.created' => 'success',
        'page.updated' => 'neutral',
        'page.deleted' => 'danger',
        'comment.approved' => 'success',
        'comment.rejected' => 'danger',
        'settings.updated' => 'neutral',
    ];

    /**
     * IP noi bo/vong lap thuong gap khi chay local/tren cung may chu (khong co gia tri phan tich
     * voi nguoi dung thuong) - rut gon thanh nhan de hieu, IP goc van xem duoc qua title attribute
     * o view (khong an thong tin, chi khong phoi tho ngay hang dau).
     */
    private const IP_LABELS = [
        '127.0.0.1' => 'Hệ thống (local)',
        '::1' => 'Hệ thống (local)',
    ];

    public static function eventLabel(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? $event;
    }

    public static function eventBadgeClass(string $event): string
    {
        return 'badge-' . (self::EVENT_VARIANTS[$event] ?? 'neutral');
    }

    public static function ipLabel(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'Không xác định';
        }

        return self::IP_LABELS[$ip] ?? $ip;
    }
}
