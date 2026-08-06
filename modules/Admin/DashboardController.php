<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Analytics\AnalyticsService;

/**
 * GET /admin/dashboard - Auth::check() tu xu ly redirect HTML (khong AuthMiddleware - class do
 * tra JSON 401, sai voi flow HTML). Query user_count/role_count copy tu Modules\Dashboard\
 * DashboardController (khong goi lai Controller do, khong sua modules/Dashboard/*).
 *
 * Phase 7 (UI/UX Demo Polish): bo sung page_count/media_count + Activity Stream (UNION Page/Media/
 * User theo thoi gian gan nhat - Fork C1 Owner Approved: KHONG gom Menu vi bang menus/menu_items
 * khong co cot timestamp nao, tranh phai them migration moi ngoai pham vi Phase 7). Giu nguyen
 * user_count/role_count (ten field, kieu du lieu) - tests/Core/AdminUiFoundationTest.php dang
 * assert truc tiep vao 2 gia tri nay.
 *
 * Phase 12 (Advanced Analytics Dashboard, CMS-049): bo sung AnalyticsService (Total Views/Unique
 * Visitors/Top Pages/bieu do 7 ngay, mac dinh chu ky '7d') - tenant isolation da xu ly ben trong
 * Service (moi query tu loc theo TenantManager::id() hien tai).
 *
 * Phase 18 follow-up (sau CMS-055, truoc Demo v0.2.0): bo sung recent_audit_logs/system_health -
 * hoan tat "khung cho" da dung san trong dashboard.php (isset() guard). Chi bo sung du lieu THUAN
 * (khong doi HTTP status/redirect/cau truc response cu) - dung lai dung 1 pattern try/catch fallback
 * rong da co san o fetchAnalyticsSummary() (bang audit_logs co the chua ton tai o fixture test cu
 * nhu AdminUiFoundationTest.php).
 */
final class DashboardController
{
    private const ACTIVITY_LIMIT = 8;

    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $siteId = $this->tenantManager->id();

