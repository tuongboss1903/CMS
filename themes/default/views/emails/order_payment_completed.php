<?php
/** Phase 20 (CMS-057). Gui khach hang khi Webhook cong thanh toan xac nhan thanh cong. Bien: $order (order_number/guest_email). */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Thanh toan thanh cong</h2>
<p>Don hang <strong><?= $this->e((string) ($order['order_number'] ?? '')) ?></strong> da duoc thanh toan thanh cong.</p>
<p>Chung toi se bat dau xu ly va giao hang cho ban.</p>
</body>
</html>
