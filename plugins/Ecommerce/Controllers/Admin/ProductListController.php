<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/** GET /admin/products - Auth::check() tu xu ly redirect HTML (dung tien le PageListController, khong AuthMiddleware). */
final class ProductListController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
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

        if (!$this->authorization->can('product.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $products = $this->database->select(
            'SELECT * FROM products WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.products.list', [
            'products' => $products,
            'breadcrumb_items' => [['label' => 'San pham']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
