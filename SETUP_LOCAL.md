# SETUP LOCAL — CMS Đa Website (Demo)

Hướng dẫn chạy project trên máy local để trải nghiệm qua trình duyệt. Kiến trúc dùng **MySQL** (không đổi sang SQLite — SQLite chỉ dùng trong PHPUnit).

## 1. Yêu cầu

- PHP >= 8.1 (đã test PHP 8.3.30) với extension `pdo_mysql`.
- MySQL/MariaDB đang chạy (local: XAMPP/WAMP/Laragon/MySQL service — bất kỳ, không phụ thuộc stack cụ thể).
- Composer.

## 2. Cài dependency

```bash
composer install
```

## 3. Cấu hình `.env`

Đã có sẵn `.env` (copy từ `.env.example`) với giá trị mặc định khớp MySQL local phổ biến (`127.0.0.1:3306`, user `root`, không mật khẩu, database `cms_db`). Nếu MySQL local của bạn khác (user/password/port khác), sửa trực tiếp file `.env`.

`.env` **không được commit** (đã có trong `.gitignore`) — chỉ dùng cho máy local.

## 4. Tạo database

```bash
php bin/create_database.php
```

Script này tạo database (`CREATE DATABASE IF NOT EXISTS`) theo đúng tên trong `.env` (`DB_DATABASE`, mặc định `cms_db`). Nếu bạn đã tự tạo database bằng tay (`CREATE DATABASE cms_db;` qua MySQL client), có thể bỏ qua bước này.

## 5. Chạy migration

```bash
php bin/migrate.php migrate
```

## 6. Khởi tạo Site + Admin User (chỉ chạy được 1 lần)

```bash
php bin/bootstrap.php "CMS Demo" cms.test "Admin" admin@example.com "Password123!"
```

Tham số theo thứ tự: `site_name domain admin_name admin_email admin_password`. **Domain phải là `cms.test`** (khớp URL sẽ mở ở bước 9) — nếu chạy web server ở port khác 80 (bước 9), xem lưu ý ở đó.

## 7. Tạo dữ liệu mẫu (Homepage/About/Contact/Menu)

```bash
php bin/seed_demo.php
```

Tạo: Page "Trang chủ" (`is_homepage=1`, slug `home`), Page "Giới thiệu" (`about`), Page "Liên hệ" (`contact`), Menu "Main Menu" (`location_key=header`) với 3 mục trỏ tới 3 Page trên, và 1 bản ghi SEO mẫu cho Trang chủ. Script idempotent — chạy lại không lỗi, chỉ báo "đã tồn tại, bỏ qua".

## 8. Trỏ domain `cms.test` về máy local

Thêm dòng sau vào file hosts:

- **Windows**: `C:\Windows\System32\drivers\etc\hosts` (cần mở Notepad với quyền Administrator)
- **macOS/Linux**: `/etc/hosts` (cần `sudo`)

```
127.0.0.1   cms.test
```

## 9. Chạy web server

```bash
php -S cms.test:80 -t public
```

Sau đó mở `http://cms.test/`.

**Nếu port 80 không dùng được** (đã bị chiếm bởi IIS/Skype/service khác): dùng port khác, ví dụ:

```bash
php -S cms.test:8080 -t public
```

**Lưu ý quan trọng**: `TenantResolverMiddleware` so khớp domain theo đúng giá trị `Host` header của trình duyệt gửi lên — nếu dùng port khác 80, trình duyệt sẽ gửi `Host: cms.test:8080` (**có kèm port**), khác với `cms.test` đã lưu ở bước 6 → sẽ trả `404`. Nếu bắt buộc dùng port khác 80, chạy lại bước 6 với domain `cms.test:8080` (bao gồm port) thay vì `cms.test`.

## 10. Truy cập

| URL | Nội dung |
|---|---|
| `http://cms.test/` | Public Homepage |
| `http://cms.test/about` | Page "Giới thiệu" |
| `http://cms.test/contact` | Page "Liên hệ" |
| `http://cms.test/admin/login` | Đăng nhập Admin |
| `http://cms.test/admin/dashboard` | Dashboard (sau khi đăng nhập) |

**Tài khoản Admin**: email/password đã nhập ở bước 6 (ví dụ `admin@example.com` / `Password123!`).

## 11. Các lệnh hữu ích khác

```bash
php bin/migrate.php status     # xem trang thai migration
php bin/migrate.php rollback   # rollback batch migration gan nhat
vendor/bin/phpunit             # chay toan bo test suite (khong dung DB local, dung SQLite in-memory rieng)
```
