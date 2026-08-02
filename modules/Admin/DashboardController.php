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
            'top_pages' => $analytics['top_pages'],
            'daily_views' => $analytics['daily_views'],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }

    /**
     * Bang analytics_views (CMS-049) chua chac ton tai o moi fixture test cu (vd
     * AdminUiFoundationTest.php) - bat Throwable, fallback rong, cung nguyen tac da ap dung cho
     * bang media o PageShowCreateController/PageShowEditController (Phase 11).
     *
     * @return array{total_views: int, unique_visitors: int, top_pages: list<array{path: string, views: int}>, daily_views: list<array{date: string, views: int}>}
     */
    private function fetchAnalyticsSummary(): array
    {
        try {
            return [
                'total_views' => $this->analyticsService->totalViews('7d'),
                'unique_visitors' => $this->analyticsService->uniqueVisitors('7d'),
                'top_pages' => $this->analyticsService->topPages('7d', 5),
                'daily_views' => $this->analyticsService->dailyViews(7),
            ];
        } catch (\Throwable) {
            $emptyDays = [];

            for ($i = 6; $i >= 0; $i--) {
                $emptyDays[] = ['date' => \date('Y-m-d', \time() - $i * 86400), 'views' => 0];
            }

            return [
                'total_views' => 0,
                'unique_visitors' => 0,
                'top_pages' => [],
                'daily_views' => $emptyDays,
            ];
        }
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
}
