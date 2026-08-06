<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment;

use Modules\Settings\SettingManager;

/**
 * Phase 24 (Payment Management, CMS-081). Bat/tat tung cong thanh toan theo tenant, tai dung nguyen
 * bang "settings" key-value da co (Modules\Settings\SettingManager, CMS-054) - KHONG tao bang moi.
 * Key dang "payment.enabled.{driver}" (vd payment.enabled.momo), gia tri '1'/'0', setting_group
 * 'payment'. Mac dinh TRUE khi chua co row (dung hanh vi hien tai truoc CMS-081: ca 3 driver deu
 * hien o Checkout, khong Silent-breaking don da tich hop san).
 *
 * bat Throwable khi doc - dung tien le OrderShowController::fetchPayments() (bang settings chua
 * chac ton tai o fixture test cu chua migrate bang nay).
 */
final class PaymentGatewaySettings
{
    public const DRIVER_KEYS = ['cod', 'momo', 'vnpay'];

    public function __construct(private readonly SettingManager $settingManager)
    {
    }

    public function isEnabled(string $driverKey): bool
    {
        try {
            return $this->settingManager->get('payment.enabled.' . $driverKey, '1') === '1';
        } catch (\Throwable) {
            return true;
        }
    }

    /** @return array<string, bool> driver key => enabled */
    public function all(): array
    {
        $result = [];

        foreach (self::DRIVER_KEYS as $key) {
            $result[$key] = $this->isEnabled($key);
        }

        return $result;
    }

    /** @return list<string> */
    public function enabledDrivers(): array
    {
        return \array_keys(\array_filter($this->all()));
    }

    public function setEnabled(string $driverKey, bool $enabled): void
    {
        $this->settingManager->set('payment.enabled.' . $driverKey, $enabled ? '1' : '0', 'payment');
    }
}
