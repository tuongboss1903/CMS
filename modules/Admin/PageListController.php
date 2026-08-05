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
        ]);

        return Response::html($html);
    }
}
