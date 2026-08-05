<?php
/**
 * Phase 20 (CMS-057). Gui ca cho khach hang (xac nhan don hang) va Admin (don hang moi can xu ly) -
 * dung chung 1 template, khac biet chi o subject/nguoi nhan (xem Hooks.php::"order.created").
 * Bien: $order (mang tu PlaceOrderAction::execute()/DB, co id/order_number/guest_name/total_amount).
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Xác nhận đơn hàng <?= $this->e((string) ($order['order_number'] ?? '')) ?></h2>
<p>Cảm ơn <strong><?= $this->e((string) ($order['guest_name'] ?? '')) ?></strong> đã đặt hàng.</p>
<p>Tổng tiền: <strong><?= $this->e((string) ($order['total_amount'] ?? '')) ?></strong></p>
<p>Chúng tôi sẽ liên hệ sớm để xác nhận và giao hàng.</p>
</body>
</html>
