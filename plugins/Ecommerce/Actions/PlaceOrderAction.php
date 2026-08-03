<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use Core\Database;
use Core\TenantManager;
use Core\Validator;
use Plugins\Ecommerce\Services\CartService;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Checkout dang Guest (khong tao users moi, dung tien le
 * Comment System Phase 14). Tao orders + order_items trong 1 transaction() (Database::transaction,
 * nested-safe san co), snapshot ten/gia san pham tai thoi diem mua (product_name_snapshot/
 * unit_price - san pham co the doi sau khong anh huong lich su). Tru ton kho + xoa gio hang CHI khi
 * transaction thanh cong. order_number sinh bang random_bytes (PHP core, Zero-dependency).
 *
 * Phase 20 (CMS-057): bo sung payment_method (cod|momo|vnpay, mac dinh 'cod' neu khong gui/gia tri
 * la) - CheckoutPlaceOrderController doc gia tri nay sau khi Action tra ve de quyet dinh co goi
 * PaymentManager::driver()->charge() dua khach sang cong thanh toan hay khong (COD khong can).
 */
final class PlaceOrderAction
{
    private const VALID_PAYMENT_METHODS = ['cod', 'momo', 'vnpay'];

    public function __construct(
        private readonly CartService $cartService,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int, order_number: string, guest_name: string, guest_email: string, total_amount: float, payment_method: string}
     *
     * @throws EcommerceValidationException
     */
    public function execute(array $data): array
    {
        if ($this->cartService->isEmpty()) {
            throw new EcommerceValidationException('Gio hang dang trong.', ['cart' => ['Gio hang dang trong.']]);
        }

        $result = $this->validator->validate($data, [
            'guest_name' => 'required|string',
            'guest_email' => 'required|email',
        ]);

        if ($result->fails()) {
            throw new EcommerceValidationException('Du lieu khong hop le.', $result->errors());
        }

        $paymentMethod = \in_array($data['payment_method'] ?? null, self::VALID_PAYMENT_METHODS, true)
            ? (string) $data['payment_method']
            : 'cod';

        $tenantId = $this->tenantManager->id();
        $items = $this->cartService->items();
        $total = $this->cartService->total();
        $orderNumber = $this->generateOrderNumber();

        $orderId = $this->database->transaction(function () use ($tenantId, $orderNumber, $data, $total, $items, $paymentMethod): int {
            $this->database->insert(
                'INSERT INTO orders (tenant_id, order_number, guest_name, guest_email, shipping_address, status, total_amount, payment_method)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $orderNumber,
                    (string) $data['guest_name'],
                    (string) $data['guest_email'],
                    (string) ($data['shipping_address'] ?? ''),
                    'pending',
                    $total,
                    $paymentMethod,
                ]
            );

            $orderId = (int) $this->database->connection()->lastInsertId();

            foreach ($items as $item) {
                $this->database->insert(
                    'INSERT INTO order_items (order_id, product_id, product_variant_id, product_name_snapshot, unit_price, quantity, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        $item['product_id'],
                        $item['variant_id'],
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        $item['price'] * $item['quantity'],
                    ]
                );

                $this->decrementStock($tenantId, $item['product_id'], $item['variant_id'], $item['quantity']);
            }

            return $orderId;
        });

        $this->cartService->clear();

        return [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'guest_name' => (string) $data['guest_name'],
            'guest_email' => (string) $data['guest_email'],
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
        ];
    }

    private function decrementStock(int|string|null $tenantId, int $productId, ?int $variantId, int $quantity): void
    {
        if ($variantId !== null) {
            $this->database->update(
                'UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?',
                [$quantity, $variantId, $tenantId]
            );

            return;
        }

        $this->database->update(
            'UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?',
            [$quantity, $productId, $tenantId]
        );
    }

    private function generateOrderNumber(): string
    {
        return \strtoupper(\date('Ymd') . '-' . \substr(\bin2hex(\random_bytes(4)), 0, 6));
    }
}
