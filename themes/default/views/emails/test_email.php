<?php
/** Phase 24 (CMS-081). Email thu nghiem gui tu /admin/email-settings de xac minh cau hinh SMTP. Bien: $sent_at. */
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; max-width:600px; margin:0 auto;">
<h2>Email thử nghiệm cấu hình SMTP</h2>
<p>Đây là email thử nghiệm được gửi từ trang Quản lý Email của hệ thống CMS lúc <?= $this->e((string) ($sent_at ?? '')) ?>.</p>
<p>Nếu bạn nhận được email này, cấu hình SMTP hiện tại đang hoạt động đúng.</p>
</body>
</html>
