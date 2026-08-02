<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Settings\SiteSettingsManager;

/**
 * GET /search?q={query} - tim kiem Page cong khai (LIKE tren title/content, status=published,
 * scoped tenant hien tai, LIMIT 50 - khong pagination, MVP). Query rong -> danh sach rong, khong
 * loi. $query bind qua prepared statement (khong noi chuoi SQL) - chan SQL Injection.
 */
final class SearchController
{
    private const MAX_RESULTS = 50;

    public function __construct(
        private readonly Database $database,
        private readonly SiteSettingsManager $siteSettings,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $query = \trim((string) $request->query('q', ''));
        $tenantId = $this->tenantManager->id();

        $results = [];

        if ($query !== '') {
            $like = '%' . $query . '%';
            $results = $this->database->select(
                "SELECT id, title, slug FROM pages
                 WHERE tenant_id = ? AND status = ? AND deleted_at IS NULL AND (title LIKE ? OR content LIKE ?)
                 ORDER BY id DESC LIMIT " . self::MAX_RESULTS,
                [$tenantId, 'published', $like, $like]
            );
        }

        $html = $this->view->render('pages.search', [
            'title' => 'Tim kiem',
            'query' => $query,
            'results' => $results,
            'site_settings' => $this->siteSettings->get(),
        ]);

        return Response::html($html);
    }
}