        $userCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE user_site_roles.site_id = ?',
            [$siteId]
        );

        $roleCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$siteId]
        );

        $pageCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM pages WHERE tenant_id = ? AND deleted_at IS NULL',
            [$siteId]
        );

        $mediaCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM media WHERE tenant_id = ?',
            [$siteId]
        );

        $analytics = $this->fetchAnalyticsSummary();

        $html = $this->view->render('admin.pages.dashboard', [
            'user_count' => (int) $userCount['count'],
            'role_count' => (int) $roleCount['count'],
            'page_count' => (int) $pageCount['count'],
            'media_count' => (int) $mediaCount['count'],
            'activity' => $this->fetchActivity($siteId),
            'total_views' => $analytics['total_views'],
            'unique_visitors' => $analytics['unique_visitors'],
            'total_views_trend' => $analytics['total_views_trend'],
            'unique_visitors_trend' => $analytics['unique_visitors_trend'],
            'top_pages' => $analytics['top_pages'],
            'daily_views' => $analytics['daily_views'],
            'recent_audit_logs' => $this->fetchRecentAuditLogs($siteId),
            'system_health' => $this->fetchSystemHealth(),
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }

    /**
     * Bang analytics_views (CMS-049) chua chac ton tai o moi fixture test cu (vd
     * AdminUiFoundationTest.php) - bat Throwable, fallback rong, cung nguyen tac da ap dung cho
     * bang media o PageShowCreateController/PageShowEditController (Phase 11).
     *
     * Design Audit Phase 8: bo sung *_trend (so voi ky truoc, AnalyticsService::
     * previousPeriodSummary()) - CHI cho total_views/unique_visitors vi day la 2 chi so THEO KY
     * duy nhat co du lieu ky truoc that su, khac page/media/user/role count (tong tich luy).
     *
     * @return array{total_views: int, unique_visitors: int, total_views_trend: array{direction: string, percent: int|null}, unique_visitors_trend: array{direction: string, percent: int|null}, top_pages: list<array{path: string, views: int}>, daily_views: list<array{date: string, views: int}>}
     */
    private function fetchAnalyticsSummary(): array
    {
        try {
            $totalViews = $this->analyticsService->totalViews('7d');
            $uniqueVisitors = $this->analyticsService->uniqueVisitors('7d');
            $previous = $this->analyticsService->previousPeriodSummary('7d');

            return [
                'total_views' => $totalViews,
                'unique_visitors' => $uniqueVisitors,
                'total_views_trend' => $this->calculateTrend($totalViews, $previous['total_views']),
                'unique_visitors_trend' => $this->calculateTrend($uniqueVisitors, $previous['unique_visitors']),
                'top_pages' => $this->analyticsService->topPages('7d', 5),
                'daily_views' => $this->analyticsService->dailyViews(7),
            ];
        } catch (\Throwable) {
            $emptyDays = [];

            for ($i = 6; $i >= 0; $i--) {
                $emptyDays[] = ['date' => \date('Y-m-d', \time() - $i * 86400), 'views' => 0];
            }

            $flatTrend = ['direction' => 'flat', 'percent' => null];

            return [
                'total_views' => 0,
                'unique_visitors' => 0,
                'total_views_trend' => $flatTrend,
                'unique_visitors_trend' => $flatTrend,
                'top_pages' => [],
                'daily_views' => $emptyDays,
            ];
        }
    }

    /**
     * @return array{direction: string, percent: int|null}
     */
    private function calculateTrend(int $current, int $previous): array
    {
        if ($current === $previous) {
            return ['direction' => 'flat', 'percent' => 0];
        }

        $direction = $current > $previous ? 'up' : 'down';

        if ($previous === 0) {
            // Ky truoc = 0 -> % tang la vo cuc, khong the tinh trung thuc. Chi hien mui ten,
            // khong bia con so.
            return ['direction' => $direction, 'percent' => null];
        }

        return ['direction' => $direction, 'percent' => (int) \round((($current - $previous) / $previous) * 100)];
    }

    /**
     * UNION 3 nguon (Page/Media/User) sap xep theo thoi gian gan nhat - khong gom Menu (bang
     * menus/menu_items khong co cot timestamp, Owner Decision Fork C1 Phase 7).
     *
     * @return list<array{type: string, label: string, event_at: string|null}>
     */
    private function fetchActivity(int|string|null $siteId): array
    {
        return $this->database->select(
            "SELECT 'page' as type, title as label, COALESCE(updated_at, created_at) as event_at
                FROM pages WHERE tenant_id = ? AND deleted_at IS NULL
             UNION ALL
             SELECT 'media' as type, file_name as label, created_at as event_at
                FROM media WHERE tenant_id = ?
             UNION ALL
             SELECT 'user' as type, users.name as label, users.created_at as event_at
                FROM users INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
                WHERE user_site_roles.site_id = ?
             ORDER BY event_at DESC
             LIMIT " . self::ACTIVITY_LIMIT,
            [$siteId, $siteId, $siteId]
        );
    }

    /**
     * Bang audit_logs (CMS-053) chua chac ton tai o moi fixture test cu (vd AdminUiFoundationTest.php
     * dung schema toi gian hon) - bat Throwable, fallback rong, dung nguyen tac da ap dung cho bang
     * analytics_views o fetchAnalyticsSummary() phia tren.
     *
     * LEFT JOIN users (Design Audit Phase 24) - View truoc day hien "user#1" tho, doi sang Avatar +
     * ten that (user_name null neu user_id null - vd dang nhap that bai - hoac user da bi xoa).
     *
     * @return list<array{event: string, created_at: string, user_id: int|null, user_name: string|null}>
     */
    private function fetchRecentAuditLogs(int|string|null $siteId): array
    {
        try {
            return $this->database->select(
                'SELECT audit_logs.event, audit_logs.created_at, audit_logs.user_id, users.name AS user_name
                    FROM audit_logs
                    LEFT JOIN users ON users.id = audit_logs.user_id
                    WHERE audit_logs.tenant_id = ?
                    ORDER BY audit_logs.created_at DESC LIMIT 5',
                [$siteId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * MVP toi gian - chi dung du lieu da co san (khong them dependency moi vao constructor de giu
     * rui ro thay doi thap nhat co the o 1 sua doi bo sung ngoai Phase). Mo rong sau neu can (vd
     * Cache driver status, Storage writable) o 1 task rieng co Architecture Review.
     *
     * @return list<array{label: string, value: string, ok: bool}>
     */
    /** @return list<array{label: string, value: string, ok: bool}> */
    private function fetchSystemHealth(): array
    {
        $items = [
            ['label' => 'Kết nối Database', 'value' => 'OK', 'ok' => true],
            ['label' => 'PHP Runtime', 'value' => \PHP_VERSION, 'ok' => true],
        ];

        /**
         * Bang "plans" (CMS-065) co the chua ton tai o fixture test cu chua migrate them - cung
         * nguyen tac try/catch fallback nhu fetchAnalyticsSummary()/fetchRecentAuditLogs() - khong
         * hien thi muc nay thay vi lam vo Dashboard neu query loi.
         */
        try {
            $row = $this->database->selectOne(
                'SELECT sites.storage_used_bytes, plans.max_storage_mb
                    FROM sites LEFT JOIN plans ON plans.id = sites.plan_id
                    WHERE sites.id = ?',
                [$this->tenantManager->id()]
            );

            if ($row !== null) {
                $usedMb = (int) \round(((int) $row['storage_used_bytes']) / 1024 / 1024, 1);
                $limitMb = $row['max_storage_mb'] !== null ? (int) $row['max_storage_mb'] : null;

                $items[] = [
                    'label' => 'Dung lượng lưu trữ',
                    'value' => $limitMb !== null ? "{$usedMb} MB / {$limitMb} MB" : "{$usedMb} MB (không giới hạn)",
                    'ok' => $limitMb === null || $usedMb < $limitMb,
                ];
            }
        } catch (\Throwable) {
            // Silent-fail co chu dich - xem docblock o tren.
        }

        return $items;
    }
}
