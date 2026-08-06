<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services;

use Core\Config;

/**
 * Tinh phi giao hang ban kinh co dinh: trong pham vi max_radius_km tinh tu toa do shop -> phi co
 * dinh fee_amount, ngoai pham vi -> tu choi (caller tu quyet dinh hanh vi, class nay chi tra du
 * lieu thuan qua ShippingQuote). Cong thuc Haversine (khoang cach duong chim bay giua 2 toa do
 * GPS tren mat cau) - Zero-dependency, khong goi API Google Maps/geocoding tra phi ben ngoai,
 * dung nguyen tac da ap dung cho Mailer/HtmlSanitizer/PaymentManager (tu viet, khong SDK ngoai).
 *
 * Nhan Core\Config truc tiep (khong ep mang con 'shipping' rieng qua constructor) - dung dung
 * pattern da co cua Plugins\Ecommerce\Services\Payment\Drivers\* (vd MomoPaymentDriver), giup
 * Container auto-wire duoc ma khong can dang ky binding rieng cho tham so array.
 */
final class ShippingFeeCalculator
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(private readonly Config $config)
    {
    }

    public function quote(float $customerLat, float $customerLng): ShippingQuote
    {
        $shopLat = (float) $this->config->get('shipping.shop_lat', 0.0);
        $shopLng = (float) $this->config->get('shipping.shop_lng', 0.0);
        $maxRadiusKm = (float) $this->config->get('shipping.max_radius_km', 0.0);
        $feeAmount = (float) $this->config->get('shipping.fee_amount', 0.0);

        $distanceKm = $this->distanceKm($shopLat, $shopLng, $customerLat, $customerLng);
        $withinRange = $distanceKm <= $maxRadiusKm;

        return new ShippingQuote(
            distanceKm: \round($distanceKm, 2),
            withinRange: $withinRange,
            fee: $withinRange ? $feeAmount : 0.0,
            maxRadiusKm: $maxRadiusKm
        );
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = \deg2rad($lat2 - $lat1);
        $lngDelta = \deg2rad($lng2 - $lng1);

        $a = \sin($latDelta / 2) ** 2
            + \cos(\deg2rad($lat1)) * \cos(\deg2rad($lat2)) * \sin($lngDelta / 2) ** 2;

        $c = 2 * \atan2(\sqrt($a), \sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
