# PRODUCTION MONITORING — Vận hành & Giám sát Production

> Tài liệu này bổ sung cho `DEPLOYMENT.md` (cài đặt ban đầu) và `STAGING_CHECKLIST.md` (checklist triển khai Staging) — tập trung vào **vận hành liên tục sau go-live**: giám sát uptime, quản lý log, sao lưu/khôi phục, và tăng cường bảo mật ở tầng hạ tầng.
>
> **Phạm vi Phase 9 (Owner Decision)**: tài liệu thuần túy, **không sửa bất kỳ file PHP nào** trong dự án. Mọi khuyến nghị dưới đây thực hiện ở tầng hạ tầng (Web Server/OS/Cronjob) — không đụng `core/*`/`modules/*`.

---

## 1. Health Check & Uptime Monitoring

### 1.1. Bản chất endpoint `GET /health` đã có sẵn

Endpoint `/health` **đã tồn tại thật** từ Core Foundation (`core/Application.php`, đăng ký trực tiếp trong `Application::boot()`, không thuộc Module nào) — không cần triển khai mới.

**Đặc điểm kỹ thuật quan trọng**:
- Đăng ký **ngoài** `TenantResolverMiddleware`/`StartSessionMiddleware` (route được thêm sau khi khối `$router->group(...)` đã đóng) → **không cần domain hợp lệ trong `site_domains`** để phản hồi. Công cụ giám sát có thể gọi qua IP server trực tiếp hoặc bất kỳ domain nào trỏ vào server, không bắt buộc phải là domain tenant thật.
- Phản hồi cố định, luôn `200 OK`:
  ```json
  {"success": true, "data": {"status": "ok"}, "message": "", "errors": []}
  ```
- **Đây là Liveness Check thuần túy** (xác nhận tiến trình PHP còn phản hồi HTTP) — **không** kiểm tra kết nối Database, không kiểm tra quyền ghi `storage/`. Owner Decision Phase 9: giữ nguyên hiện trạng, không nâng cấp thành Readiness Check (không sửa `core/Application.php`).

### 1.2. Tích hợp UptimeRobot / Pingdom (giám sát bên ngoài)

- Tạo 1 "HTTP(s) Monitor" mới, URL: `https://<domain-chinh>/health`.
- Interval khuyến nghị: 1-5 phút.
- Điều kiện báo lỗi: HTTP status ≠ 200, hoặc response chứa `"status":"ok"` không đúng (kiểm tra keyword `ok` trong body nếu công cụ hỗ trợ).
- Vì `/health` không qua `TenantResolverMiddleware`, có thể trỏ monitor vào domain bất kỳ đã trỏ DNS về server (không nhất thiết phải là domain tenant chính) — kể cả IP server trực tiếp nếu web server chấp nhận `Host` header tùy ý (`server_name _;` theo cấu hình `DEPLOYMENT.md` mục 2).

### 1.3. Tích hợp `systemd` (nếu PHP-FPM chạy như service)

Không cần healthcheck script riêng cho PHP-FPM tự thân (`systemd` đã tự khởi động lại service nếu process chết) — dùng `systemd` timer gọi `curl` định kỳ để phát hiện lỗi ở tầng ứng dụng (khác lỗi ở tầng process):

```ini
# /etc/systemd/system/cms-healthcheck.service
[Unit]
Description=CMS Health Check

[Service]
Type=oneshot
ExecStart=/usr/bin/curl -fsS --max-time 5 https://your-domain.example/health
```

```ini
# /etc/systemd/system/cms-healthcheck.timer
[Unit]
Description=Run CMS Health Check every 2 minutes

[Timer]
OnBootSec=1min
OnUnitActiveSec=2min

[Install]
WantedBy=timers.target
```

Kích hoạt: `systemctl enable --now cms-healthcheck.timer`. Log lỗi xem qua `journalctl -u cms-healthcheck.service`.

### 1.4. Tích hợp Docker Healthcheck (nếu container hóa)

```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -fsS http://localhost/health || exit 1
```

---

## 2. Log Rotation & Log Management

`core/Logger.php` ghi mọi log (exception ≥500, lỗi Hook callback) vào **1 file cố định duy nhất**: `storage/logs/app.log` — không tự rotation, không giới hạn dung lượng. Xử lý ở tầng OS qua `logrotate` (không sửa `core/Logger.php` — đã có test phụ thuộc hành vi hiện tại).

### 2.1. Cấu hình `/etc/logrotate.d/cms`

