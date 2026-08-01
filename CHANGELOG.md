# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/). Version nội bộ (chưa phát hành ra ngoài) đánh số theo Task hoàn thành: `CMS-00X` → `v0.0.X` — dùng làm mốc tiến độ Phase 1, không phải semver phát hành sản phẩm.

## [Unreleased]

Chưa có mục nào — chờ Roadmap Review xác định CMS tiếp theo.

## [0.0.36] — CMS-036: Site Status Policy

### Added

- `core/Middleware/TenantResolverMiddleware.php` — sau khi resolve domain→site thành công, chặn request nếu `sites.status !== 'active'` (fail-closed tuyệt đối, dùng thẳng `$site['status']` đã có sẵn từ `SELECT sites.*`, không thêm query SQL). `maintenance` → HTTP 503 + `"Site is under maintenance."`; `suspended` → HTTP 403 + `"Site has been suspended."`; mọi giá trị khác (NULL/rỗng/lạ/tương lai) → HTTP 403 + `"Site is not available."`. Không gọi `TenantManager::setCurrent()`, không gọi `$next()` khi bị chặn. Response giữ nguyên envelope `{success, data, message, errors}`.
- `tests/Core/Middleware/TenantResolverMiddlewareTest.php` (+4 test): `testAllowsActiveSiteExplicitly`, `testBlocksMaintenanceSiteWith503`, `testBlocksSuspendedSiteWith403`, `testBlocksUnknownStatusValue`. `seedSite()` mở rộng thêm tham số `$status` (default `'active'`, additive).

### Design Decisions

- **Fail-closed tuyệt đối** — chỉ đúng `status === 'active'` mới được đi tiếp, không dùng `in_array()`/whitelist — mọi giá trị khác đều chặn, kể cả giá trị status tương lai chưa biết.
- **Message tiết lộ lý do cụ thể** (có chủ đích) — ưu tiên khả năng vận hành/chẩn đoán hơn che giấu sự tồn tại của domain (khác nhánh domain-không-khớp, vẫn giữ 404 generic).
- **Không thêm query SQL** — tái dùng `$site['status']` đã có sẵn từ câu query gốc.
- **Không sửa `TenantManager.php`/`Database.php`/`Application.php`/`Router.php`/`Container.php`** — thay đổi cô lập hoàn toàn trong 1 file.
- **Không tạo middleware/exception/helper/config mới** — logic mới nằm trong 1 `private method` nội bộ của `TenantResolverMiddleware`.

### Testing

- `TenantResolverMiddlewareTest`: 8 tests / 24 assertions.
- Full Suite: 407 tests / 733 assertions.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `TenantResolverMiddlewareTest`: 8 tests/24 assertions; toàn bộ suite: 407 tests, 733 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 4 Skipped (Redis) đúng thiết kế.

## [0.0.35] — CMS-035: Auth Logout Endpoint

### Added

- `modules/Auth/LogoutController.php` — `POST /logout`, dùng `Auth::logout()` đã có (không sửa `Auth.php`). Idempotent — luôn trả 200 dù đã đăng nhập hay chưa.
- `modules/Auth/routes.php` — thêm route `POST /logout`.
- `tests/Core/ModuleAuthIntegrationTest.php` mở rộng (+2 test): `testLogoutClearsAuthenticatedUser`, `testLogoutSucceedsWhenNotLoggedIn`.

### Design Decisions

- **Không sửa Core** — `Application.php`/`ModuleManager.php`/`Router.php`/`Container.php` không đổi.
- **Không sửa `Auth.php`/`Session.php`** — dùng nguyên API `Auth::logout()` đã ổn định từ CMS-021.
- **Không CSRF** — nhất quán quyết định `/login` (CMS-034), chưa có token-issuing flow.
- **Không `AuthMiddleware`** — logout idempotent theo đúng bản chất "không thể thất bại" của `Auth::logout()`, ép 401 khi chưa đăng nhập sẽ mâu thuẫn với thiết kế gốc.
- **JSON API only**, **logout luôn trả 200** — không phân biệt trạng thái đăng nhập trước đó.

### Testing

- `ModuleAuthIntegrationTest`: 7 tests / 26 assertions.
- Full Suite: 403 tests / 718 assertions.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `ModuleAuthIntegrationTest`: 7 tests/26 assertions; toàn bộ suite: 403 tests, 718 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 4 Skipped (Redis) đúng thiết kế.

## [0.0.34] — CMS-034: Module System Bootstrap (Auth Module)

### Added

- `modules/Auth/` — **Module thật đầu tiên của dự án** (`modules/` trước đó hoàn toàn rỗng, chỉ `.gitkeep`). Lần đầu dùng thật PSR-4 `"Modules\\": "modules/"` đã cấu hình từ CMS-001.
- `modules/Auth/module.json` — `key: "auth"`, đúng schema `ModuleDescriptor`.
- `modules/Auth/routes.php` — `POST /login`.
- `modules/Auth/LoginController.php` — `handle(Request): Response`, authentication thông qua `AuthenticationService::attempt()` (CMS-031/033, không sửa). Validate input qua `Validator` (CMS-014). Response thành công trả `{id, email, roles, permissions}` (đọc qua `Auth`/`Authorization`, không API mới); thất bại trả message thống nhất, không phân biệt lý do (sai password/email không tồn tại/rate-limited/inactive).
- `tests/Core/ModuleAuthIntegrationTest.php` (mới, 5 test) — integration test dùng `ModuleManager` trỏ thẳng `modules/` thật, `Router::dispatch()` thật, không mock.

### Design Decisions

- **Không sửa Core** — `Application.php`/`ModuleManager.php`/`Router.php`/`Container.php` không đổi 1 dòng; cơ chế discovery/boot module đã đủ dùng từ CMS-010.
- **Không sửa `AuthenticationService.php`/`Auth.php`/`Authorization.php`** — Module chỉ gọi API public đã có.
- **Không GET `/login`** — chưa có theme/View Admin Panel nào tồn tại, để dành khi UI thật được xây.
- **Không CSRF cho `/login`** (Owner Decision, có chủ đích — không phải thiếu sót) — chưa có endpoint phát hành token trước khi submit; "login CSRF" là lớp rủi ro khác Synchronizer Token Pattern không nhắm tới trực tiếp.
- **JSON API only** — nhất quán 100% với mọi endpoint khác trong dự án (`/health`, 404/405/500, CSRF 419, Auth 401, Authorization 403 đều JSON envelope `{success,data,message,errors}`).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, `composer dump-autoload` PASS): **PASS** — `ModuleAuthIntegrationTest`: 5 tests/16 assertions; toàn bộ suite: 401 tests, 708 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.33] — CMS-033: Authentication Rate Limiting

### Added

Authentication rate limiting:

- `AuthenticationService` tích hợp `RateLimiter` (constructor injection, cùng `Config`).
- Login throttle dùng `config('auth.login_throttle.max_attempts'/'decay_seconds')` — cấu hình đã sẵn sàng từ CMS-023, lần đầu được sử dụng thật.
- Rate-limit key scoped theo email (`login:{lowercase_email}`) — 1 email bị khoá không ảnh hưởng email khác.
- `RateLimiter::clear()` gọi sau khi `password_verify()` thành công — không tính là "hit" khi mật khẩu đúng.
- `tests/Core/AuthenticationServiceTest.php` (+5 test, tổng 15 test): rate-limit cơ bản (chặn khi vượt ngưỡng, thành công dưới ngưỡng, key theo từng email, clear khi verify đúng) + 1 test khoá tường minh hành vi "clear() xảy ra dù tài khoản inactive" (đã được Owner chấp thuận qua Final Verification Phase 4).
- `tests/Fixtures/config/auth.php` — bổ sung key `login_throttle` (thuần additive).

### Design Decisions

- **Không Route** — route `/login` để dành khi Module System (Phase 3) thật bắt đầu, tránh đặt business route vào Core.
- **Không Controller** — cùng lý do trên.
- **Không JWT** — ngoài phạm vi, thuộc `/api/v1/*` theo thiết kế Hybrid Auth gốc.
- **Không Repository** — `AuthenticationService` tiếp tục gọi `Database`/`RateLimiter` trực tiếp, đúng tiền lệ CMS-030.
- **Không tạo Exception mới** — rate-limit trả `bool` (`false`), nhất quán convention `Csrf::verify()`/`RateLimiter::hit()` đã có.
- **Không sửa `Auth.php`** — ranh giới "chỉ session-state" giữ nguyên từ CMS-021.
- **Không sửa `Authorization.php`** — ranh giới "chỉ đọc Session" giữ nguyên từ CMS-022.
- **Không sửa `RateLimiter.php`/`Application.php`/`Router.php`/`Container.php`/Middleware/Database layer** — mở rộng đúng 1 file (`AuthenticationService.php`) + fixture test.
- Thứ tự `hit()`/`clear()` gắn với kết quả `password_verify()`, không gắn với kết quả cuối `attempt()` — tài khoản `inactive` dùng đúng password vẫn `clear()` rate limit (hành vi có chủ đích, đã khoá bằng test riêng, xem Technical Debt).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30): **PASS** — `AuthenticationServiceTest`: 15 tests/28 assertions; toàn bộ suite: 396 tests, 692 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.31] — CMS-031: Auth/Authorization Foundation

### Added

- `core/AuthenticationService.php` — `AuthenticationService Foundation`, method public duy nhất `attempt(string $email, string $password): bool`. Constructor inject `Database`/`Auth`/`Session`/`TenantManager`.
  - **Password verification**: `password_verify()` với hash thật từ `users.password` (query chỉ lấy `id/password/status`, không `SELECT *`).
  - **Dummy verify chống user enumeration**: khi email không tồn tại, dùng `DUMMY_HASH` (hằng số bcrypt cố định, không tương ứng password thật nào) để `password_verify()` vẫn thực thi đúng 1 lần CPU work — email không tồn tại và sai password hội tụ về đúng 1 điểm `return false`, không phân biệt được qua kết quả hay hành vi.
  - **`status` check sau `password_verify()`** (không phải trước) — chống timing side-channel phân biệt tài khoản `locked`/`pending`.
  - **Tenant-aware role/permission loading**: roles/permissions nạp theo `TenantManager::id()` (site hiện tại), 2 query raw SQL riêng qua `user_site_roles`→`roles`→`role_permissions`→`permissions`, ghi vào `Session::set('auth.roles'/'auth.permissions', list<string>)`.
  - Gọi `Auth::login()` hiện có (chỉ `id`/`email`, không `password`) — không sửa `Auth.php`.
  - Guard `TenantManager::check()` — throw `\LogicException` built-in nếu chưa resolve tenant (lỗi tiền điều kiện của caller, không phải lỗi user).
- `tests/Core/AuthenticationServiceTest.php` (mới, 10 test).

