<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services;

use Core\Session;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Gio hang la Session-based (KHONG bang DB rieng) - Owner
 * Decision o Architecture Analysis (YAGNI, dung dung vai tro "Storage" cua Core\Session). Mat gio
 * hang khi het phien la hanh vi chap nhan duoc o MVP. Key trong item: "{product_id}:{variant_id}"
 * (variant_id rong = "0") de gop dung so luong khi them lai cung 1 san pham/bien the.
 */
final class CartService
{
    private const SESSION_KEY = 'ecommerce.cart_items';

    public function __construct(private readonly Session $session)
    {
    }

    /** @return array<string, array{product_id: int, variant_id: int|null, name: string, price: float, quantity: int}> */
    public function items(): array
    {
        /** @var array<string, array{product_id: int, variant_id: int|null, name: string, price: float, quantity: int}> $items */
        $items = $this->session->get(self::SESSION_KEY, []);

        return $items;
    }

    public function add(int $productId, ?int $variantId, string $name, float $price, int $quantity): void
    {
        $items = $this->items();
        $key = $this->itemKey($productId, $variantId);

        if (isset($items[$key])) {
            $items[$key]['quantity'] += $quantity;
        } else {
            $items[$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
            ];
        }

        $this->session->set(self::SESSION_KEY, $items);
    }

    public function remove(int $productId, ?int $variantId): void
    {
        $items = $this->items();
        unset($items[$this->itemKey($productId, $variantId)]);

        $this->session->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function total(): float
    {
        $total = 0.0;

        foreach ($this->items() as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    private function itemKey(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? 0);
    }
}
