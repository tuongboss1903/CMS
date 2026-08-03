<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services\Payment;

use InvalidArgumentException;
use Plugins\Ecommerce\Services\Payment\Drivers\CodPaymentDriver;
use Plugins\Ecommerce\Services\Payment\Drivers\MomoPaymentDriver;
use Plugins\Ecommerce\Services\Payment\Drivers\VnPayPaymentDriver;

/**
 * Phase 20 (CMS-057). Facade DUY NHAT dieu phoi Payment Driver - tang tren (Controller/Action)
 * KHONG duoc goi Driver truc tiep, dung mo hinh Core\Mail\Mailer/Core\Cache. 3 Driver deu class cu
 * the (khong interface trong constructor) nen Container tu auto-wire duoc, khong can dang ky
 * tuong minh.
 */
final class PaymentManager
{
    /** @var array<string, PaymentDriverInterface> */
    private readonly array $drivers;

    public function __construct(CodPaymentDriver $cod, MomoPaymentDriver $momo, VnPayPaymentDriver $vnpay)
    {
        $this->drivers = [
            $cod->key() => $cod,
            $momo->key() => $momo,
            $vnpay->key() => $vnpay,
        ];
    }

    public function driver(string $key): PaymentDriverInterface
    {
        if (!isset($this->drivers[$key])) {
            throw new InvalidArgumentException("Payment driver khong ton tai: '{$key}'.");
        }

        return $this->drivers[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }
}
