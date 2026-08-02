# CMS Đa Website — Multi-Tenant CMS Platform

**Phiên bản**: `v0.1.1` (Production Ready) | **Kiểm thử**: 656/656 PHPUnit PASS

CMS đa website (multi-tenant, SaaS-ready) — core tự viết hoàn toàn bằng PHP 8.2/8.3, **không dùng framework nền** (Laravel/Symfony...). Mỗi website khách hàng (tenant) vận hành độc lập trên cùng 1 hạ tầng, cách ly dữ liệu tuyệt đối qua domain riêng.

## Tính năng chính

- **Multi-tenancy thật theo Domain** — không subdomain giả lập, mỗi tenant 1 domain độc lập.
- **RBAC** — phân quyền hạt mịn theo từng hành động (`.view/.create/.update/.delete/.publish`).
- **4 Module nội dung**: Page (Rich Text qua Quill.js + Visual Page Builder kéo-thả block), Media, Menu (kéo-thả AJAX), SEO Meta (Open Graph/Schema.org).
- **Admin UI đầy đủ**: Dashboard, User/Role, Pages/Media/Menu/SEO Management, Global Settings.
- **Public Engine**: Landing Page B2B, Breadcrumb, Search nội bộ, Sitemap.xml/Robots.txt tự sinh.
- **CI/CD**: GitHub Actions, PHP 8.2 + 8.3.

## Bắt đầu nhanh

Xem hướng dẫn đầy đủ tại **[SETUP_LOCAL.md](SETUP_LOCAL.md)** (demo local) hoặc **[HANDOFF_NOTES.md](HANDOFF_NOTES.md)** (tóm tắt 3 lệnh cho Developer mới).

```bash
composer install
vendor/bin/phpunit    # xac nhan 656/656 PASS truoc khi bat dau
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

Toàn bộ 656 test chạy trên SQLite in-memory (không phụ thuộc MySQL thật) — 4 test skip có điều kiện khi môi trường không có `ext-redis`.

## Giấy phép

Proprietary — xem `composer.json`.
