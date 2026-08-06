<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use PHPUnit\Framework\TestCase;
use Plugins\Ecommerce\Services\ShippingFeeCalculator;

/** Unit thuan - ShippingFeeCalculator chi tinh toan (Haversine), khong I/O. Fixture config/shipping.php: shop tai 10.7769,106.7009, ban kinh 5km, phi 15000. */
final class ShippingFeeCalculatorTest extends TestCase
{
    private function calculator(): ShippingFeeCalculator
    {
        return new ShippingFeeCalculator(new Config(__DIR__ . '/../Fixtures/config'));
    }

    public function testSameCoordinatesAsShopIsWithinRangeWithZeroDistance(): void
    {
        $quote = $this->calculator()->quote(10.7769, 106.7009);

        self::assertTrue($quote->withinRange);
        self::assertSame(0.0, $quote->distanceKm);
        self::assertSame(15000.0, $quote->fee);
    }

    public function testNearbyPointWithinRadiusGetsFixedFee(): void
    {
        // ~2km ve phia bac (0.018 do vi ~ 2km).
        $quote = $this->calculator()->quote(10.7949, 106.7009);

        self::assertTrue($quote->withinRange);
        self::assertLessThanOrEqual(5.0, $quote->distanceKm);
        self::assertSame(15000.0, $quote->fee);
    }

    public function testFarPointOutsideRadiusIsRejectedWithZeroFee(): void
    {
        // ~11km ve phia bac (0.1 do vi ~ 11km) - vuot ban kinh 5km.
        $quote = $this->calculator()->quote(10.8769, 106.7009);

        self::assertFalse($quote->withinRange);
        self::assertGreaterThan(5.0, $quote->distanceKm);
        self::assertSame(0.0, $quote->fee);
    }

    public function testMaxRadiusKmReflectsConfig(): void
    {
        $quote = $this->calculator()->quote(10.7769, 106.7009);

        self::assertSame(5.0, $quote->maxRadiusKm);
    }
}