### Design decisions

- **Không route/Controller/UI** — chỉ Foundation Service, Module tương lai (Admin/User/API) tự gọi `attempt()` trực tiếp.
- **Không RateLimiter** — `config('auth.login_throttle')` đã sẵn sàng nhưng chưa dùng trong CMS-031, để dành CMS Security riêng.
- **Không JWT, không register/forgot-password/user-management/permission-management UI, không multi-site session** (1 session = 1 site, đổi site cần login lại).
- **Không Repository** — Service gọi `Database::select()`/`selectOne()` trực tiếp (đúng tiền lệ `TenantResolverMiddleware` CMS-030), né giới hạn `QueryBuilder::join()` (Technical Debt #3).
- **Không tạo Exception class mới** — lỗi xác thực trả `bool` (đúng convention `Csrf::verify()`/`RateLimiter::hit()`), chỉ dùng `\LogicException` built-in cho lỗi tiền điều kiện.
- **Không đăng ký Container tường minh trong `Application.php`** — `AuthenticationService` không giữ state, auto-wire đủ dùng; CMS-031 không chạm `Application.php` (khác 3 CMS liên tiếp trước CMS-028/029/030).
- **`Auth.php` tiếp tục không chứa password logic, `Authorization.php` tiếp tục chỉ đọc Session** — cả 2 file không sửa 1 dòng, đúng ranh giới đã chốt từ CMS-021/022.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — `AuthenticationServiceTest`: 10 tests; toàn bộ suite: 391 tests, 676 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.30] — CMS-030: TenantManager Integration

### Added

- `core/Middleware/TenantResolverMiddleware.php` — resolve domain (`Request::getHost()`) → tenant qua `sites JOIN site_domains` (1 câu SQL, `Database::selectOne()`), gọi `TenantManager::setCurrent()` khi khớp. Domain không khớp → 404 JSON envelope (`{success:false,data:null,message:'Not Found',errors:[]}`), không gọi `$next()`, không fallback tenant mặc định.
- `core/Application.php` — đăng ký `TenantManager::class` singleton (bắt buộc để state persist giữa Middleware và `View`/Controller trong cùng 1 request); sửa Closure `View::class` đọc `TenantManager::current()['theme_active'] ?? config('app.theme')`; `boot()` bọc `ModuleManager::boot()` trong `Router::group(['middleware' => [TenantResolverMiddleware::class]], ...)`, `/health` giữ ngoài group.
- `tests/Core/Middleware/TenantResolverMiddlewareTest.php` (mới, 4 test).
- `tests/Core/ApplicationTest.php` (+3 test, +helper `seedTenant()`, +5 test cũ được thêm seed).
- `tests/Fixtures/AppProduction/config/database.php` (mới — fixture thiếu, phát hiện khi viết test).

### Design decisions

- **`TenantResolverMiddleware` chịu trách nhiệm duy nhất Host → tenant resolution** — không Auth, không Permission, không site status, không Super Admin (đúng SRP, tránh lặp lại bài học gộp responsibility từ CMS-022/023).
- **Fail-closed tuyệt đối**: domain không tồn tại trong `site_domains` → 404 ngay tại Middleware, **không fallback tenant mặc định** — loại trừ hoàn toàn rủi ro rò rỉ dữ liệu tenant khác.
- **Vị trí resolve là Middleware, không phải `Application::boot()`** — `boot()` idempotent (chạy 1 lần/instance) và không nhận `Request`, nhét domain resolution vào đó sẽ phá tính idempotent nếu `handle()` được gọi nhiều lần.
- **`/health` và route hệ thống nằm ngoài `TenantResolverMiddleware`** — dùng `Router::group()` (đã có từ CMS-006/018) bọc riêng phần đăng ký route Module, không sửa `Router.php`/`MiddlewarePipeline.php`/`Route.php`, không tạo cơ chế exclude middleware mới.
- **`View` runtime theme đọc từ `TenantManager`** — Closure factory trong `Application` đọc `theme_active`, fallback `config('app.theme')` khi NULL hoặc chưa có tenant — không sửa `View.php`.
- **`TenantManager` đăng ký singleton trong Application wiring** — phát hiện qua trace `Container::get()` (không binding thì không cache, dù auto-wire được) trước khi báo cáo PHPUnit; không sửa `TenantManager.php`.
- **Database query strategy**: 1 câu SQL JOIN qua `Database::selectOne()`, không qua `QueryBuilder::join()` (giới hạn đã ghi nhận ở Technical Debt #3), không tạo Repository/Service mới.
- **Không xử lý trong CMS-030** (Owner Decision, để dành CMS riêng): `system_admin.domains` bypass (đã có sẵn trong `config/tenants.php`, chưa dùng), site `status` (`suspended`/`maintenance`), domain normalization (lowercase/strip port).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30): **PASS** — `TenantResolverMiddlewareTest`: 4 tests/9 assertions; `ApplicationTest`: 16 tests/34 assertions; toàn bộ suite: 381 tests, 664 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.29] — CMS-029: Logger Integration

### Added

- `Application` đăng ký `Logger` singleton trong `registerCoreServices()` (path giữ nguyên `storage/logs/app.log`).
- `Application::logException()` chuyển sang gọi `Logger::log('error', $message, $context)` — context gồm `exception_class/file/line/trace`, thay cho logic `mkdir`/`sprintf`/`file_put_contents` tự viết trước đây.
- `Application::boot()` đăng ký `Hook::onError()` listener — lỗi callback Action/Filter được ghi log qua `Logger` (context `hook/exception_class/file/line`), không đổi cơ chế cô lập callback hiện có của `Hook`.
- `tests/Core/ApplicationTest.php` (+2 test): `testExceptionLogContainsExceptionClassFileLineAndTrace`, `testHookCallbackExceptionIsLoggedViaLogger`.

### Design decisions

- **Không triển khai Database query logging** — `Database::onQueryExecuted()` fire cho MỌI query (không riêng lỗi), chưa có requirement debug/performance thật, tránh log volume tăng không kiểm soát — giữ nguyên là điểm mở chưa dùng, để dành CMS riêng nếu có nhu cầu.
- **Không log `PluginManager::getFailures()`** — khác domain (lifecycle error của Plugin system, không phải runtime Hook callback error), để dành CMS PluginManager observability riêng nếu cần.
- **Không log rotation** — Technical Debt đã ghi nhận từ CMS-024, tiếp tục deferred.
- **Logger giữ Single Responsibility** — không thêm enum/hằng số level mới, không Exception/Adapter/abstraction logging mới. `Logger` là nơi duy nhất format output; `Application` chỉ truyền dữ liệu.
- Lần đầu sửa `core/Application.php` kể từ CMS-019 — thay đổi khoanh vùng rõ (3 điểm đúng Owner Decision), không đổi luồng điều khiển `handle()`/`boot()`.
- Không sửa `core/Logger.php`/`core/Database.php`/`core/Hook.php`/`core/PluginManager.php`/`core/ExceptionHandler.php`.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `ApplicationTest`: 13 tests/31 assertions; toàn bộ suite: 374 tests, 652 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.28] — CMS-028: Database Migration Phase 2 (Tenant/Auth/Role)

### Added

- `database/migrations/2026_08_01_000001_create_sites_table.php` — `sites` (name/status/plan_id nullable không FK/theme_active không FK/storage_used_bytes/timestamps), index `idx_sites_status`, `idx_sites_plan_storage`.
- `database/migrations/2026_08_01_000002_create_site_domains_table.php` — `site_domains` (FK `site_id → sites.id ON DELETE CASCADE`), unique `domain`, index `site_id`.
- `database/migrations/2026_08_01_000003_create_users_table.php` — `users` (email/password/status/timestamps), unique `email` (không theo tenant).
- `database/migrations/2026_08_01_000004_create_roles_table.php` — `roles` (FK `tenant_id → sites.id ON DELETE CASCADE`, nullable = role hệ thống), unique composite `(tenant_id, name)`.
- `database/migrations/2026_08_01_000005_create_permissions_table.php` — `permissions` (`key`/description), unique `key`.
- `database/migrations/2026_08_01_000006_create_role_permissions_table.php` — bảng trung gian N-N, composite PK `(role_id, permission_id)`, FK CASCADE cả 2 chiều.
- `database/migrations/2026_08_01_000007_create_user_site_roles_table.php` — FK `user_id`/`site_id` CASCADE, FK `role_id` RESTRICT, unique `(user_id, site_id)`.
- `tests/Core/RealMigrationsTest.php` (mới, 11 test) — chạy `MigrationManager` trỏ thẳng `database/migrations/` thật (SQLite in-memory, không mock), khác `MigrationManagerTest.php` (dùng fixture riêng để test cơ chế).

### Design decisions

- **SQLite-compatible DDL strategy** (Owner Decision, Phương án A): loại `ENUM`→`VARCHAR` (giá trị hợp lệ validate ở Service layer sau), loại `UNSIGNED`, loại `ON UPDATE CURRENT_TIMESTAMP`→`TIMESTAMP NULL` (Service tự set khi ghi) — giữ nguyên tắc "mọi thay đổi phải PHPUnit-verify thật" xuyên suốt CMS-001→CMS-027, thay vì migration MySQL-only không thể test tự động trong môi trường hiện tại (test suite luôn chạy SQLite).
- **Driver-specific auto-increment handling** — điểm rẽ nhánh driver DUY NHẤT được Owner approve: mỗi migration Closure tự đọc `$db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME)` để chọn `AUTOINCREMENT` (SQLite) hoặc `AUTO_INCREMENT` (MySQL) cho mệnh đề Primary Key — không có cú pháp SQL chung cho cả 2 engine ở điểm này. Không rẽ nhánh thêm ở phần schema khác.
- **Không seed data** — CMS-028 chỉ tạo schema.
- **Không tạo bảng `plans`** — `sites.plan_id` giữ nullable, không FK (YAGNI, chưa có requirement Billing/SaaS thật).
- **Không migration `login_logs`/`password_resets`/`personal_access_tokens`** — thuộc Auth enhancement/security audit layer, chưa cần để mở khoá TenantManager/Auth cơ bản, để dành CMS Auth Module sau.
- **Không business logic trong migration** — thuần DDL, đúng `database-design.md` mục 6 ("không Trigger cho business logic").
- **Không sửa `MigrationManager.php`/`Database.php`** — dùng nguyên hạ tầng đã có từ CMS-013/CMS-004.
- **Technical Debt ghi nhận** (không sửa trong CMS-028): UNIQUE `(tenant_id, name)` ở `roles` không ngăn được trùng tên role hệ thống khi `tenant_id IS NULL` (đúng ANSI SQL semantics — `NULL ≠ NULL` trong composite UNIQUE ở cả SQLite lẫn MySQL, không phải bug migration). Phát hiện qua PHPUnit thật (`testRolesTenantNameUniqueConstraintIsEnforced` FAIL lần đầu, root cause xác nhận là test giả định sai chứ không phải migration lỗi) — test đã sửa lại đúng phạm vi UNIQUE thật sự enforce (`tenant_id` không NULL). Xử lý trùng tên role hệ thống để dành CMS Role/Auth Service sau (Service layer, không Trigger — nhất quán ràng buộc homepage ở `database-design.md` mục 6.1).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `RealMigrationsTest`: 11 tests/51 assertions; toàn bộ suite: 372 tests, 648 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.27] — CMS-027: Middleware Parameterization