```
/path/to/cms/storage/logs/app.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0664 www-data www-data
}
```

**Giải thích từng directive**:
- `daily` — xoay file mỗi ngày (phù hợp tần suất log lỗi thực tế của 1 CMS quy mô vừa).
- `rotate 14` — giữ 14 bản gần nhất (~2 tuần lịch sử log), file cũ hơn tự xóa.
- `compress` + `delaycompress` — nén file log cũ bằng `gzip`, **trì hoãn nén file vừa xoay hôm qua** (giữ nguyên dạng text 1 ngày, tiện tra cứu nhanh sự cố mới xảy ra).
- `missingok` — không báo lỗi nếu `app.log` chưa tồn tại (ví dụ site mới chưa từng có lỗi ≥500).
- `notifempty` — không xoay file rỗng (tránh sinh file nén rỗng vô ích).
- `create 0664 www-data www-data` — sau khi xoay, tạo file mới với quyền ghi cho user chạy PHP-FPM (đổi `www-data` theo đúng user thật trên server, khớp `DEPLOYMENT.md` mục 5).

### 2.2. Kiểm tra hoạt động

```bash
logrotate -d /etc/logrotate.d/cms   # dry-run, xem log se lam gi ma khong thuc thi
logrotate -f /etc/logrotate.d/cms   # force chay ngay de kiem tra that
```

---

## 3. Backup & Disaster Recovery Strategy

Không có script `bin/backup.php` (Owner Decision Phase 9) — dùng trực tiếp công cụ chuẩn `mysqldump`/`tar`, đã có sẵn theo yêu cầu hệ thống ở `DEPLOYMENT.md` mục 1 (không phải dependency mới).

### 3.1. Backup Database

```bash
mysqldump --single-transaction --routines --triggers \
  -u <db_user> -p<db_password> <db_database> \
  | gzip > /path/to/backups/db_$(date +%Y%m%d_%H%M%S).sql.gz
```

- `--single-transaction` — backup nhất quán mà **không khóa bảng** (quan trọng cho InnoDB, tránh downtime khi backup lúc hệ thống đang chạy thật).
- `--routines --triggers` — dự án hiện chưa dùng Stored Procedure/Trigger nào (đúng nguyên tắc "không Trigger cho business logic" đã giữ xuyên suốt — xem `docs/kien-truc-cot-loi/core-architecture.md`), nhưng thêm cờ này để backup luôn đầy đủ nếu phát sinh sau này, không rủi ro thiếu sót.

### 3.2. Backup File Storage (Media)

```bash
tar -czf /path/to/backups/media_$(date +%Y%m%d_%H%M%S).tar.gz \
  -C /path/to/cms storage/app/media
```

Chỉ backup `storage/app/media/` — **không** `public/uploads/` (đã xác nhận không phải nơi lưu Media thật, xem `STAGING_CHECKLIST.md`/`docs/kien-truc-cot-loi/core-architecture.md` mục 3.46).

### 3.3. Lệnh khôi phục (Restore) — luôn kiểm thử trên môi trường riêng trước

```bash
# Restore Database (tao database rong truoc neu chua co)
gunzip < /path/to/backups/db_20260802_020000.sql.gz | mysql -u <db_user> -p<db_password> <db_database>

# Restore Media
tar -xzf /path/to/backups/media_20260802_020000.tar.gz -C /path/to/cms
```

**Bắt buộc**: sau mỗi lần restore thử nghiệm, chạy `vendor/bin/phpunit` + đăng nhập Admin thật + xem 1 trang Public để xác nhận dữ liệu khôi phục đúng — không coi backup là "đã kiểm chứng" chỉ vì lệnh chạy không lỗi.

### 3.4. CronJob tự động hàng ngày

```cron
# /etc/cron.d/cms-backup — chay 2:00 sang hang ngay
0 2 * * * www-data /path/to/backup-script.sh >> /path/to/backups/backup.log 2>&1
```

Nội dung `backup-script.sh` gộp 2 lệnh mục 3.1 + 3.2, và nên thêm dọn dẹp backup quá cũ (ví dụ giữ 30 ngày gần nhất qua `find ... -mtime +30 -delete`) để tránh đầy đĩa theo thời gian — không có cơ chế tự dọn nào trong `mysqldump`/`tar` tự thân.

---

## 4. Production Security Hardening Checklist

### 4.1. Chống Brute-Force đăng nhập Admin — tầng Nginx (`limit_req_zone` theo IP)

