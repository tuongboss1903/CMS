<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment;

/**
 * Phase 20 (CMS-057). Du lieu da chuan hoa tu 1 lan goi Webhook - PaymentDriverInterface::parseWebhookPayload()
 * chiu trach nhiem dich tu format rieng cua tung cong sang shape chung nay.
 *
 * $orderNumber (khong phai $orderId) - cong thanh toan tra ve dinh danh don hang qua "orderId" cua
 * HO (thuc chat la orders.order_number phia CMS, string tu sinh vd "20260817-ABCDEF"), khong phai
 * orders.id noi bo. PaymentWebhookController tu tra cuu orders.id qua order_number nay.
 */
final class PaymentWebhookPayload
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $transactionRef,
        public readonly float $amount,
        public readonly string $status,
    ) {
    }
}