### Added

- `core/Middleware/MiddlewarePipeline.php` — thêm `MiddlewareInterface` instance support: pipeline chấp nhận `list<class-string<MiddlewareInterface>|MiddlewareInterface>`. Thêm `private resolve(mixed $middlewareEntry): MiddlewareInterface` — string resolve qua `Container::get()` (hành vi cũ, không đổi), `MiddlewareInterface` instance dùng trực tiếp (khả năng mới), giá trị khác throw `\InvalidArgumentException` với message chứa `get_debug_type()`.
- `tests/Core/Middleware/MiddlewarePipelineTest.php` (mới) — 5 test case, unit test riêng đầu tiên cho `MiddlewarePipeline` (trước đây chỉ được kiểm chứng gián tiếp qua `RouterTest.php`).

### Changed

- PHPDoc-only (không đổi runtime): `core/Route.php` (constructor, `getMiddleware()`), `core/Router.php` (`middleware()/get()/post()/put()/patch()/delete()/group()/addRoute()` và 2 property `$groupMiddleware`/`$globalMiddleware`) — cập nhật kiểu `list<class-string>` → `list<class-string<MiddlewareInterface>|MiddlewareInterface>`.

### Design decisions

- Backward compatible tuyệt đối — class-string middleware hiện có (`CsrfMiddleware`/`AuthMiddleware`/`AuthorizationMiddleware`/`RateLimitMiddleware` và mọi fixture test) đi qua đúng nhánh `is_string()`, hành vi giống hệt code cũ.
- Phạm vi CMS-027 CHỈ là infrastructure của `MiddlewarePipeline` — không redesign `AuthorizationMiddleware`/`RateLimitMiddleware`, không thêm permission parameter/rate limit configuration, không đổi security policy (Owner Decision).
- Không tạo Exception class mới cho invalid middleware — dùng `\InvalidArgumentException` built-in (tránh abstraction thừa cho lỗi invalid developer input).
- Tham số closure trong `handle()` đổi `string` → `mixed` có chủ đích: giữ type-check bên trong `resolve()` để trả message lỗi rõ ràng, tránh PHP tự ném `TypeError` mù mờ trước khi `resolve()` kịp chạy.
- Không tạo abstraction mới (`MiddlewareResolver`/`MiddlewareFactory`/`MiddlewareDefinition`/`ParameterBag`) — `resolve()` là private method nội bộ.
- Không breaking change — `Container.php` không sửa, `Router`/`Route` runtime không đổi (chỉ PHPDoc).

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 361 tests, 597 assertions, PASS.

## [0.0.26] — CMS-026: ThemeManager

### Added

- `core/ThemeManager.php` — discovery-based theme management, manifest-driven (`theme.json`, glob `{themesPath}/*/theme.json`). API: `discover(): array<string, ThemeDescriptor>`, `find(string $key): ?ThemeDescriptor`. **Không memoize** — mỗi `discover()` đọc lại filesystem độc lập (filesystem là source of truth, theme có thể đổi runtime).
- `core/Theme/ThemeDescriptor.php` — value object readonly: `key/name/version/screenshot/path`.
- `core/Theme/ThemeException.php` — `cannotRead()/invalidManifest()`, mirror `ModuleException`.
- `tests/Core/ThemeManagerTest.php` (7 test) + fixture `tests/Fixtures/Themes/{Alpha,Beta}`, `tests/Fixtures/ThemesInvalid/BadTheme`.

### Design decisions

- Filesystem là source of truth cho theme — không chỉ giới hạn tạm thời (khác `Auth`/`TenantManager`, vốn thiếu migration DB), mà là nguyên tắc thiết kế vĩnh viễn (đã xác nhận qua `database-design.md`: bảng `themes` chỉ đồng bộ TỪ filesystem, không phải nguồn gốc) — **`ThemeManager` không dùng Database, kể cả tương lai**.
- Không `active()/setActive()` — "theme nào đang active" là business state (cột `sites.theme_active`), không thuộc discovery layer, đúng nguyên tắc "core trung lập" đã áp dụng cho `ModuleManager`.
- Không boot system, không dependency resolution giữa theme (không có bằng chứng cần).
- Không breaking change — 0 file Core cũ bị sửa (`View`/`Application`/`Container`/`Config`/`TenantManager` giữ nguyên).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `ThemeManagerTest`: 7 tests/15 assertions; toàn bộ suite: 354 tests, 589 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.25] — CMS-025: TenantManager

### Added

- `core/TenantManager.php` — `final class`, **0 dependency** (không constructor, không Session/Database/Request/Config) — mức cô lập cao nhất từ trước tới nay. Giữ state "tenant hiện tại" trong phạm vi 1 request (in-memory thuần, không Session). API: `setCurrent(int|string $tenantId, array $data = []): void`, `check(): bool`, `id(): int|string|null`, `current(): ?array`. Không tự resolve domain→tenant (bảng `sites`/`site_domains` chưa tồn tại — chưa có migration thật), không tự validate/normalize dữ liệu truyền vào.
- `tests/Core/TenantManagerTest.php` (9 test, unit thuần — không Session/filesystem/DB).

### Design decisions

- Đối xứng có chủ đích với `Auth` (CMS-021) — cùng gặp giới hạn "bảng liên quan chưa tồn tại" nên chỉ quản lý state, không tự resolve/query.
- **Khác biệt có chủ đích với `Auth`/`Authorization`/`Csrf`/`RateLimiter`**: KHÔNG dùng Session làm nơi lưu (dù `Session.php` đã dự trù namespace `tenant.current` từ CMS-007) — vì tenant phải xác định cho MỌI request (kể cả API/JWT không dùng cookie), ép `Session::start()` chỉ để biết site nào sẽ vi phạm triết lý lazy-start đã thiết kế cho `Session` từ đầu. Dùng property in-memory thuần — state tự cô lập đúng theo từng request vì `Container` đã là 1-per-request.
- Không nối `View`/`Application` (factory `View::class` vẫn dùng `config('app.theme')` tĩnh như cũ) — Foundation trước, nối dây để dành 1 CMS sau khi có migration `sites`/`site_domains` thật.
- Không breaking change — 0 file Core cũ bị sửa.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `TenantManagerTest`: 9 tests; toàn bộ suite: 347 tests, 574 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.24] — CMS-024: Logger

### Added

- `core/Logger.php` — `final class`, 0 dependency (không Config/Container/HTTP), nhận `string $logPath` qua constructor (đường dẫn file log cụ thể, không phải thư mục). Public API duy nhất: `log(string $level, string $message, array $context = []): void`. Không PSR-3, không level filtering, không channel/formatter/handler/rotation/async/buffering. Format 1 dòng: `[Y-m-d H:i:s] level: message {context json nếu có}`. Tự tạo thư mục cha nếu thiếu (`mkdir(..., true)`), ghi qua `@file_put_contents(..., FILE_APPEND | LOCK_EX)` — không throw khi ghi thất bại (nhất quán `Application::logException()` đã có từ CMS-011).
- `tests/Core/LoggerTest.php` (8 test, filesystem thật/temp dir, không mock).

### Design decisions

- Đề xuất ban đầu "CMS-024 — Cache" bị phát hiện trùng lặp hoàn toàn với `Cache`/`CacheDriver` đã hoàn thành từ CMS-008 (tag `v0.0.8`) — dừng lại, đổi thành Logger (gap thật, đã được nhắc tới nhiều lần từ CMS-011/CMS-019 nhưng chưa triển khai).
- Không dùng `psr/log` (tránh thêm Composer dependency ngoài phạm vi cần thiết) — API tối giản tự thiết kế, đúng YAGNI.
- Constructor nhận `string $logPath` trực tiếp thay vì qua `Config` mới — theo đúng tiền lệ `MigrationManager(string $migrationsPath)`.
- **Không breaking change** — 0 file Core cũ bị sửa. `Application::logException()` tiếp tục hoạt động độc lập, song song, chưa bị thay thế. 2 điểm mở đã dự trù từ trước (`Database::onQueryExecuted()` từ CMS-004, `Hook::onError()` từ CMS-009) vẫn chưa được nối dây — để dành CMS sau khi quyết định tích hợp `Logger` vào luồng chính.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `LoggerTest`: 8 tests/10 assertions; toàn bộ suite: 338 tests, 561 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.23] — CMS-023: Rate Limiter

### Added

- `core/RateLimiter.php` — đếm số lần "hit" theo key trong 1 cửa sổ thời gian (decay), lưu trong Session (namespace `rate_limit.{key}`, giá trị `{attempts:int, expires_at:int}` — chỉ integer timestamp, không object/DateTime/serialize). API: `hit(key, maxAttempts, decaySeconds): bool`, `tooManyAttempts(key, maxAttempts): bool`, `attempts(key): int`, `remaining(key, maxAttempts): int`, `clear(key): void`, `availableIn(key): int`. Không DB, không Redis, không Config.
- `core/Middleware/RateLimitMiddleware.php` — **placeholder framework component có chủ đích**: chỉ implement `MiddlewareInterface`, `return $next($request);`, không tự xác định key/limit, không gọi `hit()`. Logic rate-limit thật do Module tương lai tự gọi `RateLimiter` trực tiếp (biết đủ business context để xác định bucket).
- `tests/Core/RateLimiterTest.php` (14 test) + `tests/Core/Middleware/RateLimitMiddlewareTest.php` (4 test — chỉ xác nhận passthrough).

### Design decisions

- Architecture Analysis phát hiện xung đột kiến trúc: cơ chế Middleware hiện tại (`list<class-string>` + `Container::get(string $id)`) không hỗ trợ tham số hoá per-route — không thể gắn `new RateLimitMiddleware($limiter, 'login', 5, 60)` trực tiếp vào 1 route. Owner quyết định **không sửa `MiddlewarePipeline`** để phục vụ tính năng này — chấp nhận `RateLimitMiddleware` là placeholder tối thiểu, giữ nhất quán hình dạng với middleware khác (`Auth`/`Csrf`/`Authorization` đều nhận đúng 1 service qua constructor) mà không có logic thật, tránh thiết kế sai ngay từ Foundation.
- Không breaking change — 0 file cũ sửa.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — `RateLimiterTest`: 14 tests; `RateLimitMiddlewareTest`: 4 tests (trong lần chạy toàn bộ suite cùng CMS-022/024).

