<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /payment/return/{driver} - Phase 20 (CMS-057). Man hinh phan hoi NGUOI DUNG sau khi quay lai
 * tu cong thanh toan - CHI hien thi trang thai, KHONG ghi Database (khac Webhook/IPN, von la nguon
 * that duy nhat cap nhat orders/payments - trinh duyet nguoi dung co the dong tab/mat mang truoc
 * khi redirect ve toi day, khong dang tin cay bang server-to-server Webhook).
 */
final class PaymentReturnController
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $orderNumber = (string) ($request->query('vnp_TxnRef') ?? $request->query('orderId') ?? '');

        $order = $orderNumber !== ''
            ? $this->database->selectOne('SELECT order_number, status FROM orders WHERE tenant_id = ? AND order_number = ?', [$this->tenantManager->id(), $orderNumber])
            : null;

        $html = $this->view->render('checkout.payment_return', [
            'order' => $order,
            'order_number' => $orderNumber,
        ]);

        return Response::html($html);
    }
}
