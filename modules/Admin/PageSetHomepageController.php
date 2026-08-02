<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** POST /admin/pages/{id}/homepage - copy logic tu Modules\Page\SetHomepageController. */
final class PageSetHomepageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
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
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->transaction(function (Database $db) use ($pageId, $siteId): void {
            $db->statement('UPDATE pages SET is_homepage = 0 WHERE tenant_id = ? AND is_homepage = 1', [$siteId]);
            $db->statement('UPDATE pages SET is_homepage = 1 WHERE id = ? AND tenant_id = ?', [$pageId, $siteId]);
        });

        return Response::redirect('/admin/pages');
    }
}
