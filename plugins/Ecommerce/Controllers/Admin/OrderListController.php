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

/** GET /admin/orders */
final class OrderListController
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

        if (!$this->authorization->can('order.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $orders = $this->database->select(
            'SELECT * FROM orders WHERE tenant_id = ? ORDER BY created_at DESC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.ecommerce.orders.list', [
            'orders' => $orders,
            'breadcrumb_items' => [['label' => 'Đơn hàng']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
