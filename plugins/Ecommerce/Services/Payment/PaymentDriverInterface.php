<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment;

use Core\Http\Request;

/**
 * Phase 20 (CMS-057). Driver Pattern - tien le Core\Mail\MailerDriver/Core\Cache\CacheDriver (interface
 * toi gian, driver cu the tu chiu trach nhiem hoan toan voi API/giao thuc rieng cua tung cong thanh
 * toan). PaymentManager la facade DUY NHAT dieu phoi - tang tren KHONG duoc goi Driver truc tiep.
 */
interface PaymentDriverInterface
{
    /** Dinh danh khop voi orders.payment_method / payments.driver (vd 'cod', 'momo', 'vnpay'). */
    public function key(): string;

    /**
     * Khoi tao 1 luot thanh toan cho Order. COD: tra ve ngay status 'completed' (khong goi ra
     * ngoai, khong dung $returnUrl/$webhookUrl). Online (Momo/VNPay): tra ve status 'pending' +
     * redirectUrl de dua khach sang trang thanh toan cua cong.
     *
     * $returnUrl (browser, GET /payment/return/{driver}) va $webhookUrl (server-to-server, POST
     * /payment/webhook/{driver}) la 2 endpoint KHAC NHAU - khong dung chung 1 URL (browser return
     * khong xac thuc chu ky, Webhook bat buoc phai).
     *
     * @param array<string, mixed> $order Ban ghi "orders" that (id, order_number, total_amount, tenant_id...)
     */
    public function charge(array $order, string $returnUrl, string $webhookUrl): PaymentResult;

    /**
     * Xac thuc chu ky so cua Webhook - PHAI kiem tra TRUOC khi doc/tin bat ky truong nao khac trong
     * payload. false -> Controller tra 403 ngay, khong cham Database.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /** Chi goi SAU KHI verifyWebhookSignature() tra true. */
    public function parseWebhookPayload(Request $request): PaymentWebhookPayload;
}
