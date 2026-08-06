# DEPLOYMENT — CMS Đa Website (Production)

> Tài liệu này dành cho **triển khai Production thật**. Nếu chỉ cần chạy demo trên máy cá nhân, xem `SETUP_LOCAL.md` (dùng PHP built-in server, không cần bảo mật/web server thật).
>
> **Giới hạn đã biết**: CI (`.github/workflows/phpunit.yml`) chạy toàn bộ test suite trên **SQLite in-memory**, không dùng MySQL/MariaDB thật. CI xanh xác nhận logic đúng nhưng **không** thay thế việc kiểm thử thủ công trên MySQL/MariaDB thật trước khi migrate production — dự án từng có bug chỉ xuất hiện trên MySQL (DDL implicit-commit, xem mục 6).

## 1. Yêu cầu hệ thống

- PHP **8.2** hoặc **8.3** (2 phiên bản duy nhất đã xác nhận chạy thật — không dùng PHP 8.1 dù `composer.json` cho phép `>=8.1`, chưa từng được kiểm chứng).
- Extension bắt buộc: `pdo_mysql`, `mbstring`.
- MySQL 5.7+/MariaDB 10.4+ đang chạy.
- Composer 2.x.
- Web server: Nginx (khuyến nghị) hoặc Apache với `mod_rewrite`.

## 2. Cấu hình Web Server — Single Domain Multi-tenancy

Toàn bộ request đi qua **1 front controller duy nhất** (`public/index.php`) — không có router riêng theo tenant ở tầng web server, việc resolve tenant xảy ra hoàn toàn ở tầng ứng dụng (`TenantResolverMiddleware`, xem mục 3).

**Nginx** (mẫu tối giản):

```nginx
server {
    listen 80;
    server_name _;                      # catch-all — moi domain tenant deu vao day
    root /path/to/cms/public;
    index index.php;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;                       # chan truy cap .env, .git, v.v.
    }
}
```

**Apache**: cần `public/.htaccess` (rewrite mọi request không khớp file/thư mục thật về `index.php`) — **file này chưa tồn tại trong repo** (dự án local chỉ dùng `php -S`, không cần rewrite rule). Cần tạo trước khi deploy Apache:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
```

## 3. Multi-tenancy DNS/Domain

`TenantResolverMiddleware` resolve tenant bằng cách tra cứu **`site_domains.domain` khớp chính xác với `Host` header** (không phải wildcard subdomain pattern-matching). Vì vậy:

- Mỗi domain của mỗi tenant (`site_domains.domain`) trỏ DNS (A record/CNAME) về **cùng 1 server**.
- Web server chỉ cần **1 `server_name` catch-all** (`server_name _;` ở Nginx) — không cần virtual host/server block riêng cho từng tenant.
- Domain không khớp bản ghi nào trong `site_domains` → `404` (fail-closed, không fallback tenant mặc định — đã xác nhận qua `TenantResolverMiddleware`).
- Site có `status` khác `active` (`maintenance`/`suspended`) → chặn ngay (`503`/`403` tương ứng), không phục vụ nội dung.

## 4. Environment Variables

Copy `.env.example` → `.env`, **các giá trị sau bắt buộc đổi khi lên production** (khác mặc định demo local):

| Biến | Mặc định demo (`.env.example`) | Bắt buộc ở Production | Lý do |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | |
| `APP_DEBUG` | `true` | **`false`** | `true` sẽ lộ stack trace/thông tin hệ thống ra response lỗi |
| `APP_URL` | `http://cms.test` | URL thật (domain chính) | |
| `APP_KEY` | rỗng | **Phải sinh giá trị ngẫu nhiên** | Rỗng là rủi ro bảo mật |
| `DB_*` | MySQL local mặc định (`root`, không mật khẩu) | Thông tin DB thật, **user riêng không phải `root`, có mật khẩu mạnh** | |
| `SESSION_SECURE` | `false` | **`true`** (nếu chạy HTTPS — bắt buộc thực tế) | `false` cho phép cookie session gửi qua HTTP không mã hóa |
| `SYSTEM_ADMIN_DOMAINS` | rỗng | Theo nhu cầu thật | Chưa có Controller nào đọc biến này (tính năng System Admin domain bypass — xem Technical Debt, chưa triển khai) |

