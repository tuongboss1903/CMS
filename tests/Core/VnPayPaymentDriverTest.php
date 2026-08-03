<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Http\Request;
use PHPUnit\Framework\TestCase;
use Plugins\Ecommerce\Services\Payment\Drivers\VnPayPaymentDriver;

/**
 * Phase 20 (CMS-057). Unit thuan - secret co dinh tu tests/Fixtures/config/payment.php
 * ('test-vnpay-secret'). Thuat toan ky (ksort + http_build_query RFC3986 + HMAC-SHA512) trong test
 * PHAI khop chinh xac VnPayPaymentDriver::sign()/buildQuery() - xem docblock class do.
 */
final class VnPayPaymentDriverTest extends TestCase
{
    private function driver(): VnPayPaymentDriver
    {
        return new VnPayPaymentDriver(new Config(__DIR__ . '/../Fixtures/config'));
    }

    /** @return array<string, mixed> */
    private function validWebhookData(): array
    {
        $data = [
            'vnp_Amount' => '10000000',
            'vnp_TxnRef' => 'ORD-1',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => 'VNP-123',
            'vnp_TmnCode' => 'TEST_TMN',
        ];

        \ksort($data);
        $data['vnp_SecureHash'] = \hash_hmac('sha512', \http_build_query($data, '', '&', PHP_QUERY_RFC3986), 'test-vnpay-secret');

        return $data;
    }

    public function testKeyReturnsVnpay(): void
    {
        self::assertSame('vnpay', $this->driver()->key());
    }

    public function testChargeBuildsSignedRedirectUrlToConfiguredEndpoint(): void
    {
        $order = ['id' => 1, 'order_number' => 'ORD-1', 'total_amount' => 100.0];

        $result = $this->driver()->charge($order, 'https://tenant-a.example.com/payment/return/vnpay', 'https://tenant-a.example.com/payment/webhook/vnpay');

        self::assertSame('pending', $result->status);
        self::assertSame('ORD-1', $result->transactionRef);
        self::assertNotNull($result->redirectUrl);
        self::assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $result->redirectUrl);
        self::assertStringContainsString('vnp_SecureHash=', $result->redirectUrl);
        self::assertStringContainsString('vnp_TxnRef=ORD-1', $result->redirectUrl);
    }

    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $request = new Request('POST', '/payment/webhook/vnpay', 'example.com', $this->validWebhookData());

        self::assertTrue($this->driver()->verifyWebhookSignature($request));
    }

    public function testVerifyWebhookSignatureRejectsInvalidSignature(): void
    {
        $data = $this->validWebhookData();
        $data['vnp_SecureHash'] = 'not-a-real-hash';
        $request = new Request('POST', '/payment/webhook/vnpay', 'example.com', $data);

        self::assertFalse($this->driver()->verifyWebhookSignature($request));
    }

    public function testVerifyWebhookSignatureRejectsMissingRequiredFields(): void
    {
        $request = new Request('POST', '/payment/webhook/vnpay', 'example.com', ['vnp_SecureHash' => 'x']);

        self::assertFalse($this->driver()->verifyWebhookSignature($request));
    }

    public function testParseWebhookPayloadMapsResponseCode00ToCompleted(): void
    {
        $request = new Request('POST', '/payment/webhook/vnpay', 'example.com', $this->validWebhookData());
        $payload = $this->driver()->parseWebhookPayload($request);

        self::assertSame('completed', $payload->status);
        self::assertSame('ORD-1', $payload->orderNumber);
        self::assertSame('VNP-123', $payload->transactionRef);
        self::assertSame(100000.0, $payload->amount);
    }

    public function testParseWebhookPayloadMapsOtherResponseCodeToFailed(): void
    {
        $data = $this->validWebhookData();
        $data['vnp_ResponseCode'] = '24';
        $request = new Request('POST', '/payment/webhook/vnpay', 'example.com', $data);

        self::assertSame('failed', $this->driver()->parseWebhookPayload($request)->status);
    }
}
