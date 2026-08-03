<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use Core\Database;
use Core\TenantManager;
use Plugins\Ecommerce\Services\CartService;
use Plugins\Ecommerce\Services\ProductService;

/** Phase 19 (Ecommerce MVP, CMS-056). Validate san pham thuoc dung tenant/dang "published"/con du ton kho truoc khi them vao gio (CartService, Session-based). */
final class AddToCartAction
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly Database $database,
        private readonly ProductService $productService,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @throws EcommerceValidationException */
    public function execute(int $productId, ?int $variantId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new EcommerceValidationException('So luong khong hop le.', ['quantity' => ['So luong phai lon hon 0.']]);
        }

        $tenantId = $this->tenantManager->id();

        $product = $this->database->selectOne(
            "SELECT * FROM products WHERE id = ? AND tenant_id = ? AND status = 'published' AND deleted_at IS NULL",
            [$productId, $tenantId]
        );

        if ($product === null) {
            throw new EcommerceValidationException('San pham khong ton tai.', ['product_id' => ['San pham khong ton tai.']]);
        }

        $variant = null;
        $availableStock = (int) $product['stock_quantity'];

        if ($variantId !== null) {
            $variant = $this->database->selectOne(
                'SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND tenant_id = ?',
                [$variantId, $productId, $tenantId]
            );

            if ($variant === null) {
                throw new EcommerceValidationException('Bien the san pham khong hop le.', ['variant_id' => ['Bien the san pham khong hop le.']]);
            }

            $availableStock = (int) $variant['stock_quantity'];
        }

        if ($availableStock < $quantity) {
            throw new EcommerceValidationException('Khong du ton kho.', ['quantity' => ['Khong du ton kho.']]);
        }

        $price = $this->productService->effectivePrice($product, $variant);
        $name = $variant !== null ? $product['name'] . ' - ' . $variant['name'] : (string) $product['name'];

        $this->cartService->add($productId, $variantId, $name, $price, $quantity);
    }
}
