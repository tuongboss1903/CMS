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

/** GET /admin/orders/{id} */
final class OrderShowController
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

        $orderId = (int) $request->routeParam('id');
        $tenantId = $this->tenantManager->id();

        $order = $this->database->selectOne('SELECT * FROM orders WHERE id = ? AND tenant_id = ?', [$orderId, $tenantId]);

        if ($order === null) {
            return Response::html('404 Not Found', 404);
        }

        $items = $this->database->select('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);

        $html = $this->view->render('admin.pages.ecommerce.orders.show', [
            'order' => $order,
            'items' => $items,
            'payments' => $this->fetchPayments($orderId),
            'breadcrumb_items' => [['label' => 'Don hang', 'url' => '/admin/orders'], ['label' => (string) $order['order_number']]],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }

    /**
     * Bang payments (CMS-057, Phase 20) chua chac ton tai o fixture test cu cua Phase 19
     * (EcommerceOrderManagementTest.php) - bat Throwable, fallback rong, dung nguyen tac da ap dung
     * cho audit_logs/analytics_views o DashboardController.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchPayments(int $orderId): array
    {
        try {
            return $this->database->select('SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC', [$orderId]);
        } catch (\Throwable) {
            return [];
        }
    }
}
