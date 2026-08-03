<?php
/**
 * Phase 20 (CMS-057). Gui ca cho khach hang (xac nhan don hang) va Admin (don hang moi can xu ly) -
 * dung chung 1 template, khac biet chi o subject/nguoi nhan (xem Hooks.php::"order.created").
 * Bien: $order (mang tu PlaceOrderAction::execute()/DB, co id/order_number/guest_name/total_amount).
 */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Xac nhan don hang <?= $this->e((string) ($order['order_number'] ?? '')) ?></h2>
<p>Cam on <strong><?= $this->e((string) ($order['guest_name'] ?? '')) ?></strong> da dat hang.</p>
<p>Tong tien: <strong><?= $this->e((string) ($order['total_amount'] ?? '')) ?></strong></p>
<p>Chung toi se lien he som de xac nhan va giao hang.</p>
</body>
</html>
