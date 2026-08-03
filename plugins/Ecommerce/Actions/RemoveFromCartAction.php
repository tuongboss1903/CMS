<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use Plugins\Ecommerce\Services\CartService;

final class RemoveFromCartAction
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function execute(int $productId, ?int $variantId): void
    {
        $this->cartService->remove($productId, $variantId);
    }
}
