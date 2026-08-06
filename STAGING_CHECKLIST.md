# STAGING CHECKLIST — Triển khai Server Thử nghiệm (Staging/VPS)

> Checklist thao tác theo đúng thứ tự thực hiện. Giải thích kỹ thuật chi tiết (vì sao/rủi ro) xem `DEPLOYMENT.md` — tài liệu này **không lặp lại nội dung đó**, chỉ liệt kê hành động cụ thể + lệnh chạy thật.

---

## 1. Web Server Setup

- [ ] Cài đặt Nginx (khuyến nghị) hoặc Apache + `mod_rewrite` — cấu hình mẫu: `DEPLOYMENT.md` mục 2.
- [ ] Trỏ document root đúng vào thư mục `public/` (không phải root repo).
- [ ] **(Chỉ Apache)** Tạo file `public/.htaccess`:
  ```apache
  <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule ^ index.php [L]
  </IfModule>
  ```
  *(File này chưa có sẵn trong repo — phải tạo thủ công trên server Staging nếu dùng Apache, xem `DEPLOYMENT.md` mục 2.)*
- [ ] Xác nhận chặn truy cập `.env`/`.git` qua web server (mẫu Nginx: `location ~ /\.(?!well-known) { deny all; }`).
- [ ] Test: truy cập 1 domain bất kỳ đã trỏ về server → phải thấy `404` (chưa có `site_domains` tương ứng) — xác nhận routing qua `public/index.php` hoạt động đúng trước khi seed dữ liệu.

## 2. SSL/HTTPS Multi-Domain

- [ ] Xác định trước **danh sách đầy đủ domain Staging** sẽ dùng (ví dụ: `staging-cms.example.com`, `staging-restaurant.example.com`) — chứng chỉ SAN cần biết domain trước khi cấp.
- [ ] Cài `certbot` (kèm plugin đúng web server: `python3-certbot-nginx` hoặc `python3-certbot-apache`).
- [ ] Cấp 1 chứng chỉ SAN cho toàn bộ domain cùng lúc:
  ```bash
  certbot --nginx -d staging-cms.example.com -d staging-restaurant.example.com
  ```
  *(Không dùng Wildcard Certificate — các domain tenant là domain độc lập, không phải subdomain cùng 1 gốc, xem Architecture Analysis Phase 8.)*
- [ ] Xác nhận renew tự động đã bật (`certbot renew --dry-run`).
- [ ] Sau khi HTTPS hoạt động: đổi `SESSION_SECURE=true` trong `.env` (bắt buộc — xem `DEPLOYMENT.md` mục 4), khởi động lại PHP-FPM/web server để nạp `.env` mới.
- [ ] Khi thêm domain mới sau này (Tenant mới qua `bin/add_site.php`): phải chạy lại `certbot` để bổ sung domain vào chứng chỉ SAN hiện có (`certbot --nginx -d ... --expand`) — **không tự động**, cần thao tác thủ công mỗi lần thêm tenant mới trên Staging.

## 3. Data Initialization

Chạy đúng thứ tự (mỗi bước phụ thuộc bước trước):

```bash
# 1. Migrate schema
php bin/migrate.php migrate
php bin/migrate.php status        # xac nhan toan bo migration da ap dung

# 2. Khoi tao Tenant 1 + Admin User (CHI CHAY 1 LAN DUY NHAT)
php bin/bootstrap.php "SaaS CMS Technology Co." staging-cms.example.com "Admin" admin@example.com "MatKhauManh123!"

# 3. Seed du lieu Tenant 1 (content pack "tech" - mac dinh)
php bin/seed_demo.php staging-cms.example.com tech

# 4. Tao Tenant 2 (tai su dung Admin User + Role da co, KHONG chay lai bootstrap.php)
php bin/add_site.php "Green Gourmet Restaurant & Cafe" staging-restaurant.example.com

# 5. Seed du lieu Tenant 2 (content pack "restaurant")
php bin/seed_demo.php staging-restaurant.example.com restaurant
```

- [ ] Bước 1 hoàn tất, `migrate.php status` không còn dòng nào "chưa áp dụng".
- [ ] Bước 2 chỉ chạy **đúng 1 lần** — script tự chặn nếu đã có `users` (xem `DEPLOYMENT.md`/`docs/kien-truc-cot-loi/core-architecture.md` mục 3.45).
- [ ] Bước 3-5 chạy xong, kiểm tra bằng 2 URL thật (`https://staging-cms.example.com/`, `https://staging-restaurant.example.com/`) hiển thị đúng nội dung tương ứng.
- [ ] Đổi mật khẩu Admin mặc định trên Staging thật (không dùng mật khẩu ví dụ ở trên cho môi trường thật lâu dài).

## 4. Permissions & Environment

- [ ] Phân quyền ghi cho user chạy PHP-FPM (`www-data` hoặc tương đương):
  ```bash
  chmod -R 775 storage/app/media storage/cache storage/logs storage/framework
  chown -R www-data:www-data storage/app/media storage/cache storage/logs storage/framework
  ```
  *(Không cần xử lý `public/uploads/` — thư mục này không được bất kỳ Controller nào sử dụng, chỉ là tàn dư `.gitignore` cũ, xem Architecture Analysis Phase 8. Media thật lưu tại `storage/app/media/`.)*
- [ ] `.env` trên Staging — rà theo đúng bảng đối chiếu `DEPLOYMENT.md` mục 4: `APP_ENV=production` (hoặc `staging` nếu muốn phân biệt riêng — chưa có logic phân nhánh theo giá trị này, chỉ ảnh hưởng hiển thị), `APP_DEBUG=false`, `APP_KEY` đã sinh giá trị ngẫu nhiên, `DB_*` là thông tin thật (user riêng, không dùng `root`), `SESSION_SECURE=true` (sau khi có HTTPS ở mục 2).
- [ ] `.env` **không commit** — xác nhận `.gitignore` vẫn chặn đúng (`.env`, `.env.*`, trừ `.env.example`).

## 5. Kiểm tra cuối trước khi mời khách hàng Demo

- [ ] Đăng nhập Admin thành công trên cả 2 domain Staging qua HTTPS (không có cảnh báo chứng chỉ trên trình duyệt).
- [ ] `https://staging-cms.example.com/sitemap.xml` và `/robots.txt` trả về đúng nội dung XML/text (không lỗi 404/500).
- [ ] Chạy thử toàn bộ 4 bước trong `DEMO_WALKTHROUGH.md` **trước** khi demo thật cho khách hàng ít nhất 1 lần trên chính Staging (không chỉ local).
