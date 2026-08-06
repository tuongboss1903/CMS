<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services;

/** Ket qua tinh phi ship - thuan du lieu, khong logic (readonly value object). */
final class ShippingQuote
{
    public function __construct(
        public readonly float $distanceKm,
        public readonly bool $withinRange,
        public readonly float $fee,
        public readonly float $maxRadiusKm,
    ) {
    }
}