`.env` **không được commit** (đã có trong `.gitignore`).

## 5. File Storage Permissions

- Upload Media lưu tại `storage/app/media/{tenant_id}/{tên_file_duy_nhất}` (đã nằm **ngoài** `public/` — đúng chuẩn, không thể truy cập trực tiếp qua URL, chỉ phục vụ qua `MediaServeController` ở `/media/{filename}`).
- Thư mục `storage/app/media/` phải **tồn tại sẵn và ghi được** (`chmod 775`, owner/group khớp user chạy PHP-FPM, ví dụ `www-data`) **trước lần upload đầu tiên** — `UploadMediaController` tự `mkdir()` thư mục con theo `{tenant_id}` nhưng thư mục cha phải writable trước.
- **Đã cập nhật `.gitignore`** trong Phase 6 để bỏ qua nội dung upload thật (`/storage/app/media/*`, giữ `.gitkeep`) — trước đó thiếu rule này, có rủi ro commit nhầm file upload thật vào repo.
- `storage/cache/`, `storage/logs/`, `storage/framework/` cũng cần quyền ghi tương tự (đã có `.gitkeep`/gitignore từ trước).

## 6. Database Migration an toàn

```bash
php bin/migrate.php status     # 1. Xem migration nao da/chua chay
# 2. BACKUP database that truoc khi migrate (mysqldump hoac tuong duong)
php bin/migrate.php migrate    # 3. Chay migration
php bin/migrate.php status     # 4. Xac nhan lai trang thai
```

**Lưu ý quan trọng — đã từng có bug thật**: MySQL/MariaDB tự động **implicit-commit** khi chạy DDL (`CREATE TABLE`/`ALTER TABLE`) giữa 1 transaction đang mở — từng phá vỡ `MigrationManager` (đã fix qua `runInTransactionIfSupported()`, driver-aware: chỉ bọc transaction thật trên SQLite, MySQL/MariaDB chạy DDL không transaction). Dù đã fix, **luôn chạy migration ở giờ thấp điểm và backup trước mỗi lần** — CI chạy trên SQLite nên **không thể** phát hiện lại lớp bug này nếu tái diễn dưới hình thức khác.

## 7. Cronjobs

**Dự án hiện chưa có bất kỳ tác vụ định kỳ (cronjob/scheduled task) nào cần thiết lập.** Không Queue, không Event Dispatcher, không Cache TTL cần dọn định kỳ (`core/Cache.php` là dead capability, chưa Module nào dùng — xem `docs/kien-truc-cot-loi/core-architecture.md`). Mục này sẽ được bổ sung khi có tính năng thật sự cần chạy định kỳ (ví dụ: dọn Session hết hạn, gửi email hàng loạt).

## 8. Checklist Go-Live

- [ ] PHP 8.2 hoặc 8.3 + `pdo_mysql`, `mbstring` đã cài
- [ ] Web server (Nginx/Apache) trỏ đúng `public/` làm document root, chặn truy cập `.env`/`.git`
- [ ] (Apache) đã tạo `public/.htaccess` theo mục 2
- [ ] Tất cả domain tenant đã trỏ DNS về đúng server, đã có bản ghi tương ứng trong `site_domains`
- [ ] `.env` production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` đã sinh, `DB_*` là thông tin thật (không dùng `root`), `SESSION_SECURE=true` nếu chạy HTTPS
- [ ] `storage/app/media/`, `storage/cache/`, `storage/logs/`, `storage/framework/` đã có quyền ghi cho user PHP-FPM
- [ ] Database đã backup trước khi `php bin/migrate.php migrate`
- [ ] `php bin/migrate.php status` xác nhận toàn bộ migration đã áp dụng đúng
- [ ] Đã tạo Site + Admin User đầu tiên qua `php bin/bootstrap.php` (chỉ chạy 1 lần)
- [ ] Test đăng nhập Admin + xem 1 trang Public thật trước khi công bố go-live
