<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use Core\Database;
use Core\TenantManager;
use Core\Validator;
use Plugins\Ecommerce\Services\CartService;
use Plugins\Ecommerce\Services\Payment\PaymentGatewaySettings;
use Plugins\Ecommerce\Services\ShippingFeeCalculator;

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
 *
 * Phase 24 (CMS-081): tu choi dat hang neu payment_method duoc chon da bi Admin tat qua trang Quan
 * ly thanh toan (PaymentGatewaySettings) - chan ca truong hop client gui thang request bo qua UI
 * (UI Checkout da loc san, day la lop bao ve thu 2 o server).
 *
 * Ship ban kinh co dinh: bat buoc customer_lat/customer_lng (toa do GPS khach hang, checkout view
 * tu lay qua navigator.geolocation phia trinh duyet) - ngoai ban kinh (ShippingFeeCalculator, cau
 * hinh config/shipping.php) thi TU CHOI dat hang (EcommerceValidationException, khong tao don),
 * trong ban kinh thi cong them shipping_fee CO DINH vao total_amount, snapshot ca 2 gia tri
 * (shipping_fee/shipping_distance_km) vao orders - doi cau hinh ban kinh/phi sau khong anh huong
 * don da dat.
 */
final class PlaceOrderAction
{
    private const VALID_PAYMENT_METHODS = ['cod', 'momo', 'vnpay'];

    public function __construct(
        private readonly CartService $cartService,
        private readonly Database $database,
        private readonly PaymentGatewaySettings $paymentGatewaySettings,
        private readonly ShippingFeeCalculator $shippingFeeCalculator,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int, order_number: string, guest_name: string, guest_email: string, total_amount: float, payment_method: string, shipping_fee: float, shipping_distance_km: float}
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
            'customer_lat' => 'required|numeric',
            'customer_lng' => 'required|numeric',
        ]);

        if ($result->fails()) {
            throw new EcommerceValidationException('Du lieu khong hop le.', $result->errors());
        }

        $shippingQuote = $this->shippingFeeCalculator->quote((float) $data['customer_lat'], (float) $data['customer_lng']);

        if (!$shippingQuote->withinRange) {
            throw new EcommerceValidationException(
                "Ngoai pham vi giao hang ({$shippingQuote->maxRadiusKm}km).",
                ['shipping' => ["Rất tiếc, vị trí của bạn cách quán khoảng {$shippingQuote->distanceKm}km, vượt quá phạm vi giao hàng {$shippingQuote->maxRadiusKm}km. Vui lòng đặt hàng tại quán hoặc chọn địa chỉ khác gần hơn."]]
            );
        }

        $paymentMethod = \in_array($data['payment_method'] ?? null, self::VALID_PAYMENT_METHODS, true)
            ? (string) $data['payment_method']
            : 'cod';

        if (!$this->paymentGatewaySettings->isEnabled($paymentMethod)) {
            throw new EcommerceValidationException(
                'Phuong thuc thanh toan khong kha dung.',
                ['payment_method' => ['Phương thức thanh toán này hiện không khả dụng, vui lòng chọn phương thức khác.']]
            );
        }

        $tenantId = $this->tenantManager->id();
        $items = $this->cartService->items();
        $itemsTotal = $this->cartService->total();
        $total = $itemsTotal + $shippingQuote->fee;
        $orderNumber = $this->generateOrderNumber();

        $orderId = $this->database->transaction(function () use ($tenantId, $orderNumber, $data, $total, $shippingQuote, $items, $paymentMethod): int {
            $this->database->insert(
                'INSERT INTO orders (tenant_id, order_number, guest_name, guest_email, shipping_address, status, total_amount, payment_method, shipping_fee, shipping_distance_km)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $orderNumber,
                    (string) $data['guest_name'],
                    (string) $data['guest_email'],
                    (string) ($data['shipping_address'] ?? ''),
                    'pending',
                    $total,
                    $paymentMethod,
                    $shippingQuote->fee,
                    $shippingQuote->distanceKm,
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
            'shipping_fee' => $shippingQuote->fee,
            'shipping_distance_km' => $shippingQuote->distanceKm,
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
