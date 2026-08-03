<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Config;
use Core\Csrf;
use Core\Hook;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\View;
use Modules\Admin\NotificationService;
use Plugins\Ecommerce\Actions\EcommerceValidationException;
use Plugins\Ecommerce\Actions\PlaceOrderAction;
use Plugins\Ecommerce\Services\CartService;
use Plugins\Ecommerce\Services\Payment\PaymentManager;

/**
 * POST /checkout - Phase 20 (CMS-057): sau khi PlaceOrderAction tao Order thanh cong, ban hook
 * "order.created" (Plugins\Ecommerce\Hooks.php dang ky listener goi Mailer/NotificationService) -
 * Controller (khong phai Action) chiu trach nhiem ban hook vi day la noi tu nhien co san Container/
 * cac Service can thiet, giu PlaceOrderAction (Action Class Pattern) thuan business logic.
 *
 * payment_method khac 'cod' (momo/vnpay) -> goi PaymentManager::driver()->charge() va REDIRECT
 * khach sang trang thanh toan cua cong (redirectUrl) thay vi hien thi checkout.success ngay - don
 * hang van o "pending", chi coi la hoan tat sau khi PaymentWebhookController nhan Webhook thanh cong.
 */
final class CheckoutPlaceOrderController
{
    public function __construct(
        private readonly PlaceOrderAction $action,
        private readonly CartService $cartService,
        private readonly Config $config,
        private readonly Csrf $csrf,
        private readonly Hook $hook,
        private readonly Mailer $mailer,
        private readonly NotificationService $notificationService,
        private readonly PaymentManager $paymentManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $data = $request->all();

        try {
            $order = $this->action->execute($data);
        } catch (EcommerceValidationException $exception) {
            $html = $this->view->render('checkout.index', [
                'items' => $this->cartService->items(),
                'total' => $this->cartService->total(),
                'errors' => $exception->errors(),
                'old' => $data,
                'csrf_token' => $this->csrf->token(),
            ]);

            return Response::html($html);
        }

        $this->hook->do('order.created', $order, $this->mailer, $this->notificationService);

        if ($order['payment_method'] !== 'cod') {
            $redirect = $this->initiateOnlinePayment($order);

            if ($redirect !== null) {
                return Response::redirect($redirect);
            }
        }

        return Response::html($this->view->render('checkout.success', ['order_number' => $order['order_number']]));
    }

    /** @param array{id: int, order_number: string, total_amount: float, payment_method: string} $order */
    private function initiateOnlinePayment(array $order): ?string
    {
        $baseUrl = \rtrim((string) $this->config->get('payment.return_base_url', ''), '/');
        $returnUrl = $baseUrl . '/payment/return/' . $order['payment_method'];
        $webhookUrl = $baseUrl . '/payment/webhook/' . $order['payment_method'];

        $result = $this->paymentManager->driver($order['payment_method'])->charge($order, $returnUrl, $webhookUrl);

        return $result->redirectUrl;
    }
}
