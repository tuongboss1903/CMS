<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;
use Plugins\Ecommerce\Services\Payment\PaymentGatewaySettings;

/** GET /admin/payment-settings - Phase 24 (Payment Management, CMS-081). Bat/tat tung cong thanh toan cho tenant hien tai. */
final class PaymentSettingListController
{
    private const DRIVER_LABELS = [
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'momo' => 'Ví MoMo',
        'vnpay' => 'VNPay',
    ];

    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly PaymentGatewaySettings $paymentGatewaySettings,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        if (!$this->authorization->can('payment.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $enabledState = $this->paymentGatewaySettings->all();
        $drivers = [];

        foreach (self::DRIVER_LABELS as $key => $label) {
            $drivers[] = ['key' => $key, 'label' => $label, 'enabled' => $enabledState[$key]];
        }

        $html = $this->view->render('admin.pages.ecommerce.payment_settings.edit', [
            'drivers' => $drivers,
            'breadcrumb_items' => [['label' => 'Quản lý thanh toán']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
