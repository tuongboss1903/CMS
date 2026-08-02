<?php
/**
 * Phase 15 (CMS-052). Gui toi Admin khi co Comment moi can duyet. Bien: $guest_name, $page_title,
 * $body, $admin_url. Khong extend()/section() - template email doc lap, tu chua <html> day du.
 *
 * Ten file dung underscore (khong gach ngang) - View::resolvePath() dung NAME_PATTERN
 * '/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/' KHONG chap nhan dau "-" trong ten template (phat hien qua
 * PHPUnit that - ban dau dat ten comment-new.php gay ViewNotFoundException ngay o buoc regex).
 */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Co binh luan moi cho duyet</h2>
<p><strong><?= $this->e((string) ($guest_name ?? '')) ?></strong> vua binh luan tren trang "<?= $this->e((string) ($page_title ?? '')) ?>":</p>
<blockquote style="border-left:3px solid #ccc; padding-left:12px; color:#555; margin-left:0;">
<?= \nl2br($this->e((string) ($body ?? ''))) ?>
</blockquote>
<p><a href="<?= $this->e((string) ($admin_url ?? '/admin/comments')) ?>" style="background:#2563eb;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block;">Xem va duyet</a></p>
</body>
</html>