## [0.0.22] — CMS-022: Authorization

### Added

- `core/Authorization.php` — đọc `roles`/`permissions` từ Session (namespace `auth.roles`/`auth.permissions`, đã dự trù từ CMS-007), thuần đọc, không ghi, không DB, không biết `Auth`. API: `roles()/permissions()/hasRole()/hasAnyRole()/hasAllRoles()/hasPermission()/hasAnyPermission()/hasAllPermissions()/can()` (`can()` alias thuần của `hasPermission()`).
- `core/Middleware/AuthorizationMiddleware.php` — gate chung: chặn (403 JSON) nếu user không có role/permission nào được gán. Không tham số hoá per-route (cùng lý do kiến trúc như CMS-023) — kiểm tra quyền cụ thể cho từng hành động là trách nhiệm Controller tự gọi `Authorization::hasRole()/can()` trực tiếp.
- `tests/Core/AuthorizationTest.php` (17 test) + `tests/Core/Middleware/AuthorizationMiddlewareTest.php` (4 test).

### Design decisions

- Cùng phát hiện xung đột kiến trúc Middleware-tham-số-hoá như CMS-023 (Owner Decision: Phương án C — không sửa `MiddlewarePipeline`/`Router`/`Route`/`Container`).
- Không breaking change — 0 file cũ sửa.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — `AuthorizationTest`: 17 tests; `AuthorizationMiddlewareTest`: 4 tests (trong lần chạy toàn bộ suite cùng CMS-023/024).

## [0.0.21] — CMS-021: Authentication

### Added

- `core/Auth.php` — quản lý trạng thái "đã đăng nhập hay chưa" trong Session (namespace `auth.user_id`/`auth.user`, đã dự trù từ CMS-007). Không verify credential, không Database, không password — việc xác thực thuộc Module Auth tương lai. `login(int|string $userId, array $user = []): void` theo đúng thứ tự `Session::regenerate()` (chống fixation) → `remove('csrf.token')` (rotate CSRF khi nâng mức tin cậy) → `set('auth.user_id', $userId)` → `set('auth.user', $user)`. `logout(): void` dùng `Session::destroy()` (huỷ toàn bộ session, không chỉ xoá key `auth.*`). `check(): bool`/`id(): int|string|null`/`user(): ?array` đọc lại từ Session.
- `core/Middleware/AuthMiddleware.php` — implement `MiddlewareInterface` (CMS-006, không đổi contract), chặn request chưa đăng nhập trả `Response::json({success:false,data:null,message:'Unauthenticated.',errors:[]}, 401)`, không redirect, không Config.
- `tests/Core/AuthTest.php` (13 test), `tests/Core/Middleware/AuthMiddlewareTest.php` (3 test).

### Design decisions

- **Option A** (đã cân nhắc kỹ ở Architecture Analysis): `Auth` thuần quản lý session-state, KHÔNG query Database/verify password — vì chưa có bảng `users` nào tồn tại (chưa có migration thật), và giữ đúng ranh giới Core=cơ chế/Module=nghiệp vụ đã áp dụng xuyên suốt (`Validator`, `Csrf`, `ExceptionHandler`...). Module Auth đầy đủ (JWT, password reset, login_logs — theo `02-module-auth.md`) là phạm vi Phase 3+ riêng.
- `login()` rotate CSRF token (`remove('csrf.token')`) — theo khuyến nghị OWASP, phòng thủ theo chiều sâu khi nâng mức tin cậy; `Auth` biết chuỗi literal `'csrf.token'` độc lập (không phụ thuộc `Csrf`, tránh coupling không cần thiết).
- 0 file Core cũ bị sửa — `Container` tự auto-wire `Auth`/`AuthMiddleware` (đã xác nhận qua đọc trực tiếp `Container.php`, nhất quán CMS-020).

### Fixed

- Bug trong test tự viết (không phải trong `Auth`): `Session::destroy()` khiến `isStarted()` trở về `false` — test kiểm tra `check()`/`id()` ngay sau `logout()` mà chưa `start()` lại session sẽ nhận `SessionException`. Sửa bằng cách mô phỏng đúng "request kế tiếp tự `start()` lại" trong test, không đổi `Auth.php`/`Session.php`.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `AuthTest`: 13 tests/17 assertions; `AuthMiddlewareTest`: 3 tests/4 assertions; toàn bộ suite: 291 tests, 500 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.20] — CMS-020: CSRF Protection

### Added

- `core/Csrf.php` — quản lý vòng đời token CSRF (sinh/lưu/đọc/so khớp), thuần logic, không biết HTTP. `token(): string` — get-or-generate qua `Session` (namespace `csrf.token`, đã dự trù sẵn từ CMS-007), sinh bằng `bin2hex(random_bytes(32))` (256-bit CSPRNG) nếu chưa có, không regenerate nếu đã tồn tại. `verify(string $submitted): bool` — so khớp `timing-safe` bằng `hash_equals()`, tự guard kiểu dữ liệu trước khi so sánh. Không tự `Session::start()`, không throw exception mới, phụ thuộc duy nhất `Session` (coupling cần thiết cố hữu của bài toán, không tránh được).
- `core/Middleware/CsrfMiddleware.php` — implement `MiddlewareInterface` có sẵn từ CMS-006 (không đổi contract). Safe methods (`GET/HEAD/OPTIONS`) đi thẳng `$next()`, không kiểm tra. Unsafe methods (`POST/PUT/PATCH/DELETE`) đọc token theo đúng thứ tự `_token` (input) → `X-CSRF-TOKEN` (header) → `X-XSRF-TOKEN` (header); guard `is_string()` trước khi gọi `Csrf::verify()` (không ép kiểu `(string)` mù quáng, tránh PHP Warning "Array to string conversion" nếu client gửi `_token[]=...`); fail trả `Response::json({success:false,data:null,message:"CSRF token mismatch.",errors:[]}, 419)`.
- `tests/Core/CsrfTest.php` (6 test), `tests/Core/Middleware/CsrfMiddlewareTest.php` (11 test — gồm 2 edge case: `_token` rỗng không fallback qua header, `_token` dạng mảng bị từ chối sạch không Warning).

### Design decisions

- Tách 2 class (`Csrf` logic thuần / `CsrfMiddleware` tích hợp HTTP) thay vì gộp 1 class — đúng SRP, nhất quán pattern đã dùng cho `Validator`/`ExceptionHandler`.
- Hardcode tên field/header (`_token`/`X-CSRF-TOKEN`/`X-XSRF-TOKEN`) — không thêm `Config` dependency chỉ để đổi tên, đúng YAGNI.
- Fail trả **419** (không 403) — quy ước riêng cho lỗi CSRF, tách biệt khỏi lỗi phân quyền (403, dành cho CMS-022 Authorization sau này).
- Không tạo `CsrfException`/mở rộng `ExceptionHandler` — Middleware tự trả `Response` trực tiếp (giống `ShortCircuitMiddleware` đã có tiền lệ trong test suite từ CMS-018), giữ `ExceptionHandler` (CMS-019) ổn định.
- Token sống theo Session (không one-time, không regenerate mỗi request) — chuẩn thực dụng phổ biến (Laravel/Django/Rails).
- Đặt trong Core (`core/`, không phải Module/App) — CSRF thuần cơ chế, không business logic, nhất quán mọi Core Component khác (`Validator`/`MigrationManager`/`ExceptionHandler`).
- **Xác nhận qua đọc trực tiếp `Container.php`**: không cần đăng ký `Csrf`/`CsrfMiddleware` vào `Application::registerCoreServices()` — Container tự auto-wire qua fallback `class_exists()`. **0 file Core cũ nào bị sửa.**
- CSRF hoàn toàn **opt-in** — không tự động áp dụng cho bất kỳ route nào; việc gắn `CsrfMiddleware::class` vào route/group cụ thể (Admin Panel, không phải `/api/*`) thuộc phạm vi Module/App sau này.
- Ghi nhận cho CMS-021 (Authentication): `Session::regenerate()` không tự cascade đổi `csrf.token` — nếu cần rotate token khi đăng nhập (session fixation defense-in-depth), CMS-021 có thể dùng `Session::remove('csrf.token')` + `Csrf::token()` (API đã có sẵn, không cần đổi `Csrf`).

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `CsrfTest`: 6 tests/7 assertions; `CsrfMiddlewareTest`: 11 tests/17 assertions; toàn bộ suite: 275 tests, 479 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.19] — CMS-019: Exception Handler

### Added

- `core/ExceptionHandler.php` — `final class`, **0 dependency** (không Config/Container/logging/Session/Database/View/Request) — mức cô lập cao nhất cùng `Validator`. Public API duy nhất: `handle(Throwable $exception, bool $debug): Response`. Mapping tĩnh (không registry/extend): `RouteNotFoundException`→404/`'Not Found'`, `MethodNotAllowedException`→405/`'Method Not Allowed'`, mọi `Throwable` khác→500 (`debug ? getMessage() : 'Internal Server Error'`). Debug block (`exception`/`file`/`line`/`trace`) chỉ xuất hiện khi `status===500 && debug===true` — `trace` dùng `explode("\n", getTraceAsString())` (không dùng `getTrace()` để tránh lỗi serialize JSON với object/resource không encode được, VD instance `PDO`/`Closure` trong tham số hàm). Giữ nguyên envelope `{success:false,data:null,message,errors:[]}` qua `Response::json()` đã có.
- `tests/Core/ExceptionHandlerTest.php` (8 test, unit thuần — không `Router`/`Application`/filesystem).

### Changed

- `core/Application.php` — gộp 3 nhánh `catch` (`RouteNotFoundException`/`MethodNotAllowedException`/`Throwable`) thành 1 `catch (Throwable $exception)` duy nhất, delegate toàn bộ mapping cho `ExceptionHandler` (đăng ký singleton, 0 dependency riêng). Quyết định có `logException()` hay không dựa trên `$response->getStatusCode() >= 500` (thay vì `instanceof` từng loại exception) — giữ **chính xác** hành vi log cũ (404/405 không log, 500 vẫn log) đồng thời giúp `Application` không cần biết tên class exception cụ thể nào nữa. Xoá `errorResponse()` (không còn dùng). `isDebug()`/`logException()` không đổi.
- `tests/Core/ApplicationTest.php` — thêm 1 test `testRouteNotFoundDoesNotWriteToLogFile` khoá tường minh hành vi "chỉ log status ≥ 500" (trước đây chỉ đúng ngầm định qua cấu trúc 3 catch riêng).

### Design decisions

