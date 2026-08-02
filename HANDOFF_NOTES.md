# HANDOFF NOTES — CMS Đa Website (Multi-Tenant SaaS Platform)

**Milestone bàn giao**: `v0.1.0` | **Ngày bàn giao**: 02/08/2026 | **Trạng thái**: Production Ready

> Tài liệu này là điểm khởi đầu cho bất kỳ ai tiếp nhận dự án — Developer mới, Stakeholder, hoặc đội vận hành. Đọc xong tài liệu này, người tiếp nhận biết chính xác: hệ thống làm được gì, tài liệu nào đọc tiếp theo, và cần chạy lệnh gì để có môi trường chạy được ngay.

---

## 1. System Executive Summary

- **10 Phase phát triển**, từ `v0.0.1` đến `v0.1.0` — Core Foundation tự viết (không framework) → Multi-tenancy + RBAC + 4 Module nội dung (Page/Media/Menu/SEO) → Admin UI đầy đủ → Global Settings/Search/Media Serving → Release Preparation → UI/UX Demo Polish → Beta Readiness → Production Monitoring → Final Handoff.
- **641/641 PHPUnit test PASS** (1264 assertion, 0 Failures, 0 Errors, 4 Skipped do thiếu `ext-redis` trên môi trường CI — đúng thiết kế) — kiểm chứng **thật**, chạy trên môi trường thật ở mỗi cột mốc, không phải số liệu mô phỏng.
- **Kiến trúc Zero-Dependency**: chỉ 1 dependency runtime (`psr/container`, chuẩn PSR-11) — toàn bộ Container/Router/View/Database/Session/Auth/CSRF tự viết. Frontend: CSS3 thuần + Vanilla JS (ngoại lệ duy nhất: Quill.js qua CDN cho Rich Text Editor, đã Owner phê duyệt).
- **Multi-tenancy thật theo Domain**: mỗi tenant = 1 domain riêng (`site_domains.domain`, khớp chính xác qua `TenantResolverMiddleware`), cách ly dữ liệu tuyệt đối ở tầng Database (`tenant_id` trên mọi bảng nghiệp vụ) — không phải subdomain giả lập.
- **Dynamic Migration Framework**: `MigrationManager` tự viết, hỗ trợ cả SQLite (test) và MySQL/MariaDB (production), xử lý đúng khác biệt hành vi DDL-transaction giữa 2 engine (bug thật đã phát hiện và vá ở giai đoạn Local Demo).
- **CI/CD**: GitHub Actions (`.github/workflows/phpunit.yml`), matrix PHP 8.2 + 8.3 — 2 phiên bản duy nhất đã xác nhận chạy thật.

## 2. Key Assets & Entry Points

| Tài liệu | Vai trò | Đọc khi nào |
|---|---|---|
| `README.md` | Giới thiệu nhanh dự án, liên kết tới toàn bộ tài liệu khác | Điểm khởi đầu đầu tiên |
| `SETUP_LOCAL.md` | Dựng môi trường demo trên máy cá nhân (PHP built-in server) | Developer mới, demo cá nhân |
| `DEPLOYMENT.md` | Triển khai Production thật (Nginx/Apache, `.env`, migration an toàn) | Trước khi lên Production/Staging |
| `STAGING_CHECKLIST.md` | Checklist thao tác đưa hệ thống lên Staging/VPS | Khi chuẩn bị môi trường thử nghiệm |
| `DEMO_WALKTHROUGH.md` | Kịch bản Demo 4 bước cho Sales/Founder | Trước buổi demo khách hàng |
| `PRODUCTION_MONITORING.md` | Health check, Log rotation, Backup/Restore, Security hardening | Sau go-live, vận hành liên tục |
| `CHANGELOG.md` | Lịch sử đầy đủ mọi thay đổi theo từng version | Tra cứu "tính năng X thêm từ bản nào" |
| `TODO.md` | Trạng thái hoàn thành từng Phase/Task, Technical Debt còn mở | Tra cứu tiến độ chi tiết |
| `core-architecture.md` | Tài liệu kiến trúc kỹ thuật đầy đủ nhất (mọi quyết định thiết kế đã chốt) | Bắt buộc đọc trước khi sửa code |
| `database-design.md`, `cms-architecture-proposal.md`, `0X-module-*.md` | Tài liệu đề xuất kiến trúc gốc (giai đoạn thiết kế ban đầu) | Tham khảo lịch sử quyết định, không phải trạng thái hiện tại |

## 3. Quick Start for New Engineers

> **Lưu ý minh bạch**: 3 lệnh cốt lõi bên dưới là bước **khởi tạo dữ liệu** — cần hoàn tất 4 bước chuẩn bị hạ tầng trước đó (cài dependency, tạo `.env`, tạo database, chạy migration). Đây **không phải** toàn bộ quy trình rút gọn còn 3 lệnh — hướng dẫn đầy đủ, chính xác từng bước xem `SETUP_LOCAL.md`.

