<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment\Drivers;

use Core\Config;
use Core\Http\Request;
use Plugins\Ecommerce\Services\Payment\PaymentDriverInterface;
use Plugins\Ecommerce\Services\Payment\PaymentResult;
use Plugins\Ecommerce\Services\Payment\PaymentWebhookPayload;

/**
 * Phase 20 (CMS-057). Zero-dependency: goi API Momo qua ext-curl (extension chuan PHP, khong phai
 * thu vien Composer - dung tien le ext-openssl cua SettingManager Phase 17), ky HMAC-SHA256 qua
 * hash_hmac() thuan. Thuat toan chu ky theo dung tai lieu cong khai Momo (chuoi canonical ghep tu
 * CAC TRUONG DA DECODE theo dung thu tu quy dinh - khong phai byte tho cua request, nen KHONG can
 * Request::rawBody() cho driver nay).
 */
final class MomoPaymentDriver implements PaymentDriverInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function key(): string
    {
        return 'momo';
    }

    /** @param array<string, mixed> $order */
    public function charge(array $order, string $returnUrl, string $webhookUrl): PaymentResult
    {
        $cfg = $this->driverConfig();
        $requestId = $order['id'] . '-' . \bin2hex(\random_bytes(4));
        $orderNumber = (string) $order['order_number'];
        $amount = (string) (int) \round(((float) $order['total_amount']) * 1);
        $orderInfo = 'Thanh toan don hang ' . $orderNumber;
        $extraData = '';
        $requestType = 'captureWallet';

        $rawSignature = "accessKey={$cfg['access_key']}&amount={$amount}&extraData={$extraData}&ipnUrl={$webhookUrl}"
            . "&orderId={$orderNumber}&orderInfo={$orderInfo}&partnerCode={$cfg['partner_code']}&redirectUrl={$returnUrl}"
            . "&requestId={$requestId}&requestType={$requestType}";
        $signature = \hash_hmac('sha256', $rawSignature, $cfg['secret_key']);

        $response = $this->postJson((string) $cfg['endpoint'], [
            'partnerCode' => $cfg['partner_code'],
            'accessKey' => $cfg['access_key'],
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderNumber,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $returnUrl,
            'ipnUrl' => $webhookUrl,
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
            'lang' => 'vi',
        ]);

        if ($response === null || !isset($response['payUrl'])) {
            return new PaymentResult(status: 'failed', transactionRef: $orderNumber, redirectUrl: null);
        }

        return new PaymentResult(status: 'pending', transactionRef: $orderNumber, redirectUrl: (string) $response['payUrl']);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $cfg = $this->driverConfig();
        $data = $request->all();

        $required = ['partnerCode', 'orderId', 'requestId', 'amount', 'orderInfo', 'orderType', 'transId', 'resultCode', 'message', 'payType', 'responseTime', 'extraData', 'signature'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        $rawSignature = "accessKey={$cfg['access_key']}&amount={$data['amount']}&extraData={$data['extraData']}"
            . "&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}"
            . "&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}"
            . "&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";
        $expected = \hash_hmac('sha256', $rawSignature, $cfg['secret_key']);

        return \hash_equals($expected, (string) $data['signature']);
    }

    public function parseWebhookPayload(Request $request): PaymentWebhookPayload
    {
        $data = $request->all();

        return new PaymentWebhookPayload(
            orderNumber: (string) $data['orderId'],
            transactionRef: (string) $data['transId'],
            amount: (float) $data['amount'],
            status: ((int) $data['resultCode']) === 0 ? 'completed' : 'failed',
        );
    }

    /** @return array{endpoint: string, partner_code: string, access_key: string, secret_key: string} */
    private function driverConfig(): array
    {
        /** @var array{endpoint?: string, partner_code?: string, access_key?: string, secret_key?: string} $cfg */
        $cfg = (array) $this->config->get('payment.drivers.momo', []);

        return [
            'endpoint' => (string) ($cfg['endpoint'] ?? ''),
            'partner_code' => (string) ($cfg['partner_code'] ?? ''),
            'access_key' => (string) ($cfg['access_key'] ?? ''),
            'secret_key' => (string) ($cfg['secret_key'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function postJson(string $url, array $payload): ?array
    {
        if (!\function_exists('curl_init')) {
            return null;
        }

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = \curl_exec($ch);
        \curl_close($ch);

        if (!\is_string($response) || $response === '') {
            return null;
        }

        $decoded = \json_decode($response, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