```nginx
# Trong khoi http { } (ngoai server block)
limit_req_zone $binary_remote_addr zone=admin_login:10m rate=5r/m;

server {
    # ... cau hinh server that (xem DEPLOYMENT.md muc 2) ...

    location = /admin/login {
        limit_req zone=admin_login burst=3 nodelay;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        # ... fastcgi_pass nhu binh thuong ...
    }
}
```

- `rate=5r/m` — tối đa 5 request/phút/IP tới `/admin/login` (áp dụng cho cả `GET` hiển thị form lẫn `POST` submit — nên tách riêng zone cho `POST` nếu muốn giới hạn chặt hơn chỉ với hành vi submit).
- `burst=3 nodelay` — cho phép vượt ngắn hạn 3 request (tránh chặn nhầm người dùng thật gõ sai 1-2 lần liên tiếp), vượt quá `burst` → `503 Service Temporarily Unavailable` ngay lập tức.

### 4.2. HTTP Security Headers khuyến nghị

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' https://cdn.quilljs.com; frame-ancestors 'self';" always;
```

- `X-Frame-Options: SAMEORIGIN` — chặn Clickjacking (nhúng Admin qua `<iframe>` từ domain khác).
- `X-Content-Type-Options: nosniff` — chặn trình duyệt tự đoán sai MIME type (giảm rủi ro XSS qua file upload Media giả dạng).
- `Content-Security-Policy` — **`script-src` phải bao gồm `https://cdn.quilljs.com`** (Rich Text Editor dùng qua CDN từ Pages Admin UI, xem `docs/kien-truc-cot-loi/core-architecture.md` mục 3.38) — nếu thiếu domain này, Admin Create/Edit Page sẽ bị chặn tải Quill.js, giao diện soạn thảo hỏng hoàn toàn. **Kiểm tra kỹ trên Staging trước khi áp dụng CSP lên Production** — CSP quá chặt là nguyên nhân phổ biến nhất gây vỡ giao diện âm thầm (không lỗi PHP, chỉ lỗi Console trình duyệt).

### 4.3. Phân tích minh bạch: vì sao KHÔNG dùng `RateLimiter` sẵn có cho việc này

Dự án đã có `core/RateLimiter.php` (đầy đủ, có test) và `config/auth.php` đã chuẩn bị sẵn `login_throttle.max_attempts`/`decay_seconds` — nhưng **cố tình không wiring vào `LoginController.php`** ở Phase 9 (Owner Decision). Lý do kỹ thuật cần đội Ops hiểu rõ:

> `RateLimiter` lưu bộ đếm số lần thử **trong `Session`** (cookie-based, riêng theo từng trình duyệt/client). Một script tấn công brute-force thực sự **không gửi cookie** sẽ nhận `Session` mới hoàn toàn ở mỗi request → bộ đếm luôn bắt đầu lại từ 0 → **không bao giờ bị chặn**. Cơ chế này chỉ hữu ích để nhắc nhở người dùng thật lỡ gõ sai mật khẩu nhiều lần trên cùng 1 trình duyệt — **không phải giải pháp chống brute-force thật sự**.

Vì vậy biện pháp chống brute-force hiệu quả **phải nằm ở tầng Reverse Proxy** (mục 4.1) — chặn theo địa chỉ IP nguồn, không phụ thuộc cookie/session của client, không thể bị né bằng cách đơn giản "không gửi cookie". Đây là quyết định kiến trúc có chủ đích, không phải sơ suất bỏ sót.

### 4.4. Checklist tổng hợp

- [ ] `limit_req_zone` cho `/admin/login` đã cấu hình và test bằng cách gửi >5 request/phút thật (xác nhận nhận `503`).
- [ ] HTTP Security Headers đã thêm, đã test Admin Create/Edit Page vẫn tải được Quill.js sau khi bật CSP.
- [ ] `logrotate` đã cấu hình và chạy thử `logrotate -f` thành công (mục 2.2).
- [ ] CronJob backup hàng ngày đã kích hoạt (`crontab -l` hoặc `/etc/cron.d/cms-backup` xác nhận có mặt).
- [ ] Đã thử khôi phục (restore) ít nhất 1 lần trên môi trường riêng — không chỉ tin tưởng backup chạy không lỗi.
- [ ] `/health` đã thêm vào công cụ giám sát uptime bên ngoài (UptimeRobot/Pingdom/tương đương).
- [ ] `SESSION_SECURE=true` đã bật (nếu chưa, xem `DEPLOYMENT.md` mục 4 — bắt buộc khi có HTTPS).
