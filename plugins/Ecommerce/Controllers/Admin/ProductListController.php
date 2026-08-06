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
            'SELECT products.*, media.path AS image_path FROM products
             LEFT JOIN media ON media.id = products.image_id AND media.tenant_id = products.tenant_id
             WHERE products.tenant_id = ? AND products.deleted_at IS NULL ORDER BY products.created_at DESC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.ecommerce.products.list', [
            'products' => $products,
            'breadcrumb_items' => [['label' => 'Sản phẩm']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
