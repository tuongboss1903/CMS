<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/pages/{id}/delete - khong DELETE method. Copy logic tu
 * Modules\Page\DeletePageController (soft delete, khong xoa that).
 */
final class PageDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $this->tenantManager->id()]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->statement('UPDATE pages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$pageId]);

        return Response::redirect('/admin/pages');
    }
}
