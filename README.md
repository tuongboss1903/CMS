# CMS Đa Website — Multi-Tenant CMS Platform

**Phiên bản**: `v0.2.0` (Production Ready) | **Kiểm thử**: 788/788 PHPUnit tests (100% PASS)

CMS đa website (multi-tenant, SaaS-ready) — core tự viết hoàn toàn bằng PHP 8.2/8.3, **không dùng framework nền** (Laravel/Symfony...). Mỗi website khách hàng (tenant) vận hành độc lập trên cùng 1 hạ tầng, cách ly dữ liệu tuyệt đối qua domain riêng.

## Tính năng chính

- **Multi-tenancy thật theo Domain** — không subdomain giả lập, mỗi tenant 1 domain độc lập.
- **RBAC** — phân quyền hạt mịn theo từng hành động (`.view/.create/.update/.delete/.publish`).
- **4 Module nội dung**: Page (Rich Text qua Quill.js + Visual Page Builder kéo-thả block), Media, Menu (kéo-thả AJAX), SEO Meta (Open Graph/Schema.org).
- **Admin UI đầy đủ**: Dashboard (kèm Analytics — Total Views/Unique Visitors/Top Pages/biểu đồ SVG), User/Role, Pages/Media/Menu/SEO Management, Global Settings.
- **Public Engine**: Landing Page B2B, Breadcrumb, Search nội bộ, Sitemap.xml/Robots.txt tự sinh.
- **Đa ngôn ngữ (i18n)**: bản dịch Page theo locale (`vi`/`en`) qua bảng `page_translations`, tự động fallback ngôn ngữ gốc khi thiếu bản dịch, route công khai `/{locale}/...`, dịch UI tĩnh qua `__()`.
- **Comment/Review**: khách để lại bình luận trên Page công khai (không cần đăng nhập), Admin duyệt trước khi hiển thị (moderation-first), chống spam qua Rate Limiting.
- **Notification & Email**: báo Admin khi có comment mới (in-app + email), báo khách khi comment được duyệt/từ chối — Mailer tự viết (driver `log`/`smtp`, không thư viện ngoài), silent-fail tuyệt đối.
- **Audit Log**: truy vết hành động Admin (đăng nhập, CRUD Page, duyệt Comment, đổi Settings) kèm dữ liệu trước/sau thay đổi, IP, thời gian — xem/lọc tại `/admin/audit-logs`.
- **System Settings**: cấu hình key-value linh hoạt theo nhóm (`/admin/system-settings`), có cache và mã hoá cho giá trị nhạy cảm (SMTP password, API key...).
- **Admin UI & Theme Engine**: giao diện quản trị chuẩn hoá toàn bộ qua 5 Partial View dùng chung (Breadcrumb/Pagination/Table Filter/Flash Message/Confirm Modal), Dark/Light Theme (`[data-theme]`, chuyển đổi tức thời, nhớ lựa chọn qua `localStorage`, không FOUC), Modal xác nhận thay `window.confirm()` thô, Roles/Permissions dạng bảng Matrix, Media Manager Grid/List — toàn bộ thuần Vanilla CSS/JS, **không Tailwind/AlpineJS/npm build step**.
- **CI/CD**: GitHub Actions, PHP 8.2 + 8.3.

## Bắt đầu nhanh

Xem hướng dẫn đầy đủ tại **[SETUP_LOCAL.md](SETUP_LOCAL.md)** (demo local) hoặc **[HANDOFF_NOTES.md](HANDOFF_NOTES.md)** (tóm tắt 3 lệnh cho Developer mới).

```bash
composer install
vendor/bin/phpunit    # xac nhan 788/788 PASS truoc khi bat dau
```

## Tài liệu dự án

| Tài liệu | Mục đích |
|---|---|
| [HANDOFF_NOTES.md](HANDOFF_NOTES.md) | Tóm tắt bàn giao hệ thống, Quick Start, Roadmap bảo trì |
| [SETUP_LOCAL.md](SETUP_LOCAL.md) | Dựng môi trường demo local (2 tenant) |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Triển khai Production (Web Server, `.env`, Migration an toàn) |
| [STAGING_CHECKLIST.md](STAGING_CHECKLIST.md) | Checklist triển khai Staging/VPS |
| [DEMO_WALKTHROUGH.md](DEMO_WALKTHROUGH.md) | Kịch bản Demo khách hàng doanh nghiệp |
| [PRODUCTION_MONITORING.md](PRODUCTION_MONITORING.md) | Health Check, Log Rotation, Backup, Security Hardening |
| [core-architecture.md](core-architecture.md) | Tài liệu kiến trúc kỹ thuật đầy đủ — **đọc trước khi sửa code** |
| [CHANGELOG.md](CHANGELOG.md) | Lịch sử thay đổi theo từng version |
| [TODO.md](TODO.md) | Trạng thái tiến độ chi tiết từng Phase/Task |

## Kiểm thử

```bash
vendor/bin/phpunit
```

Toàn bộ 788 test chạy trên SQLite in-memory (không phụ thuộc MySQL thật) — 4 test skip có điều kiện khi môi trường không có `ext-redis`.

## Giấy phép

Proprietary — xem `composer.json`.
