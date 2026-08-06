<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Plugins\Ecommerce\Services\Payment\PaymentGatewaySettings;

/**
 * POST /admin/payment-settings/{key}/toggle - Phase 24 (Payment Management, CMS-081). Tai dung
 * dung UI Switch (form+data-confirm+CSRF) da chuan hoa tu CMS-073, xem
 * Modules\Admin\PluginToggleController - khong nhan key tuy y tu client, chi cho phep 1 trong 3
 * driver that su ton tai (PaymentGatewaySettings::DRIVER_KEYS).
 */
final class PaymentSettingToggleController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly PaymentGatewaySettings $paymentGatewaySettings,
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

        $driverKey = (string) $request->routeParam('key');

        if (!\in_array($driverKey, PaymentGatewaySettings::DRIVER_KEYS, true)) {
            return Response::html('404 Not Found', 404);
        }

        $this->paymentGatewaySettings->setEnabled($driverKey, !$this->paymentGatewaySettings->isEnabled($driverKey));

        return Response::redirect('/admin/payment-settings');
    }
}
