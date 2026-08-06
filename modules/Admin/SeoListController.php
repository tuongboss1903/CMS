<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/seo - liet ke Page cua tenant + trang thai da/chua cau hinh SEO Meta. LEFT JOIN
 * seo_meta theo entity_type='page' AND entity_id=pages.id - khong N+1.
 */
final class SeoListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('seo.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();

        $pages = $this->database->select(
            'SELECT pages.id, pages.title, pages.slug,
                    CASE WHEN seo_meta.id IS NULL THEN 0 ELSE 1 END AS has_seo_meta
             FROM pages
             LEFT JOIN seo_meta ON seo_meta.entity_type = \'page\' AND seo_meta.entity_id = pages.id AND seo_meta.tenant_id = pages.tenant_id
             WHERE pages.tenant_id = ? AND pages.deleted_at IS NULL
             ORDER BY pages.id DESC',
            [$siteId]
        );

        $configuredCount = \count(\array_filter($pages, static fn (array $page): bool => (int) $page['has_seo_meta'] === 1));

        $html = $this->view->render('admin.pages.seo.list', [
            'breadcrumb_items' => [['label' => 'SEO']],
            'pages' => $pages,
            'stats' => [
                'total' => \count($pages),
                'configured' => $configuredCount,
                'missing' => \count($pages) - $configuredCount,
            ],
        ]);

        return Response::html($html);
    }
}
