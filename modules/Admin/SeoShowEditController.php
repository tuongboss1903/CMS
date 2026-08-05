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

/**
 * GET /admin/seo/pages/{id} - form sua SEO Meta cho 1 Page. entity_type co dinh 'page' (Admin UI
 * chi phuc vu Page, khong nhan qua route param nhu JSON API goc). Chua co seo_meta khong phai loi
 * - form trong (dung quy uoc GET /seo/{entity_type}/{entity_id} goc: success=true, data=null).
 */
final class SeoShowEditController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
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

        $pageId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id, title, slug FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $meta = $this->database->selectOne(
            'SELECT * FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );

        $schemaDataText = '';

        if ($meta !== null && $meta['schema_data'] !== null) {
            $decoded = \json_decode((string) $meta['schema_data'], true);
            $schemaDataText = $decoded !== null ? \json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
        }

        $images = $this->database->select(
            "SELECT id, file_name FROM media WHERE tenant_id = ? AND mime_type LIKE 'image/%' ORDER BY file_name ASC",
            [$siteId]
        );

        $html = $this->view->render('admin.pages.seo.edit', [
            'breadcrumb_items' => [['label' => 'SEO', 'url' => '/admin/seo'], ['label' => 'Sửa SEO']],
            'page' => $page,
            'meta' => $meta,
            'schema_data_text' => $schemaDataText,
            'images' => $images,
            'errors' => [],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
