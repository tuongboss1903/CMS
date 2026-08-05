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

/** GET /admin/products/{id}/edit */
final class ProductShowEditController
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

        if (!$this->authorization->can('product.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $productId = (int) $request->routeParam('id');

        $product = $this->database->selectOne(
            'SELECT * FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$productId, $this->tenantManager->id()]
        );

        if ($product === null) {
            return Response::html('404 Not Found', 404);
        }

        $html = $this->view->render('admin.pages.ecommerce.products.edit', [
            'product' => $product,
            'errors' => [],
            'old' => $product,
            'breadcrumb_items' => [['label' => 'Sản phẩm', 'url' => '/admin/products'], ['label' => 'Sửa']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
