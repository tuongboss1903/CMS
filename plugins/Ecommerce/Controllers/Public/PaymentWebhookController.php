<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\TenantManager;
use Plugins\Ecommerce\Actions\UpdateOrderStatusAction;
use Plugins\Ecommerce\Services\Payment\PaymentManager;
use Plugins\Ecommerce\Services\Payment\PaymentWebhookPayload;
use Throwable;

/**
 * POST /payment/webhook/{driver} - Phase 20 (CMS-057). NAM NGOAI CsrfMiddleware (xem routes.php) -
 * request den tu SERVER cong thanh toan, khong co browser session/form nen khong the co CSRF
 * token; bao ve bang xac thuc chu ky so thay the (verifyWebhookSignature()).
 *
 * Tenant duoc TenantResolverMiddleware resolve BINH THUONG qua domain cua chinh URL Webhook nay
 * (URL notify gui cho cong thanh toan luon la domain cua DUNG tenant khoi tao thanh toan, xem
 * PaymentReturnController/CheckoutPlaceOrderController) - KHONG can tu suy tenant tu payload nhu
 * ban Architecture Analysis truoc do de xuat (da sua truoc khi code, xem ghi chu trong
 * core/Application.php ve loi Plugin Route thieu TenantResolverMiddleware o Phase 19).
 *
 * Idempotent: kiem tra payments (tenant_id, driver, transaction_ref) da ton tai VA da o trang thai
 * cuoi (completed/failed) truoc khi ghi - cong thanh toan co the goi lai Webhook nhieu lan cho CUNG
 * 1 giao dich, khong duoc tao/cap nhat trung.
 */
final class PaymentWebhookController
{
    public function __construct(
        private readonly Database $database,
        private readonly Hook $hook,
        private readonly Mailer $mailer,
        private readonly PaymentManager $paymentManager,
        private readonly TenantManager $tenantManager,
        private readonly UpdateOrderStatusAction $updateOrderStatusAction,
    ) {
    }

    public function handle(Request $request): Response
    {
        $driverKey = (string) $request->routeParam('driver');

        if (!$this->paymentManager->has($driverKey)) {
            return Response::html('404 Not Found', 404);
        }

        $driver = $this->paymentManager->driver($driverKey);

        if (!$driver->verifyWebhookSignature($request)) {
            return Response::json(['success' => false, 'data' => null, 'message' => 'Invalid signature', 'errors' => []], 403);
        }

        $payload = $driver->parseWebhookPayload($request);
        $tenantId = $this->tenantManager->id();

        $order = $this->database->selectOne(
            'SELECT id, order_number, guest_email, status FROM orders WHERE tenant_id = ? AND order_number = ?',
            [$tenantId, $payload->orderNumber]
        );

        if ($order === null) {
            return Response::html('404 Not Found', 404);
        }

        $existing = $this->database->selectOne(
            'SELECT id, status FROM payments WHERE tenant_id = ? AND driver = ? AND transaction_ref = ?',
            [$tenantId, $driverKey, $payload->transactionRef]
        );

        if ($existing !== null && \in_array($existing['status'], ['completed', 'failed'], true)) {
            return Response::json(['success' => true, 'data' => null, 'message' => 'Already processed', 'errors' => []]);
        }

        $this->recordPayment($tenantId, (int) $order['id'], $driverKey, $payload, $request, $existing);

        if ($payload->status === 'completed' && (string) $order['status'] === 'pending') {
            $this->advanceOrderToProcessing((int) $order['id']);
        }

        if ($payload->status === 'completed') {
            $this->hook->do('order.payment_completed', $order, $this->mailer);
        }

        return Response::json(['success' => true, 'data' => null, 'message' => '', 'errors' => []]);
    }

    /** @param array{id: int, status: string}|null $existing */
    private function recordPayment(
        int|string|null $tenantId,
        int $orderId,
        string $driverKey,
        PaymentWebhookPayload $payload,
        Request $request,
        ?array $existing,
    ): void {
        $rawPayload = $request->rawBody() !== '' ? $request->rawBody() : \json_encode($request->all(), JSON_UNESCAPED_UNICODE);

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO payments (tenant_id, order_id, driver, status, amount, transaction_ref, raw_payload)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$tenantId, $orderId, $driverKey, $payload->status, $payload->amount, $payload->transactionRef, $rawPayload]
            );

            return;
        }

        $this->database->update(
            'UPDATE payments SET status = ?, raw_payload = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$payload->status, $rawPayload, $existing['id']]
        );
    }

    /**
     * Thanh toan thanh cong -> chuyen Order tu "pending" sang "processing" (bat dau xu ly don), KHONG
     * nhay thang len "completed" (that toan != da giao hang xong - 2 khai niem khac nhau). Dung lai
     * UpdateOrderStatusAction thay vi tu UPDATE truc tiep - tranh trung logic validate transition.
     */
    private function advanceOrderToProcessing(int $orderId): void
    {
        try {
            $this->updateOrderStatusAction->execute($orderId, 'processing');
        } catch (Throwable) {
            // Silent - Order co the da khong con o "pending" giua luc xu ly (race condition hiem),
            // khong lam gian doan phan hoi 200 cho cong thanh toan (tranh bi retry vo ich).
        }
    }
}
