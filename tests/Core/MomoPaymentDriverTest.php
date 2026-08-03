<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Http\Request;
use PHPUnit\Framework\TestCase;
use Plugins\Ecommerce\Services\Payment\Drivers\MomoPaymentDriver;

/**
 * Phase 20 (CMS-057). Unit thuan - secret co dinh tu tests/Fixtures/config/payment.php
 * ('test-access-key'/'test-momo-secret') de HMAC deterministic. Thuat toan ky trong test PHAI khop
 * chinh xac MomoPaymentDriver::verifyWebhookSignature() - xem docblock class do.
 */
final class MomoPaymentDriverTest extends TestCase
{
    private function driver(): MomoPaymentDriver
    {
        return new MomoPaymentDriver(new Config(__DIR__ . '/../Fixtures/config'));
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        $data = [
            'partnerCode' => 'TEST_PARTNER',
            'orderId' => 'ORD-1',
            'requestId' => 'REQ-1',
            'amount' => '100000',
            'orderInfo' => 'Thanh toan don hang ORD-1',
            'orderType' => 'momo_wallet',
            'transId' => 'TXN-123',
            'resultCode' => 0,
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        $raw = "accessKey=test-access-key&amount={$data['amount']}&extraData={$data['extraData']}"
            . "&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}"
            . "&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}"
            . "&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";

        $data['signature'] = \hash_hmac('sha256', $raw, 'test-momo-secret');

        return $data;
    }

    public function testKeyReturnsMomo(): void
    {
        self::assertSame('momo', $this->driver()->key());
    }

    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $request = new Request('POST', '/payment/webhook/momo', 'example.com', [], $this->validPayload());

        self::assertTrue($this->driver()->verifyWebhookSignature($request));
    }

    public function testVerifyWebhookSignatureRejectsInvalidSignature(): void
    {
        $data = $this->validPayload();
        $data['signature'] = 'not-a-real-signature';
        $request = new Request('POST', '/payment/webhook/momo', 'example.com', [], $data);

        self::assertFalse($this->driver()->verifyWebhookSignature($request));
    }

    public function testVerifyWebhookSignatureRejectsMissingFields(): void
    {
        $request = new Request('POST', '/payment/webhook/momo', 'example.com', [], ['partnerCode' => 'TEST_PARTNER']);

        self::assertFalse($this->driver()->verifyWebhookSignature($request));
    }

    public function testParseWebhookPayloadMapsResultCodeZeroToCompleted(): void
    {
        $request = new Request('POST', '/payment/webhook/momo', 'example.com', [], $this->validPayload());
        $payload = $this->driver()->parseWebhookPayload($request);

        self::assertSame('completed', $payload->status);
        self::assertSame('ORD-1', $payload->orderNumber);
        self::assertSame('TXN-123', $payload->transactionRef);
        self::assertSame(100000.0, $payload->amount);
    }

    public function testParseWebhookPayloadMapsNonZeroResultCodeToFailed(): void
    {
        $data = $this->validPayload();
        $data['resultCode'] = 99;
        $request = new Request('POST', '/payment/webhook/momo', 'example.com', [], $data);

        self::assertSame('failed', $this->driver()->parseWebhookPayload($request)->status);
    }
}