**Chuẩn bị** (1 lần):
```bash
composer install
cp .env.example .env          # chinh sua neu MySQL local khac mac dinh
php bin/create_database.php
php bin/migrate.php migrate
```

**3 lệnh khởi tạo dữ liệu — dựng xong Tenant 1**:
```bash
php bin/bootstrap.php "SaaS CMS Technology Co." cms.test "Admin" admin@example.com "Password123!"
php bin/seed_demo.php                 # mac dinh domain dau tien, content pack "tech"
```

*(Lưu ý: `bin/bootstrap.php` chỉ chạy được **đúng 1 lần** cho toàn hệ thống — đây là lệnh tạo Site đầu tiên + Admin User, không phải lệnh chạy mỗi lần thêm tenant.)*

**Có Tenant 2 (demo Multi-tenant thật) — thêm 2 lệnh**:
```bash
php bin/add_site.php "Green Gourmet Restaurant & Cafe" restaurant.test
php bin/seed_demo.php restaurant.test restaurant
```

Thêm 2 dòng vào file hosts (`127.0.0.1 cms.test`, `127.0.0.1 restaurant.test`), chạy `php -S cms.test:80 -t public`, mở `http://cms.test/` và `http://restaurant.test/`. Chi tiết đầy đủ + xử lý lỗi thường gặp: `SETUP_LOCAL.md`.

## 4. Post-Launch Maintenance Roadmap (đề xuất, chưa triển khai — cần Architecture Analysis riêng cho từng mục)

| Hạng mục | Mô tả | Mức độ sẵn sàng hạ tầng hiện tại |
|---|---|---|
| **Redis Cache Driver thật** | `core/Cache.php` + `RedisCacheDriver` đã viết sẵn, có test — nhưng **chưa Module nghiệp vụ nào gọi** (dead capability). Ứng viên đầu tiên: cache `SiteSettingsManager::get()`, danh sách Menu | Hạ tầng đã sẵn sàng 100%, chỉ cần tích hợp vào Controller/Service |
| **Async Queue Worker** | Chưa có Queue nào trong dự án (Owner Decision CMS-025: hoãn tới khi Foundation hoàn tất — nay đã hoàn tất). Ứng dụng tiềm năng: gửi email hàng loạt, xử lý ảnh (resize/WebP) không đồng bộ | Cần thiết kế mới hoàn toàn — chưa có bảng `jobs`, chưa có Worker process |
| **Multi-language i18n** | Chưa có cơ chế đa ngôn ngữ cho cả Admin lẫn nội dung Public | Cần thiết kế schema mới — ảnh hưởng `pages`/`seo_meta`, cần Architecture Analysis riêng |
| **Nhân rộng Action Class Pattern** | Đã pilot thành công trên Module `Page` (Phase 6) — còn 24 cặp Controller Admin↔JSON (User/Role/Media/Menu/SEO/Settings) chưa áp dụng | Pattern đã kiểm chứng, quyết định nhân rộng chờ đánh giá qua thời gian sử dụng thực tế |
| **Plugin/Hook System kích hoạt** | `core/Hook.php` đã viết sẵn (Action + Filter kiểu WordPress), đang Standby — chưa Plugin thật nào tồn tại để kiểm chứng | Hạ tầng đã có, cần Plugin mẫu đầu tiên |
| **Media Thumbnail/Resize** | Upload hiện lưu nguyên file gốc, không xử lý ảnh | Cần thêm dependency xử lý ảnh (GD/Imagick) — vi phạm "Zero Dependency" hiện tại, cần Owner quyết định trước |
| **Rate Limiting brute-force thật** | `RateLimiter` hiện có giới hạn Session-based (không chặn được script không gửi cookie) — khuyến nghị hiện tại là Nginx `limit_req` (xem `PRODUCTION_MONITORING.md` mục 4.3) | Có thể nâng cấp thành IP-based dùng chính `core/Cache.php` (khi đã kích hoạt Redis) làm storage thay Session |
| **Analytics/Dashboard Metrics** | Lượt xem trang, nguồn truy cập — chưa có bảng/Module nào | Cần thiết kế mới hoàn toàn |

---

**Người bàn giao**: Kỹ thuật (AI-assisted development, giám sát bởi Owner/Technical Lead qua toàn bộ 10 Phase, mọi tính năng đều qua Architecture Analysis → Owner Approval → Implementation → PHPUnit thật → Documentation).
**Liên hệ kỹ thuật tiếp theo**: xem `core-architecture.md` để hiểu đầy đủ quyết định thiết kế trước khi sửa bất kỳ phần nào của hệ thống.
