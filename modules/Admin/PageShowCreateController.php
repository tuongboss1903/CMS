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

/** GET /admin/pages/create - render form tao Page + danh sach Page hien co lam Parent Page. */
final class PageShowCreateController
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
        if (!$this->authorization->can('page.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY title ASC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.pages.create', [
            'parents' => $parents,
            'errors' => [],
            'old' => ['title' => '', 'slug' => '', 'content_html' => '', 'template' => '', 'parent_id' => ''],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
