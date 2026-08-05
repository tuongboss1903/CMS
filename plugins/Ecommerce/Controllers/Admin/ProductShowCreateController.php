<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;

/** GET /admin/products/create */
final class ProductShowCreateController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        if (!$this->authorization->can('product.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $html = $this->view->render('admin.pages.ecommerce.products.create', [
            'errors' => [],
            'old' => [],
            'breadcrumb_items' => [['label' => 'Sản phẩm', 'url' => '/admin/products'], ['label' => 'Thêm mới']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
