<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/payments - Phase 24 (Payment Management, CMS-081). Danh sach giao dich thanh toan
 * (bang "payments", CMS-057) noi voi orders de hien ma don/khach hang. Bat Throwable khi doc bang
 * "payments" - dung tien le OrderShowController::fetchPayments() (bang co the chua ton tai o
 * fixture test cu chua migrate).
 */
final class PaymentListController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
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

        if (!$this->authorization->can('payment.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $statusFilter = (string) $request->query('status', '');
        $driverFilter = (string) $request->query('driver', '');

        $html = $this->view->render('admin.pages.ecommerce.payments.list', [
            'payments' => $this->fetchPayments($statusFilter, $driverFilter),
            'status_filter' => $statusFilter,
            'driver_filter' => $driverFilter,
            'breadcrumb_items' => [['label' => 'Giao dịch thanh toán']],
        ]);

        return Response::html($html);
    }

    /** @return list<array<string, mixed>> */
    private function fetchPayments(string $statusFilter, string $driverFilter): array
    {
        $sql = 'SELECT payments.*, orders.order_number, orders.guest_name
                FROM payments
                INNER JOIN orders ON orders.id = payments.order_id
                WHERE payments.tenant_id = ?';
        $params = [$this->tenantManager->id()];

        if ($statusFilter !== '') {
            $sql .= ' AND payments.status = ?';
            $params[] = $statusFilter;
        }

        if ($driverFilter !== '') {
            $sql .= ' AND payments.driver = ?';
            $params[] = $driverFilter;
        }

        $sql .= ' ORDER BY payments.created_at DESC';

        try {
            return $this->database->select($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }
}
