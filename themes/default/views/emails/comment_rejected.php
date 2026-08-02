<?php
/**
 * Phase 15 (CMS-052). Gui toi khach khi Comment bi tu choi. Bien: $guest_name, $page_title.
 * Ten file dung underscore - xem docblock comment_new.php ve NAME_PATTERN cua View.
 */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Binh luan cua ban khong duoc duyet</h2>
<p>Xin chao <?= $this->e((string) ($guest_name ?? '')) ?>,</p>
<p>Binh luan cua ban tren trang "<?= $this->e((string) ($page_title ?? '')) ?>" khong duoc duyet de hien thi cong khai.</p>
</body>
</html>