- Trước khi code, Architecture Analysis phát hiện xung đột cấu trúc loại bỏ hướng "Middleware-based Exception Handler": `Router::match()` (nơi ném `RouteNotFoundException`/`MethodNotAllowedException`) chạy TRƯỚC `MiddlewarePipeline::handle()` — 1 Middleware (kể cả Global, mới có ở CMS-018) không bao giờ có cơ hội bắt được 2 exception này. Chọn tách `Core\ExceptionHandler` riêng (cải thiện SRP cho `Application`) thay vì tiếp tục mở rộng `Application::handle()` bằng nhiều nhánh `catch`.
- Không xây registry/`extend()` cho mapping — chỉ 2/23 exception hiện có cần map riêng, thêm registry lúc này là YAGNI.
- Logger (Core Component đầy đủ tính năng) tiếp tục ngoài phạm vi — `logException()` giữ nguyên tại `Application`, không chuyển vào `ExceptionHandler` (tách bạch rõ 2 mối quan tâm: "map lỗi thành Response" vs "ghi log").
- Không auto-map `ValidationException`→422 — ranh giới rõ: `Application`/`ExceptionHandler` chỉ xử lý exception KHÔNG ai bắt (unexpected); Controller/Form layer tự quyết định catch `ValidationException` nếu muốn custom response.
- Không map 403 — chưa có Auth/Authz module, chưa có exception contract cho authorization, để dành CMS sau.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `ExceptionHandlerTest`: 8 tests/20 assertions; `ApplicationTest`: 11 tests/27 assertions; `RouterTest`: 19 tests/29 assertions; toàn bộ suite: 258 tests, 455 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.18] — CMS-018: Middleware Pipeline Extension

> Không có `v0.0.17` — CMS-017 (Redirect) kết thúc bằng Architecture Decision, không phát sinh code/tag (xem mục "CMS-017" bên dưới).

### Added

- `core/Router.php` — mở rộng **additive** (Middleware Pipeline gốc từ CMS-006 — `MiddlewareInterface`/`MiddlewarePipeline` — giữ nguyên tuyệt đối, không sửa). Thêm `middleware(array $middleware): static` — đăng ký Global Middleware (append, fluent), chạy trên **mọi route** bất kể group/route-level. `get()/post()/put()/patch()/delete()` thêm tham số cuối `array $middleware = []` — cho phép gán middleware trực tiếp cho 1 route đơn lẻ mà không cần bọc `group()`. Thứ tự onion cuối cùng: `Global → Group → Route-specific → Controller` (Global gộp **runtime** trong `dispatch()`, không "nướng cứng" vào `Route` lúc đăng ký — đảm bảo mọi route luôn nhận đúng global middleware hiện tại bất kể thứ tự gọi `middleware()` so với lúc route được đăng ký).
- `tests/Fixtures/Http/MiddlewareC.php`, `tests/Core/RouterTest.php` (+6 test).

### Design decisions

- Trước khi code, Architecture Analysis phát hiện Middleware Pipeline **đã tồn tại và hoạt động từ CMS-006** — phạm vi CMS-018 không phải "xây Pipeline mới" mà là hoàn thiện 2 gap thật: Global Middleware (chưa có cơ chế áp dụng cho mọi route) và Route-level middleware đơn lẻ (trước đây chỉ gán được qua `group()`).
- `core/Route.php` xác nhận **không cần sửa** — constructor tiếp tục nhận `list<class-string>` middleware phẳng, không cần biết nguồn gốc (global/group/route).
- Router tiếp tục là owner duy nhất của middleware lifecycle — `core/Application.php` không đổi, không biết middleware tồn tại (đúng ranh giới đã có từ CMS-006).
- Loại trừ có chủ đích khỏi phạm vi: middleware alias/registry (`'auth'` string), terminate/after-response hook, plugin middleware, event middleware — đúng YAGNI, chưa có nhu cầu thật.

### Verified

- `vendor/bin/phpunit` trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **PASS** — `RouterTest.php` riêng: 19 tests/29 assertions; toàn bộ suite: 249 tests, 433 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## CMS-017: Redirect — Architecture Decision (không có code/tag)

Kết luận: `Response::redirect()` (CMS-006) + `Session::flash()`/`getFlash()` (CMS-007) + `Request::all()`/`header('Referer')` (CMS-015) đã đủ để Controller tự viết pattern "redirect kèm flash message" — quyết định **không tạo `core/Redirector.php`** hay bất kỳ class nào cầu nối `Response`↔`Session` (sẽ phá vỡ nguyên tắc "Response/Session độc lập tuyệt đối" đã xuyên suốt từ CMS-001). Ghi nhận convention: `$session->flash($key, $value)` rồi `return Response::redirect($location)`. Chi tiết đầy đủ trong `core-architecture.md`.

## [0.0.16] — CMS-016: HTTP Response Layer

### Added

- `core/Http/Response.php` — mở rộng **additive** (không tạo Core Component mới, không breaking method cũ). Constructor thêm 1 tham số cuối cùng có default `[]`: `cookies` (lưu tách riêng khỏi `headers` vì HTTP cho phép nhiều `Set-Cookie` header cùng lúc, còn `headers` chỉ giữ 1 giá trị/tên). Thêm: `withHeader()/withHeaders()/withStatus()` (immutable, trả instance mới), `withCookie(name, value, options)` (`options`: `expires/path/domain/secure/httponly[default true]/samesite`, caller tự truyền tường minh — Response không đọc Config), `withCache(seconds, public = true)`/`noCache()` (chỉ thao tác header `Cache-Control`, không ETag/Last-Modified/Vary), `getCookies()`. `send()` cập nhật gửi thêm `Set-Cookie` qua `header(..., false)` để không ghi đè khi có nhiều cookie.
- `tests/Core/Http/ResponseTest.php` (17 test) — file test đầu tiên dành riêng cho `Response` (trước đây chỉ được kiểm chứng gián tiếp qua `RouterTest`/`ControllerResolverTest`).

### Design decisions

- **KHÔNG** thêm `apiSuccess()/apiError()` — envelope JSON (`{success,data,message,errors}`) là business convention (REST/GraphQL/JSON:API/HAL đều khác nhau), không phải HTTP contract, Core không nên áp đặt. Module/Helper tự dựng body rồi truyền `Response::json()`. Technical Debt #4 (đã ghi từ CMS-006) tiếp tục tồn đọng có chủ đích.
- **KHÔNG** thêm `download()/file()` — đưa filesystem vào Response sẽ thay đổi boundary (Streaming/Range Request/MIME detection là chủ đề riêng), để dành CMS khác khi Module Media cần thật.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 243 tests, 424 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.15] — CMS-015: HTTP Request Layer

### Added

- `core/Http/Request.php` — mở rộng **additive** (không tạo Core Component mới, không breaking method cũ). Constructor thêm 3 tham số cuối cùng có default `[]`: `files`, `cookies`, `server`. `fromGlobals()` đọc thêm `$_FILES`/`$_COOKIE`/`$_SERVER` (đúng 1 lần, cùng điểm cô lập superglobal đã có). `withRouteParams()` giữ nguyên 3 property mới khi tạo instance mới. Thêm 13 method: `method()/uri()/path()` (alias các getter cũ), `all()` (query+body, body ghi đè khi trùng key), `has()/filled()`, `cookie()`, `file()` (trả raw `$_FILES` entry, không abstraction), `ip()` (chỉ đọc `REMOTE_ADDR`, không Trusted Proxy), `userAgent()`, `isMethod()`, `ajax()`, `json()` (kiểm tra Content-Type của request, không phải Accept header).
- `tests/Core/Http/RequestTest.php` (+14 test), `tests/Core/Http/RequestFromGlobalsTest.php` (+1 test, mở rộng setup/teardown cho `$_FILES`/`$_COOKIE`).

### Design decisions

- Trước khi code, Architecture Analysis phát hiện yêu cầu ban đầu mô tả trùng trách nhiệm với `Core\Http\Request` đã hoàn thành ở CMS-006 — chốt: CMS-015 là mở rộng file đã có, không tạo Request thứ 2 (tránh 2 nguồn sự thật cho "request hiện tại").
- Không Method Spoofing (`_method`), không Trusted Proxy — cả 2 để dành phase sau khi có nhu cầu thật (reverse proxy/HTML form giả lập verb), ghi Technical Debt tường minh thay vì tự bật ngầm định (rủi ro bảo mật nếu làm sai).
- Giữ eager JSON parsing như CMS-006 — không có lợi ích rõ rệt khi chuyển sang lazy cho use-case thực tế hiện tại.

### Fixed

- 2 failure PHPUnit ở vòng chạy đầu (`testAjaxDetectsXRequestedWithHeader`, `testJsonDetectsJsonContentType`) — do test tự viết dùng header key chưa uppercase, trong khi `header()` (từ CMS-006) chỉ chuẩn hoá phía tra cứu (`strtoupper($name)`), không chuẩn hoá key đã lưu trong mảng `$headers`. Đây là hành vi đã có từ trước, không phải bug phát sinh ở CMS-015 — sửa đúng phạm vi trong dữ liệu test, không đổi `core/Http/Request.php`.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 229 tests, 382 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.14] — CMS-014: Validation Layer

### Added

- `core/Validator.php` — validate `array $data` theo `array<string,string> $rules` (rule string kiểu Laravel, VD `'required|email|max:255'`), trả `ValidationResult` (không throw cho input sai). Registry rule nội bộ dạng `Closure`, là state riêng của từng instance (không static/global) — 16 rule built-in đăng ký qua chính `extend()` (built-in và custom dùng chung 1 cơ chế): `required/nullable/string/int/integer/numeric/boolean/array/email/min/max/between/in/regex/date/confirmed`. `extend(string $ruleName, Closure $callback): void` cho phép đăng ký rule tuỳ chỉnh, CHO PHÉP ghi đè rule built-in. `validate()` chạy hết toàn bộ rule của 1 field (không bail ở lỗi đầu tiên), gom lỗi vào `array<string, list<string>>`. Field vắng mặt và không có `required` → bỏ qua toàn bộ rule còn lại của field. 0 dependency vào Core Component khác (không Config/Container/Database/Router/Session/Hook) — không đăng ký Container/Application, module tự `new Validator()` khi cần.
- `core/Validation/ValidationResult.php` — `passes()/fails()/errors()/firstError()`.
- `core/Validation/ValidationException.php` — chỉ ném khi `$rules` tham chiếu 1 rule không tồn tại trong registry (lỗi cấu hình, không phải lỗi input).
- `tests/Core/ValidatorTest.php` (31 test).

### Design decisions

- Registry rule dùng Closure nội bộ, không tách mỗi rule thành 1 class riêng (Strategy Pattern) — tránh phình ~15 file nhỏ cho phạm vi tối giản, đúng KISS.
- Không đăng ký `Validator` vào Container/`Application` — không có state cần singleton (khác `Hook`), giống cách tiếp cận đã dùng cho `MigrationManager`.
- Chỉ 1 rule format (string kiểu Laravel) — không hỗ trợ song song array format, đúng YAGNI.

