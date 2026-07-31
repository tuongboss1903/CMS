# TODO — CMS Đa Website

> Theo dõi tiến độ theo Phase/Task. Task ID dạng `CMS-XXX`, đánh số tuần tự theo thứ tự triển khai thực tế (không nhảy số).

## Phase 1 — Core Framework Foundation

Mục tiêu: dựng khung core tự viết (không framework nền), đủ để boot 1 request qua Router → Middleware → View/JSON. Chi tiết xem `cms-architecture-proposal.md` mục 2 và phần "QUYẾT ĐỊNH CHÍNH THỨC".

- [x] **CMS-001** — Khởi tạo project skeleton
  - [x] Tạo cấu trúc thư mục đầy đủ (`app/`, `core/`, `modules/`, `plugins/`, `themes/`, `public/`, `storage/`, `resources/`, `database/`, `config/`, `docs/`)
  - [x] `.gitkeep` cho các thư mục rỗng
  - [x] `composer.json` với PSR-4 autoload (`Core\`, `App\`, `Modules\`)
  - [x] `.gitignore`
  - [ ] `git init` + commit đầu tiên — **chưa chạy được**: môi trường hiện tại không có `git` trên PATH. Cần tự chạy thủ công: `git init`, `git add -A`, `git commit -m "chore: init project skeleton"`.
  - [ ] `composer install` / `composer dump-autoload` — **chưa chạy được**: môi trường hiện tại không có `composer`/`php` trên PATH. Cần tự chạy thủ công để sinh `vendor/autoload.php`.
- [ ] **CMS-002** — Config loader + file cấu hình (`config/app.php`, `database.php`, `cache.php`, `auth.php`, `tenants.php`) + `core/Config.php`
- [ ] **CMS-003** — `core/Container.php` (DI Container: bind/singleton/resolve, auto-wiring qua Reflection)
- [ ] **CMS-004** — `core/Database.php` + `core/QueryBuilder.php` (PDO wrapper, transaction API `DB::transaction()`)
- [ ] **CMS-005** — `core/View.php` (template engine PHP thuần, layout inheritance, escape mặc định)
- [ ] **CMS-006** — `core/Session.php`
- [ ] **CMS-007** — `core/Cache.php` + `core/Cache/CacheDriver.php` (interface) + `FileCacheDriver` + `RedisCacheDriver`
- [ ] **CMS-008** — `core/Hook.php` (Event Dispatcher lõi + Action/Filter)
- [ ] **CMS-009** — Middleware: interface + pipeline + stub `TenantResolverMiddleware`
- [ ] **CMS-010** — `core/Router.php` (route matching, group, middleware pipeline)
- [ ] **CMS-011** — `public/index.php` (entry point, bootstrap Container, đăng ký binding, chạy Router)
- [ ] **CMS-012** — Exception/Error Handler cơ bản (log ra `storage/logs`)
- [ ] **CMS-013** — Route kiểm thử tạm `/health` (JSON chuẩn `{success, data, message, errors}`) — xác minh pipeline end-to-end, sẽ gỡ bỏ khi có module thật

## Phase 2 — Database Migration (Tenant/Auth/User)

Chưa bắt đầu. Xem `database-design.md` mục 2.

## Phase 3 — Module Auth / User / Role

Chưa bắt đầu.

## Phase 4+ — Theme Engine, Page, Post, Product, Media, Menu, Form, SEO, Settings, Plugin

Chưa bắt đầu. Thứ tự tham khảo `00-master-spec.md`.
