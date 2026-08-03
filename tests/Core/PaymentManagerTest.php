<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugins\Ecommerce\Services\Payment\Drivers\CodPaymentDriver;
use Plugins\Ecommerce\Services\Payment\Drivers\MomoPaymentDriver;
use Plugins\Ecommerce\Services\Payment\Drivers\VnPayPaymentDriver;
use Plugins\Ecommerce\Services\Payment\PaymentDriverInterface;
use Plugins\Ecommerce\Services\Payment\PaymentManager;

/** Phase 20 (CMS-057). Unit thuan - PaymentManager chi dieu phoi, khong I/O rieng. */
final class PaymentManagerTest extends TestCase
{
    private function manager(): PaymentManager
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        return new PaymentManager(new CodPaymentDriver(), new MomoPaymentDriver($config), new VnPayPaymentDriver($config));
    }

    public function testDriverReturnsCodDriverForKeyCod(): void
    {
        $driver = $this->manager()->driver('cod');

        self::assertInstanceOf(CodPaymentDriver::class, $driver);
        self::assertSame('cod', $driver->key());
    }

    public function testDriverReturnsMomoDriverForKeyMomo(): void
    {
        $driver = $this->manager()->driver('momo');

        self::assertInstanceOf(MomoPaymentDriver::class, $driver);
    }

    public function testDriverReturnsVnPayDriverForKeyVnpay(): void
    {
        $driver = $this->manager()->driver('vnpay');

        self::assertInstanceOf(VnPayPaymentDriver::class, $driver);
    }

    public function testDriverThrowsForUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager()->driver('paypal');
    }

    public function testHasReturnsTrueForKnownDriverFalseForUnknown(): void
    {
        $manager = $this->manager();

        self::assertTrue($manager->has('cod'));
        self::assertTrue($manager->has('momo'));
        self::assertTrue($manager->has('vnpay'));
        self::assertFalse($manager->has('paypal'));
    }

    public function testEveryDriverImplementsPaymentDriverInterface(): void
    {
        foreach (['cod', 'momo', 'vnpay'] as $key) {
            self::assertInstanceOf(PaymentDriverInterface::class, $this->manager()->driver($key));
        }
    }
}