### Fixed

- Rule `in` ép `(string) $value` trực tiếp — nếu `$value` là mảng, PHP phát sinh Warning "Array to string conversion". Sửa bằng guard `is_scalar($value) &&` trước khi ép kiểu, giá trị non-scalar tự fail rule `in` thay vì gây Warning. Phát hiện qua Self Code Review, không phải qua PHPUnit thật (không có test nào truyền mảng vào rule `in` trước đó).

### Verified

- `vendor/bin/phpunit` PASS — xác nhận qua lần chạy toàn bộ suite cùng CMS-015 (229 tests/382 assertions, bao gồm 31 test của `ValidatorTest.php`), 0 Errors/Failures/Warnings/Risky/Deprecations.

## [0.0.13] — CMS-013: Migration System

### Added

- `core/MigrationManager.php` — quản lý schema database, không chứa business logic. Constructor nhận `Database $database, string $driver, string $migrationsPath` — validate `$driver` ∈ `{'mysql','sqlite'}` ngay trong constructor (throw `MigrationException` nếu sai, cùng kiểu validate-trong-constructor đã có tiền lệ ở `QueryBuilder`/`IdentifierValidator`, không phải side-effect I/O). `discover()` glob `{migrationsPath}/*.php`, sort theo tên file (timestamp prefix đảm bảo thứ tự), không memoize (mỗi lần gọi CLI là 1 vòng đời `MigrationManager` riêng, khác `PluginManager`). Migration file `require` và validate phải trả đúng shape `['up' => Closure, 'down' => Closure]` (không interface, không class, không DSL — Decision #1). `migrate()` chạy toàn bộ migration chưa áp dụng theo đúng thứ tự, mỗi migration bọc trong `Database::transaction()` riêng, ghi record vào bảng `migrations` (cột `batch` tăng dần theo `MAX(batch)+1`). `rollback()` hoàn tác toàn bộ migration thuộc batch gần nhất, theo thứ tự đảo ngược (`ORDER BY id DESC`). `status()` đối chiếu file đã discover với bảng `migrations`, trả `{name, applied, batch}`. **Fail-fast tuyệt đối** ở cả `migrate()`/`rollback()` — khác hẳn `ModuleManager`/`PluginManager` (không có `getFailures()`), vì các bước thay đổi schema có tính tuần tự/phụ thuộc, cách ly lỗi có thể làm hỏng schema.
- `core/Migration/MigrationException.php`, `MigrationNotFoundException.php` (rollback nhưng file migration đã bị xoá khỏi disk).
- `bin/migrate.php` — CLI entry point mỏng, tự bootstrap `new Config()`/`new Database()` trực tiếp (không qua Container), đọc `$argv[1]` (`migrate`/`rollback`/`status`) qua 1 switch đơn giản — không Console Kernel, không Command Registry.
- `tests/Fixtures/Migrations/{Valid,Failing,Malformed}/*` + `tests/Core/MigrationManagerTest.php` (16 test: migrate/rollback/status, batch tăng dần, fail-fast, malformed migration, `MigrationNotFoundException`, validate driver, regression không ảnh hưởng bảng khác do `Database` quản lý).

### Design decisions

- Trước khi code: thực hiện 1 vòng Adversarial Architecture Review riêng — phát hiện rủi ro `MigrationManager` tự đọc PDO driver qua `getAttribute()` (rò rỉ trách nhiệm khỏi `Database`) và rủi ro dùng Container trong `bin/migrate.php` sẽ trùng lặp logic wiring `Database` với `Application.php`. Cả 2 đều bị loại bỏ khỏi thiết kế cuối.
- `driver: string` truyền vào `MigrationManager` qua constructor (lấy từ `Config` tại `bin/migrate.php`) — `MigrationManager` không bao giờ chạm PDO, không có API mới nào được thêm vào `Database` (Decision #8).
- Migration hoàn toàn tách khỏi HTTP lifecycle — không sửa `Application.php`/`public/index.php`, không có Module/Plugin nào biết tới `MigrationManager`.

### Known limitations (ghi nhận chủ động, không phải phát sinh ngoài ý muốn)

- `Database::transaction()` bọc quanh DDL chỉ thực sự transactional trên SQLite — MySQL tự động implicit-commit khi gặp DDL, không rollback được nếu lỗi xảy ra sau câu DDL trong cùng 1 migration.
- Không hỗ trợ concurrent migration — không có locking chống 2 tiến trình `migrate()`/`rollback()` chạy đồng thời (race condition trên tính `batch`).
- `rollback()` phụ thuộc migration file gốc còn tồn tại trên disk — xoá file sẽ khiến rollback bất khả thi (`MigrationNotFoundException`).

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 185 tests, 299 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.12] — CMS-012: Plugin Manager

### Added

- `core/PluginManager.php` — discover plugin qua `plugin.json` (glob `{pluginsPath}/*/plugin.json`), **memoize kết quả trong instance** (`discover()` chỉ glob + parse 1 lần cho cả vòng đời 1 `PluginManager`, khác chủ đích với `ModuleManager::discover()` không memoize). `resolveLoadOrder()` — topological sort + phát hiện circular dependency, **code độc lập hoàn toàn** với `ModuleManager::visit()` (không chia sẻ abstraction, chấp nhận trùng lặp logic để giữ ổn định, đúng quyết định đã chốt trước khi code). `boot(Hook, enabledKeys)` — **reset `failures` ở đầu mỗi lần gọi**, nạp `Hooks.php` của từng plugin đã bật theo đúng thứ tự dependency qua closure cô lập scope (chỉ `$hook` khả kiến), **cách ly lỗi tuyệt đối**: 1 plugin có `Hooks.php` ném lỗi được ghi vào `failures[key]`, không rethrow, không làm crash các plugin còn lại. `getFailures()` trả map lỗi của lần `boot()` gần nhất. `discover()` phát hiện và ném `PluginException` nếu 2 plugin khai `key` trùng nhau (không âm thầm ghi đè).
- `core/Plugin/PluginDescriptor.php` — value object đọc từ `plugin.json` (key/name/version/author/description/dependencies/path).
- `core/Plugin/PluginException.php`, `PluginNotFoundException.php` (key không tồn tại / dependency chưa bật), `CircularPluginDependencyException.php` (mang `getChain()`, cùng hình dạng `CircularModuleDependencyException`/`Core\CircularDependencyException`) — 3 exception riêng của Plugin Layer, không kế thừa/dùng chung với `Core\Module\*`.
- `tests/Fixtures/Plugins/{GoodPluginA,GoodPluginB,BrokenPlugin,NoHooksPlugin,CircularA,CircularB,ScopeCheckPlugin}/*`, `tests/Fixtures/PluginsInvalid/BadPlugin/plugin.json` (fixture riêng, tách khỏi thư mục chính vì `discover()` throw cho cả thư mục nếu có 1 manifest sai), `tests/Fixtures/PluginsDuplicate/{PluginX,PluginY}/plugin.json` (fixture riêng cho test duplicate key) + `tests/Core/PluginManagerTest.php` (16 test) + `tests/Core/PluginManagerContainerIntegrationTest.php` (2 test Regression — `PluginManager`+`Hook` ráp qua `Container`).

### Changed

- `core/Application.php` — CHỈ 2 điểm bổ sung (không sửa gì khác): đăng ký `PluginManager` làm singleton trong `registerCoreServices()`; trong `boot()`, gọi `pluginManager->boot($hook, array_keys($pluginManager->discover()))` ngay sau đoạn boot `ModuleManager` hiện có (mặc định coi mọi plugin đã discover là "enabled", nhất quán với cách `ModuleManager` đang được boot — chưa có cơ chế bật/tắt theo site, nằm ngoài phạm vi CMS-012).

### Design decisions

- `PluginManager` độc lập hoàn toàn với `ModuleManager` — không refactor `ModuleManager`, không tạo abstraction dùng chung cho topological sort dù logic tương tự (đúng nguyên tắc "không tạo abstraction chỉ để DRY"), chấp nhận trùng lặp có chủ đích để không đụng vào component đã ổn định/đã tag version.
- Ranh giới cách ly lỗi trong `boot()`: chỉ đoạn `require Hooks.php` của từng plugin được try/catch cô lập. Lỗi ở tầng `resolveLoadOrder()` (key không tồn tại, dependency chưa bật, circular dependency) vẫn ném ra ngoài `boot()` — coi là lỗi cấu hình (ai đó khai `enabledKeys` sai), khác bản chất với lỗi runtime của code trong 1 plugin, nhất quán với hành vi hiện tại của `ModuleManager::boot()`.
- Plugin hợp lệ nhưng không có `Hooks.php` được coi là nạp thành công (không lỗi) — xử lý phòng thủ, tương tự cách `ModuleManager` xử lý module không có `routes.php`.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 171 tests, 273 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.11] — CMS-011: Application / Bootstrap

### Added

- `core/Application.php` — điểm khởi động duy nhất của framework. `handle(Request): Response` thuần (test được, không cần superglobal) tách khỏi `run(): void` (I/O boundary duy nhất: `Request::fromGlobals()` + `Response::send()`). `boot()` idempotent (guard `$booted`) — nạp `ModuleManager` (mặc định bật tất cả module đã `discover()`), đăng ký route `/health`. Đăng ký toàn bộ Core Service vào `Container` qua Closure lazy (Database, Session, Hook, CacheDriver/Cache, View, Router, ModuleManager). Bắt `RouteNotFoundException`/`MethodNotAllowedException`/`Throwable` ở `handle()`, trả JSON chuẩn `{success,data,message,errors}` cho 404/405/500 — 500 chỉ lộ message exception thật khi `config('app.debug')=true`, log mọi exception chưa bắt vào `storage/logs/app.log`.
- `public/index.php` — viết lại còn 3 dòng thật (require autoload, `Application::bootstrap(dirname(__DIR__))->run()`), thay thế hoàn toàn smoke test từ CMS-002.
- `tests/Fixtures/App/*` (config đầy đủ + module + theme fixture), `tests/Fixtures/AppProduction/*` (fixture riêng cho test `debug=false`) + `tests/Core/ApplicationTest.php` (11 test).

### Design decisions

- `config/app.php` thêm key `theme` — dùng chung cho `activeTheme`/`defaultTheme` của `View` cho tới khi có `TenantManager` thật (Phase 2+).
- `Application::container(): Container` — bổ sung ngoài thiết kế đã duyệt ban đầu (chỉ `bootstrap/handle/run`), cần thiết để test xác nhận Core Service đăng ký đúng vào Container mà không phải đi qua 1 route thật.
- Không xây `Core\Logger` đầy đủ tính năng (channel/level/formatter) — chỉ `file_put_contents` trực tiếp trong `Application`, đúng phạm vi CMS-011.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 154 tests, 237 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.10] — CMS-010: Module Manager

