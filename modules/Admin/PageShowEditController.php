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

/** GET /admin/pages/{id}/edit - 404 cross-tenant/da xoa mem (khong 403). */
final class PageShowEditController
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
        if (!$this->authorization->can('page.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id, parent_id, title, slug, content, template FROM pages
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL AND id != ? ORDER BY title ASC',
            [$siteId, $pageId]
        );

        try {
            $images = $this->database->select(
                "SELECT id, file_name FROM media WHERE tenant_id = ? AND mime_type LIKE 'image/%' ORDER BY file_name ASC",
                [$siteId]
            );
        } catch (\Throwable) {
            $images = [];
        }

        $content = $page['content'] !== null ? \json_decode((string) $page['content'], true) : null;
        $editorMode = \is_array($content) && isset($content['blocks']) ? 'block' : 'quill';

        $html = $this->view->render('admin.pages.pages.edit', [
            'page' => $page,
            'parents' => $parents,
            'images' => $images,
            'editor_mode' => $editorMode,
            'errors' => [],
            'old' => [
                'title' => $page['title'],
                'slug' => $page['slug'],
                'content_html' => (string) ($content['html'] ?? $content['text'] ?? ''),
                'template' => (string) ($page['template'] ?? ''),
                'parent_id' => (string) ($page['parent_id'] ?? ''),
                'blocks' => $content['blocks'] ?? [],
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
