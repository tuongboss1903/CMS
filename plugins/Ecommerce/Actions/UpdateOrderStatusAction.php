<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use Core\Database;
use Core\TenantManager;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Luong trang thai don gian qua cot status string (dung tien le
 * comments.status/pages.status, khong ENUM/state machine rieng): pending -> processing -> shipped
 * -> completed, hoac pending/processing -> cancelled. Moi buoc khac bi tu choi.
 *
 * Phase 20 (CMS-057): bo sung trang thai "shipped" giua "processing" va "completed" (Owner
 * Decision, thay doi hanh vi nghiep vu da co tu Phase 19 - da duyet truoc khi sua). "shipped" KHONG
 * cho phep huy (khong co trong danh sach chuyen tiep cua no) - hang da giao thi khong con "cancel"
 * duoc nua, chi con tien len "completed".
 */
final class UpdateOrderStatusAction
{
    /** @var array<string, list<string>> */
    private const VALID_TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @throws EcommerceValidationException */
    public function execute(int $orderId, string $newStatus): void
    {
        $tenantId = $this->tenantManager->id();

        $order = $this->database->selectOne(
            'SELECT id, status FROM orders WHERE id = ? AND tenant_id = ?',
            [$orderId, $tenantId]
        );

        if ($order === null) {
            throw new EcommerceValidationException('Don hang khong ton tai.', ['order_id' => ['Don hang khong ton tai.']]);
        }

        $currentStatus = (string) $order['status'];
        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];

        if (!\in_array($newStatus, $allowed, true)) {
            throw new EcommerceValidationException(
                "Khong the chuyen tu '{$currentStatus}' sang '{$newStatus}'.",
                ['status' => ["Khong the chuyen tu '{$currentStatus}' sang '{$newStatus}'."]]
            );
        }

        $this->database->update(
            'UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$newStatus, $orderId]
        );
    }
}
