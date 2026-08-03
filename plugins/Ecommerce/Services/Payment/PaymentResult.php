<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment;

/** Phase 20 (CMS-057). Ket qua tao 1 luot thanh toan (PaymentDriverInterface::charge()) - khong phai ket qua Webhook (xem PaymentWebhookPayload). */
final class PaymentResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionRef,
        public readonly ?string $redirectUrl,
    ) {
    }
}
