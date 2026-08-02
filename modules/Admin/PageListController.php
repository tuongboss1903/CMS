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

/** GET /admin/pages - copy logic tu Modules\Page\ListPagesController. */
final class PageListController
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
        if (!$this->authorization->can('page.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $pages = $this->database->select(
            'SELECT id, parent_id, title, slug, status, is_homepage, published_at
             FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY id DESC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.pages.list', [
            'pages' => $pages,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