### Added

- `core/ModuleManager.php` — discover module qua `module.json` (glob `{modulesPath}/*/module.json`), resolve thứ tự load bằng topological sort + phát hiện circular dependency (cùng mô hình `Container::resolve()` — stack `resolving`, chặn tại chỗ không đệ quy vô hạn), `boot(Router, enabledKeys)` nạp `routes.php` của từng module đã bật (theo đúng thứ tự dependency) vào `Router` qua closure cô lập scope, trả về danh sách key đã nạp. Không tự query Database để biết module nào "bật" cho tenant nào — nhận `enabledKeys` từ bên ngoài, giữ core trung lập (nhất quán `Database`/`View`/`Cache`).
- `core/Module/ModuleDescriptor.php` — value object đọc từ `module.json` (key/name/version/dependencies/path).
- `core/Module/ModuleException.php`, `ModuleNotFoundException.php` (key không tồn tại / dependency chưa bật), `CircularModuleDependencyException.php` (mang `getChain()`, cùng hình dạng `Core\CircularDependencyException` của Container).
- `tests/Fixtures/Modules/{Alpha,Beta,Circular1,Circular2,NoRoutes}/*`, `tests/Fixtures/ModulesInvalid/BadModule/module.json` + `tests/Core/ModuleManagerTest.php` (9 test) + `tests/Core/ModuleManagerContainerIntegrationTest.php` (1 test Regression).

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 144 tests, 212 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.9] — CMS-009: Hook System

### Added

- `core/Hook.php` — Hook System kiểu WordPress (Action + Filter) trên 1 registry dùng chung (action/filter chỉ khác cách gọi `do()`/`apply()`, không khác cách đăng ký — đúng cách WordPress triển khai bên trong). API: `action()/filter()/removeAction()/removeFilter()/do()/apply()/onError()`. Priority mặc định 10 (số nhỏ chạy trước, cùng priority theo thứ tự đăng ký). Wildcard hook (`"post.*"`) trộn đúng thứ tự priority với hook đăng ký chính xác, không tách chạy riêng trước/sau. Mỗi callback chạy trong `try/catch` riêng (đúng `13-module-plugin.md` — 1 plugin lỗi không ảnh hưởng plugin khác), `onError()` là điểm mở cho `PluginManager` (task sau) tự ghi log, `Hook` không tự phụ thuộc Database/Logger. Không static, không hàm global — 1 instance dùng chung qua `Container` trong 1 request.
- `tests/Core/HookTest.php` (17 test) + `tests/Core/HookContainerIntegrationTest.php` (2 test Regression — singleton qua Container, auto-wire không cần bind tường minh vì không có constructor dependency).

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 133 tests, 192 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations, 4 Skipped (Redis) đúng thiết kế.

## [0.0.8] — CMS-008: Cache Layer

### Added

- `core/Cache.php` — facade duy nhất của Cache Layer. Áp `prefix` (namespace cấp app từ `config/cache.php`), `remember(key, ttl, Closure)` (Object cache), Tag support (`put(..., tags: [])`/`flushTags()`) triển khai bằng registry key portable qua mọi driver (không đặt ở driver, tránh viết logic tag riêng cho từng loại storage). Tenant key là **quy ước đặt tên** (`"tenant:{id}:..."`), không phải API riêng — nhất quán với `Database`/`QueryBuilder::forTenant()`.
- `core/Cache/CacheDriver.php` — interface tối giản (`get/put/has/forget/flush`), không tag.
- `core/Cache/FileCacheDriver.php` — ghi **atomic** (file tạm + `rename()`), tên file là `hash(key)` (an toàn tuyệt đối với path traversal/ký tự lạ trong key).
- `core/Cache/RedisCacheDriver.php` — dùng `ext-redis` (PHP extension, không thêm composer dependency), lazy connect.
- `core/Cache/CacheException.php`.
- `tests/Core/Cache/FileCacheDriverTest.php` (8 test), `tests/Core/CacheTest.php` (8 test), `tests/Core/Cache/RedisCacheDriverTest.php` (4 test, tự `markTestSkipped` nếu môi trường không có Redis thật), `tests/Core/CacheContainerIntegrationTest.php` (1 test Regression).

### Design decisions

- Quy trình làm việc đổi từ task này: Design → Implementation → Self Code Review → Unit Test → 1 báo cáo tổng hợp cuối, không dừng hỏi xác nhận giữa các bước trừ khi có quyết định kiến trúc lớn/breaking change/ảnh hưởng toàn hệ thống.

### Fixed

- `RedisCacheDriver::connection()` (phát hiện ở Architecture Review riêng Cache Layer, sau khi Completed) chỉ bắt `RedisException`, nhưng `ext-redis` mặc định **không ném exception** cho `auth()`/`select()` thất bại — chỉ trả `false`. Sai password/database index sẽ bị bỏ qua âm thầm, lỗi thật chỉ lộ ra ở 1 lệnh Redis khác sau đó với thông báo khó hiểu. Sửa: kiểm tra tường minh giá trị trả về của `connect()/auth()/select()`, ném `CacheException` rõ ràng ngay tại điểm lỗi.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 115 tests, 170 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations. 4 Skipped (`RedisCacheDriverTest`) đúng thiết kế — môi trường không có `ext-redis`.

## [0.0.7] — CMS-007: Session

### Added

- `core/Session.php` — wrapper duy nhất quanh `$_SESSION`/`session_*()`. Chỉ Storage (không login/logout/check quyền — thuộc `AuthService` Phase sau). Lazy start (không tự `session_start()` trong constructor). Namespace dot-notation lồng nhau **giống hệt `Config::get()`** (nhất quán kiến trúc): `set('auth.user_id', 5)` → `$_SESSION['auth']['user_id']`. Flash message đúng vòng đời 1 request (hết hạn theo tuổi request qua cơ chế 2 bucket `_flash_old`/`_flash_new`, không phải "xoá khi đọc"). `regenerate()` chống session fixation, `destroy()` xoá cả `$_SESSION` lẫn cookie thật.
- `core/SessionException.php` — ném khi gọi `get()/set()/has()/remove()/flash()/getFlash()/regenerate()` trước `start()` (tránh dùng `$_SESSION` chưa được PHP khởi tạo → Warning).
- `tests/Fixtures/config/auth.php` (fixture) + `tests/Core/SessionTest.php` (13 test — mô phỏng vòng đời 3 "request" trong 1 test qua `session_write_close()`/`start()` lại).

### Fixed

- `RouterTest::testDoesNotConfuseNotFoundWithMethodNotAllowed` (viết ở CMS-006) bị PHPUnit báo **Risky** — dựa vào type của `catch` để "xác minh ngầm" loại exception, không có `self::assert*()` thật. Viết lại: `catch (Throwable)` rộng + `assertInstanceOf()`/`assertNotInstanceOf()` tường minh cho cả 404 và 405 trong cùng 1 test.
- `Tests\Fixtures\Http\PhpInputStreamStub` (viết ở CMS-006) gây **Deprecation** "Creation of dynamic property ...::$context" trên PHP 8.2+ — PHP Stream Wrapper API tự gán `$stream->context` khi đăng ký wrapper, class không khai báo property này tường minh. Sửa: thêm `public mixed $context = null;` (không dùng `#[AllowDynamicProperties]` — chỉ che cảnh báo, không sửa gốc). Rà soát toàn bộ project xác nhận đây là stream wrapper tự viết duy nhất.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 94 tests, 140 assertions, 0 Errors, 0 Failures, 0 Warnings, 0 Risky, 0 Deprecations.

## [0.0.6] — CMS-006: Router + HTTP Layer

### Added

- `core/Http/Request.php` — Immutable (mọi `with*()` tạo instance mới qua `new self(...)`, không `clone` — PHP 8.1 không cho gán lại property `readonly` trong `__clone()`). `fromGlobals()` là nơi duy nhất đọc `$_SERVER`/`$_GET`/`$_POST`. `routeParam()` trả `?string` thô, không ép kiểu/validate.
- `core/Http/Response.php` — Immutable, `send()` là nơi duy nhất gọi `header()`/`echo` thật. Factory `json()/html()/redirect()`.
- `core/Route.php` — value object 1 route đã đăng ký, parse `{param}` thành tên (không ép kiểu), `signature()` phục vụ phát hiện đăng ký trùng Method+URI+Domain.
- `core/Router.php` — CHỈ chịu trách nhiệm: đăng ký route (`get/post/put/patch/delete`), `group()` (chỉ merge prefix/middleware/domain), match route (phân biệt rõ 404 `RouteNotFoundException` và 405 `MethodNotAllowedException`, ném `DuplicateRouteException` ngay lúc đăng ký nếu trùng — không đợi runtime), `dispatch()` điều phối Middleware Pipeline → Controller Resolver.
- `core/Middleware/MiddlewareInterface.php`, `core/Middleware/MiddlewarePipeline.php` — mô hình Onion (Before/After), middleware có thể short-circuit (tự trả `Response`, không gọi `$next()`).
- `core/Router/ControllerResolver.php` — bước cuối pipeline, resolve Controller qua `Container` (tách riêng theo sơ đồ `Middleware Pipeline → Controller Resolver → Controller` để test độc lập được).
- `core/Router/{RouteNotFoundException,MethodNotAllowedException,DuplicateRouteException}.php`.
- `tests/Fixtures/Http/*.php` (8 fixture: Controller, Middleware, Service) + `tests/Core/RouterTest.php` (14 test) + `tests/Core/Router/ControllerResolverTest.php` (3 test) + `tests/Core/Http/RequestTest.php` (3 test) + `tests/Core/RouterContainerDatabaseViewIntegrationTest.php` (1 test Regression — Router ráp đúng `Container`/`Database`/`View` mà không sửa 3 file đó).
- `tests/Fixtures/Http/PhpInputStreamStub.php` + `tests/Core/Http/RequestFromGlobalsTest.php` (3 test) — giả lập `php://input` qua stream wrapper để test JSON body parsing.

### Design decisions

- HTTP layer (`Request`/`Response`/`MiddlewareInterface`) tự viết nhẹ, **không dùng PSR-7/PSR-15 thật** — xác nhận rõ ràng, khác với `psr/container` ở CMS-003 (chỉ 3 interface nhỏ); PSR-7/15 là cả hệ sinh thái class (Stream/Uri...), đi ngược tinh thần "PHP thuần, không framework nền".
- Thứ tự Phase 1 đổi: Router lên CMS-006 (ngay sau View) thay vì CMS-010, để có pipeline Container+Database+View+Router hoàn chỉnh sớm; Session/Cache/Hook/Middleware cụ thể dời xuống CMS-007–010.

