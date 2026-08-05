<?php
/**
 * Phase 15 (CMS-052). Gui toi khach khi Comment bi tu choi. Bien: $guest_name, $page_title.
 * Ten file dung underscore - xem docblock comment_new.php ve NAME_PATTERN cua View.
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Bình luận của bạn không được duyệt</h2>
<p>Xin chào <?= $this->e((string) ($guest_name ?? '')) ?>,</p>
<p>Bình luận của bạn trên trang "<?= $this->e((string) ($page_title ?? '')) ?>" không được duyệt để hiển thị công khai.</p>
</body>
</html>
