<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment\Drivers;

use Core\Http\Request;
use Plugins\Ecommerce\Services\Payment\PaymentDriverInterface;
use Plugins\Ecommerce\Services\Payment\PaymentResult;
use Plugins\Ecommerce\Services\Payment\PaymentWebhookPayload;

/** Phase 20 (CMS-057). COD khong co Webhook that (khong cong nao goi lai) - verifyWebhookSignature()/parseWebhookPayload() khong bao gio duoc PaymentWebhookController goi toi vi route Webhook luon co {driver} khac 'cod' tren thuc te, giu de tron ven interface. */
final class CodPaymentDriver implements PaymentDriverInterface
{
    public function key(): string
    {
        return 'cod';
    }

    /** @param array<string, mixed> $order */
    public function charge(array $order, string $returnUrl, string $webhookUrl): PaymentResult
    {
        return new PaymentResult(status: 'completed', transactionRef: null, redirectUrl: null);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return false;
    }

    public function parseWebhookPayload(Request $request): PaymentWebhookPayload
    {
        throw new \LogicException('CodPaymentDriver khong ho tro Webhook.');
    }
}
