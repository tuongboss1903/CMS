<?php
/** Phase 20 (CMS-057). Gui khach hang khi Admin chuyen Order sang trang thai "shipped". Bien: $order (order_number/guest_email). */
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Đơn hàng đang được vận chuyển</h2>
<p>Đơn hàng <strong><?= $this->e((string) ($order['order_number'] ?? '')) ?></strong> của bạn đã được giao cho đơn vị vận chuyển.</p>
</body>
</html>
