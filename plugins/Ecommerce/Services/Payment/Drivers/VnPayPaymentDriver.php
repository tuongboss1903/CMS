<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment\Drivers;

use Core\Config;
use Core\Http\Request;
use Plugins\Ecommerce\Services\Payment\PaymentDriverInterface;
use Plugins\Ecommerce\Services\Payment\PaymentResult;
use Plugins\Ecommerce\Services\Payment\PaymentWebhookPayload;

/**
 * Phase 20 (CMS-057). Zero-dependency: khong goi API ra ngoai (khac Momo) - VNPay dua tren dieu
 * huong trinh duyet thuan (charge() chi dung URL da ky, khong can ext-curl). Chu ky HMAC-SHA512
 * (khac Momo SHA256) qua hash_hmac() thuan, tren chuoi query da urlencode + sap xep theo tu dien -
 * dung tai lieu cong khai VNPay. Webhook/Return deu la GET (khac Momo POST JSON) nen doc tu
 * Request::all() (gom ca query) - khong can rawBody().
 */
final class VnPayPaymentDriver implements PaymentDriverInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function key(): string
    {
        return 'vnpay';
    }

    /** @param array<string, mixed> $order VNPay khong nhan IPN URL rieng theo tung giao dich (cau hinh san o Merchant Portal cua VNPay) - $webhookUrl khong dung trong driver nay, chi giu de khop interface. */
    public function charge(array $order, string $returnUrl, string $webhookUrl): PaymentResult
    {
        $cfg = $this->driverConfig();
        $orderNumber = (string) $order['order_number'];
        $amount = (string) ((int) \round(((float) $order['total_amount']) * 100));

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $cfg['tmn_code'],
            'vnp_Amount' => $amount,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $orderNumber,
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $orderNumber,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_CreateDate' => \date('YmdHis'),
        ];

        $signature = $this->sign($params, $cfg['hash_secret']);
        $redirectUrl = $cfg['endpoint'] . '?' . $this->buildQuery($params) . '&vnp_SecureHash=' . $signature;

        return new PaymentResult(status: 'pending', transactionRef: $orderNumber, redirectUrl: $redirectUrl);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $cfg = $this->driverConfig();
        $data = $request->all();

        $receivedHash = (string) ($data['vnp_SecureHash'] ?? '');

        if ($receivedHash === '' || !isset($data['vnp_TxnRef'], $data['vnp_Amount'], $data['vnp_ResponseCode'])) {
            return false;
        }

        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);

        $expected = $this->sign($data, $cfg['hash_secret']);

        return \hash_equals($expected, $receivedHash);
    }

    public function parseWebhookPayload(Request $request): PaymentWebhookPayload
    {
        $data = $request->all();

        return new PaymentWebhookPayload(
            orderNumber: (string) $data['vnp_TxnRef'],
            transactionRef: (string) ($data['vnp_TransactionNo'] ?? $data['vnp_TxnRef']),
            amount: ((float) $data['vnp_Amount']) / 100,
            status: ((string) $data['vnp_ResponseCode']) === '00' ? 'completed' : 'failed',
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $secret): string
    {
        \ksort($params);

        return \hash_hmac('sha512', $this->buildQuery($params), $secret);
    }

    /** @param array<string, mixed> $params */
    private function buildQuery(array $params): string
    {
        \ksort($params);

        return \http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{endpoint: string, tmn_code: string, hash_secret: string} */
    private function driverConfig(): array
    {
        /** @var array{endpoint?: string, tmn_code?: string, hash_secret?: string} $cfg */
        $cfg = (array) $this->config->get('payment.drivers.vnpay', []);

        return [
            'endpoint' => (string) ($cfg['endpoint'] ?? ''),
            'tmn_code' => (string) ($cfg['tmn_code'] ?? ''),
            'hash_secret' => (string) ($cfg['hash_secret'] ?? ''),
        ];
    }
}
