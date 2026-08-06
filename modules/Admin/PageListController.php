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
use Modules\Analytics\AnalyticsService;

/** GET /admin/pages - copy logic tu Modules\Page\ListPagesController. */
final class PageListController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $search = \trim((string) ($request->query('q') ?? ''));
        $status = \trim((string) ($request->query('status') ?? ''));

        $conditions = ['tenant_id = ?', 'deleted_at IS NULL'];
        $bindings = [$this->tenantManager->id()];

        if ($search !== '') {
            $conditions[] = '(title LIKE ? OR slug LIKE ?)';
            $bindings[] = '%' . $search . '%';
            $bindings[] = '%' . $search . '%';
        }

        if ($status !== '') {
            $conditions[] = 'status = ?';
            $bindings[] = $status;
        }

        $where = \implode(' AND ', $conditions);

        $pages = $this->database->select(
            "SELECT id, parent_id, title, slug, status, is_homepage, published_at
             FROM pages WHERE {$where} ORDER BY id DESC",
            $bindings
        );

        $html = $this->view->render('admin.pages.pages.list', [
            'breadcrumb_items' => [['label' => 'Trang nội dung']],
            'pages' => $pages,
            'csrf_token' => $this->csrf->token(),
            'filters' => ['q' => $search, 'status' => $status],
            'stats' => $this->fetchStats(),
        ]);

        return Response::html($html);
    }

    /**
     * Stat card row dau trang (Design Audit Phase 24) - "tang mat do tinh nang" theo yeu cau, lap
     * day khoang trong dau danh sach. Luot xem hom nay qua AnalyticsService('24h') - bang
     * analytics_views co the chua ton tai o fixture test cu (cung ly do voi DashboardController::
     * fetchAnalyticsSummary()), bat Throwable rieng cho phan nay de KHONG lam vo ca trang neu loi.
     *
     * @return array{total: int, published: int, draft: int, views_today: int}
     */
    private function fetchStats(): array
    {
        $tenantId = $this->tenantManager->id();

        $counts = $this->database->selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft
             FROM pages WHERE tenant_id = ? AND deleted_at IS NULL",
            [$tenantId]
        );

        try {
            $viewsToday = $this->analyticsService->totalViews('24h');
        } catch (\Throwable) {
            $viewsToday = 0;
        }

        return [
            'total' => (int) ($counts['total'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'views_today' => $viewsToday,
        ];
    }
}
