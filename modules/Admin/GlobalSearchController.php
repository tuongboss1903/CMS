<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/search - Global Search o Topbar (Design Audit Phase 24). Tim theo Page (tieu de/
 * slug) va Nguoi dung (ten/email) trong pham vi tenant hien tai - 2 nguon du lieu don gian nhat
 * de "that", khong bia UI khong lam gi. Auth::check() (khong AuthMiddleware, giong Dashboard) -
 * KHONG gate theo permission tung loai (page.view/user.view) de giu don gian, ket qua chi la link
 * dieu huong toi man hinh that su co gate permission rieng.
 */
final class GlobalSearchController
{
    private const LIMIT_PER_TYPE = 8;

    public function __construct(
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $query = \trim((string) ($request->query('q') ?? ''));
        $siteId = $this->tenantManager->id();

        $pages = [];
        $users = [];

        if ($query !== '') {
            $like = '%' . $query . '%';

            $pages = $this->database->select(
                'SELECT id, title, slug FROM pages
                 WHERE tenant_id = ? AND deleted_at IS NULL AND (title LIKE ? OR slug LIKE ?)
                 ORDER BY id DESC LIMIT ' . self::LIMIT_PER_TYPE,
                [$siteId, $like, $like]
            );

            $users = $this->database->select(
                'SELECT users.id, users.name, users.email
                 FROM users
                 INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
                 WHERE user_site_roles.site_id = ? AND (users.name LIKE ? OR users.email LIKE ?)
                 ORDER BY users.id DESC LIMIT ' . self::LIMIT_PER_TYPE,
                [$siteId, $like, $like]
            );
        }

        $html = $this->view->render('admin.pages.search.results', [
            'breadcrumb_items' => [['label' => 'Tìm kiếm']],
            'query' => $query,
            'pages' => $pages,
            'users' => $users,
        ]);

        return Response::html($html);
    }
}
