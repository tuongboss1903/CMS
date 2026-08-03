<?php
/** Phase 20 (CMS-057). Gui khach hang khi Admin chuyen Order sang trang thai "shipped". Bien: $order (order_number/guest_email). */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Don hang dang duoc van chuyen</h2>
<p>Don hang <strong><?= $this->e((string) ($order['order_number'] ?? '')) ?></strong> cua ban da duoc giao cho don vi van chuyen.</p>
</body>
</html>