### Fixed

- `Request::fromGlobals()` chỉ đọc `$_POST`, bỏ sót JSON body — phát hiện ở Architecture Review tổng thể HTTP Layer (đối chiếu ngược `api-document.md`/`02-module-auth.md`), vì PHP không tự điền `$_POST` khi `Content-Type: application/json`, sẽ chặn cứng `POST /api/v1/auth/login` ngay khi Phase 3 bắt đầu. Sửa: `resolveBody()` đọc `php://input` + `json_decode` khi Content-Type là JSON, fallback `$_POST` cho form SSR thường; `extractHeaders()` sửa để bắt thêm `CONTENT_TYPE`/`CONTENT_LENGTH` (2 header duy nhất không có tiền tố `HTTP_` trong `$_SERVER`).

### Known limitations

- `Response::json()` chưa tự bọc chuẩn `{success, data, message, errors}` (`api-document.md`) — Controller phải tự dựng đúng cấu trúc, chưa bắt buộc sửa ngay.
- `Router::dispatch()` ném exception (không tự trả `Response`) cho 404/405 — CMS-011 (bootstrap)/CMS-012 (Error Handler) phải bắt `RouteNotFoundException`/`MethodNotAllowedException` và map thành `Response` tương ứng.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 0 Errors, 0 Failures, 0 Warnings, 0 Deprecations (chạy trước khi vá gap JSON body — **cần chạy lại để xác nhận bản vá không phá vỡ gì**).

## [0.0.5] — CMS-005: View / Theme Engine

### Added

- `core/View.php` — Theme Engine PHP thuần (không compiler, không Twig/Blade). View Resolution 2 cấp cố định: `themes/{active}/views/{dot.notation}.php` → fallback `themes/{default}/views/...` → `ViewNotFoundException`. API: `render()`, `exists()`, `extend()/section()/endSection()/yield()/hasSection()` (layout 1 cấp), `include()` (Partial/Component dùng chung), `e()/escape()/raw()` (escape tường minh, không tự động). Cache nội bộ `resolvePath()` theo instance.
- `core/View/ViewException.php`, `core/View/ViewNotFoundException.php` — 2 exception cho toàn bộ lỗi cấu trúc template.
- `tests/Fixtures/themes/{active,default}/views/*` (fixture 2 theme) + `tests/Core/ViewTest.php` (15 test) + `tests/Core/ViewContainerIntegrationTest.php` (regression: `View` ráp qua `Container` không cần sửa `Container`).

### Fixed

- `View::section()` gọi không cân bằng (2 lần liên tiếp không `endSection()`) làm rò rỉ PHP output buffer (global stack cấp tiến trình) — phát hiện khi tự trace test. Sửa 2 lớp: test tự dọn qua `try/finally`, và `View::render()` tự dọn mọi buffer thừa trong `finally` để bảo vệ response HTTP thật khỏi lỗi tương tự từ template do Theme developer viết sai.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 0 Errors, 0 Failures, 0 Warnings, 0 Deprecations (PHPUnit 10.5.64, PHP 8.3.30). Chạy gộp cùng CMS-003/CMS-004 trong 1 lần: 59 tests, 79 assertions.

## [0.0.4] — CMS-004: Database Layer (PDO + QueryBuilder)

### Added

- `core/Database.php` — class duy nhất chạm PDO trực tiếp: lazy connection (driver `mysql` và `sqlite`), `statement()/select()/selectOne()/insert()/update()/delete()`, Transaction API nested-safe (`beginTransaction()/commit()/rollback()/transaction(Closure)`), query log tối giản (`enableQueryLog()/getQueryLog()`) + điểm mở `onQueryExecuted()` cho Logger/Hook thật ở task sau, hỗ trợ nhiều connection qua `$connectionName` (chuẩn bị Database-per-Tenant nếu cần sau này, không tự implement).
- `core/QueryBuilder.php` — fluent builder (`select/where/whereIn/forTenant/join/orderBy/limit/offset/get/first/count/insert/update/delete`), chỉ dựng SQL + bindings, luôn giao `Database` thực thi. `forTenant(int $tenantId)` là sugar thuần cho `where('tenant_id', '=', $tenantId)`, không chứa business logic.
- `core/Database/{DatabaseException,ConnectionException,QueryException,TransactionException}.php` — exception chuẩn hoá (`QueryException` mang `getSql()`/`getBindings()` phục vụ debug, không lộ ra `getMessage()`).
- `core/Database/IdentifierValidator.php` — whitelist tên cột/bảng (bảo vệ khỏi injection qua identifier, thứ PDO không parameterize được).
- `core/Database/SqlCompiler.php` — `@internal`, chỉ `QueryBuilder` được dùng (không phải Public API của Database Layer) — biên dịch columns/joins/wheres/order-limit-offset thành SQL, thuần function không state, không đọc Config/Database/Transaction.
- `tests/Fixtures/config/database.php` (SQLite in-memory) + `tests/Core/DatabaseTest.php` + `tests/Core/QueryBuilderTest.php` — 31 Unit Test cho Database Layer.

### Fixed

- `QueryBuilder::whereIn($column, [])` (mảng rỗng) sinh SQL sai cú pháp `IN ()` — sửa thành mệnh đề `1 = 0` (không khớp dòng nào) thay vì crash.
- `QueryBuilder::insert([])`/`update([])` (mảng dữ liệu rỗng) sinh SQL sai cú pháp — sửa thành ném `InvalidArgumentException` rõ ràng ngay từ đầu.
- `QueryBuilder::count()` đưa chuỗi `"COUNT(*) as aggregate"` qua `IdentifierValidator` như thể đó là 1 tên cột (phát hiện qua PHPUnit thật trên môi trường người dùng, không phải qua trace tay) → `InvalidArgumentException`. Root cause: dùng chung kênh `$this->columns` cho cả "tên cột người dùng chọn" lẫn "SQL expression nội bộ". Sửa: tách `core/Database/SqlCompiler.php`, `count()` tự dựng SQL riêng không đi qua `compileColumns()`.
- `QueryBuilder.php` vượt 300 dòng (giới hạn `coding-standard.md`) sau khi sửa bug trên — xử lý cùng lúc bằng việc tách `SqlCompiler` (đưa `QueryBuilder.php` về 235 dòng).

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 0 Errors, 0 Failures, 0 Warnings, 0 Deprecations (PHPUnit 10.5.64, PHP 8.3.30), gộp chung 59 tests / 79 assertions với CMS-003/CMS-005.

## [0.0.3] — CMS-003: DI Container (PSR-11)

### Added

- `core/Container.php` — DI Container tự viết, implements `Psr\Container\ContainerInterface` (PSR-11 thật, package `psr/container`). API: `bind()`, `singleton()`, `instance()`, `get()`, `has()`, `make()`. Auto-wiring qua Reflection (chỉ Constructor Injection), phát hiện Circular Dependency (chặn tại chỗ qua stack `resolving`, không đệ quy vô hạn), cache `ReflectionClass` theo instance. Singleton chỉ sống trong vòng đời 1 Container instance — không static, không Global Container.
- `core/ContainerException.php`, `core/BindingNotFoundException.php` (implements `NotFoundExceptionInterface`), `core/CircularDependencyException.php` (mang theo `getChain()`) — 3 exception cho mọi lỗi resolve của Container.
- `psr/container ^2.0` (require), `phpunit/phpunit ^10.5` (require-dev), autoload-dev `Tests\` → `tests/` trong `composer.json`.
- `tests/Core/ContainerTest.php` + `tests/Fixtures/*.php` — 12 Unit Test (resolve class/interface, singleton, auto-wiring đệ quy, circular dependency đúng kịch bản `UserService → RoleService → PermissionService → UserService`, binding not found, `instance()`, scalar có/không default, `make()`, `has()`).
- `phpunit.xml`.

### Verified

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — 0 Errors, 0 Failures, 0 Warnings, 0 Deprecations (PHPUnit 10.5.64, PHP 8.3.30), gộp chung 59 tests / 79 assertions với CMS-004/CMS-005.

## [0.0.2] — CMS-002: Config Loader

### Added

- `core/Config.php` — config loader instance-based, đọc toàn bộ `config/*.php` theo dot notation (`get()`, `has()`, `all()`).
- `config/app.php`, `config/database.php`, `config/cache.php`, `config/auth.php`, `config/tenants.php` — 5 file cấu hình gốc theo danh sách trong `cms-architecture-proposal.md` mục 2, giá trị đọc qua `getenv()` kèm default an toàn (không hard-code môi trường vào file version-controlled).

### Fixed

- `core/Config.php` viết ban đầu dùng static property/method (global state) — vi phạm "No Global Variable" / "Dependency Injection" trong `coding-standard.md`. Chuyển sang instance-based, nhận `configPath` qua constructor.
- Dọn thư mục thừa `config/core/` (rỗng, không thuộc cấu trúc đã định nghĩa).
- `public/index.php` (phát hiện khi review sau CMS-002, không thuộc task nào) vẫn gọi `Config::init()`/`Config::get()` kiểu static — gây lỗi runtime `Call to undefined method` sau khi `Config` refactor. Sửa sang `new Config(...)`; bỏ echo trực tiếp `database.connections.mysql.host` ra output công khai (rủi ro lộ thông tin hạ tầng).

## [0.0.1] — CMS-001: Project Skeleton

### Added

- Cấu trúc thư mục project theo `cms-architecture-proposal.md` mục 2: `app/` (Controllers, Services, Repositories, Models, Helpers, Middleware), `core/`, `modules/`, `plugins/`, `themes/`, `public/` (assets, uploads), `storage/` (cache, logs, framework), `resources/` (scss, js, images), `database/` (migrations, seeds), `config/`, `docs/guideline`.
- `composer.json` với PSR-4 autoload (`Core\` → `core/`, `App\` → `app/`, `Modules\` → `modules/`), yêu cầu PHP >=8.1.
- `.gitignore`.
- `TODO.md`, `CHANGELOG.md` khởi tạo để theo dõi tiến độ Phase/Task.

### Known limitations (toàn Phase 1, tính đến v0.0.5)

- Chưa có cơ chế nạp `.env` (`config/*.php` đọc `getenv()` nhưng chưa quyết định dotenv library hay set qua server) — chưa gây lỗi vì luôn có default, cần xử lý trước khi deploy production.
- `SqlCompiler::compileJoins()`/`QueryBuilder::join()` chỉ hỗ trợ tên cột trần (không hỗ trợ `table.column` để phân biệt cột trùng tên giữa 2 bảng) — phát hiện khi viết test CMS-004, ghi nhận là giới hạn hiện tại, chưa cần mở rộng vì chưa có nhu cầu thật.
