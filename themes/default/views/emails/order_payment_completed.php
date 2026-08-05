<?php
/** Phase 20 (CMS-057). Gui khach hang khi Webhook cong thanh toan xac nhan thanh cong. Bien: $order (order_number/guest_email). */
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Thanh toán thành công</h2>
<p>Đơn hàng <strong><?= $this->e((string) ($order['order_number'] ?? '')) ?></strong> đã được thanh toán thành công.</p>
<p>Chúng tôi sẽ bắt đầu xử lý và giao hàng cho bạn.</p>
</body>
</html>
