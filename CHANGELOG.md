# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/). Version nội bộ (chưa phát hành ra ngoài) đánh số theo Task hoàn thành: `CMS-00X` → `v0.0.X` — dùng làm mốc tiến độ Phase 1, không phải semver phát hành sản phẩm.

## [Unreleased]

### CMS-057 — CI Hardening: PHPStan + lint gate + coverage report

- Thêm PHPStan level 5 (`phpstan.neon`, quét `app/core/database/modules/plugins`) và bắt buộc `php-cs-fixer fix --dry-run` trong `.github/workflows/phpunit.yml` — chặn build khi lệch PSR-12 hoặc lỗi type. Sửa 83 file lệch line-ending (LF/CRLF) và 7 lỗi PHPStan thật (thiếu `@var` cho biến nạp động trong `routes.php`/`Hooks.php`, dead-code, `@phpstan-impure` cho `View::renderTemplate()`); ignore có phạm vi hẹp 1 trường hợp placeholder có chủ đích (`RateLimitMiddleware`, Owner Decision CMS-023).
- Bật `coverage: pcov` trong CI (trước đó `coverage: none`), chạy `phpunit --coverage-text` để theo dõi độ phủ test khi module/plugin tăng lên. Khai báo `<source>` trong `phpunit.xml` (`core`/`modules`/`plugins`) để giới hạn phạm vi đo coverage.

### CMS-058 — Bổ sung CsrfMiddleware cho module JSON API

- `modules/User`, `Role`, `Menu`, `Media`, `Seo` là "JSON API phẳng" nhưng Authorization xác thực hoàn toàn qua Session cookie (cùng session với `modules/Admin` đã có CSRF từ CMS-045) — không có JWT/Bearer middleware tách bạch nào như `config/auth.php` mô tả. Phòng thủ CSRF duy nhất trước đây chỉ dựa vào `SameSite=Lax`, không có token check độc lập theo Synchronizer Token Pattern (`core/Csrf.php`).
- Bọc `CsrfMiddleware` cho toàn bộ route ghi (POST/PATCH/DELETE) của 5 module trên, dùng `group()` giống pattern `modules/Admin`/`plugins/Ecommerce`. Cập nhật 5 test tích hợp tương ứng, gửi kèm `_token` qua `Core\Csrf::token()`.

### CMS-059 — Fix lỗ hổng leo thang đặc quyền khi gán System Role

- `modules/User/AssignRoleController.php` và `modules/Admin/UserAssignRoleController.php` chấp nhận cả System Role (`tenant_id NULL`) khi gán role cho user, trong khi `bin/bootstrap.php` gán TOÀN BỘ 41 permission cho System Role "Admin". Bất kỳ user nào chỉ cần quyền `user.assign_role` (quyền hẹp, đổi role trong phạm vi tenant) có thể tự gán hoặc gán cho người khác System Role này, leo thang lên toàn quyền hệ thống — ID role dễ đoán/dễ dò qua `GET /roles` ("View allowed" cho System Role).
- Thu hẹp điều kiện xác thực role từ `(tenant_id IS NULL OR tenant_id = ?)` xuống `tenant_id = ?` — chỉ cho gán Tenant Role, đồng bộ nguyên tắc System Role đã áp dụng ở `AssignPermissionController`/`EditRoleController`/`DeleteRoleController`. Thêm 2 test regression (`ModuleUserIntegrationTest::testAssignRoleRejectsSystemRole`, `AdminUserManagementUiTest::testAssignSystemRoleIsRejected`).

### CMS-060 — Xác minh MIME type thật qua `finfo_file()` khi upload media

- Không tin `Content-Type` client tự khai báo trong `$file['type']` (giả mạo được) — đọc lại magic byte thật qua `finfo_file()` trước khi lưu vào storage, áp dụng đồng bộ cho cả `Modules\Media\UploadMediaController` và `Modules\Admin\MediaUploadController`. Thêm header `X-Content-Type-Options: nosniff` khi serve file (`MediaServeController`/`MediaFileController`) để chặn MIME sniffing phía trình duyệt.

### CMS-061 — CSRF cho module Page + sanitize HTML content

- `modules/Page/routes.php` (POST/PATCH/DELETE: create/edit/delete/publish/set-homepage) thiếu `CsrfMiddleware`, khác với `modules/Admin`/`modules/Media` đã bao đầy đủ — cho phép giả mạo request tạo/sửa/xóa Page qua cross-site form submit từ session admin đang đăng nhập. Bổ sung group middleware đúng pattern đã có.
- Thêm `Core\Security\HtmlSanitizer` (tự viết bằng `DOMDocument`, không thêm dependency ngoài) — whitelist tag/attribute cho Page content dạng `content['html']`, lớp phòng thủ thứ 2 ở tầng lưu dữ liệu (phòng trường hợp sau này cấp quyền `page.create`/`page.update` cho role thấp hơn Admin). Tích hợp vào `CreatePageAction`/`UpdatePageAction` (dùng chung cho cả JSON API và Admin HTML).

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **869 tests, 1813 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression trên 825 test của `v0.3.0`.

## [0.3.0] — 2026-08-17 — CMS-056: Ecommerce MVP & Plugin Architecture (PHASE 19) + Payment Gateway Integration (PHASE 20)

### Added

- **Đóng Technical Debt #9** (ghi nhận từ CMS-012, `core-architecture.md`): bảng `site_plugins` mới (`tenant_id`, `plugin_key`, `is_active`, `activated_at`) + `core/PluginActivationService.php` (Cache-aware qua `Core\Cache` có sẵn, key `tenant:{id}:plugins:enabled`, tự invalidate khi `activate()`/`deactivate()`) — dùng chung cho **mọi** Plugin tương lai, không riêng Ecommerce.
- **`core/View.php`** mở rộng additive: thêm `$globalData` (constructor param thứ 4) — merge tự động vào mọi `render()`/`include()`, không cần từng Controller tự truyền. Phục vụ điểm mở `admin.menu.items` (Plugin tự bơm mục menu Admin qua Hook, không sửa `sidebar.php` mỗi lần thêm Plugin mới).
- **Hook mới `plugin.routes.register`** (`core/Application.php::boot()`): `PluginManager::boot()` trước Phase 19 chỉ nạp `Hooks.php` (không nạp `routes.php` như `ModuleManager`) — Plugin nay tự đăng ký Route qua action này, `$router` được truyền qua tham số bổ sung của `Hook::do()`.
- **Ecommerce Plugin — Plugin thật đầu tiên của dự án** (`plugins/Ecommerce/`): `plugin.json`/`Hooks.php`/`routes.php`, `Plugins\Ecommerce\EcommercePluginGuardMiddleware` (fail-closed 404 nếu tenant chưa kích hoạt plugin), `Services\{CartService,ProductService}` (Giỏ hàng Session-based, Danh sách sản phẩm Cache-aware), `Actions\{AddToCartAction,RemoveFromCartAction,PlaceOrderAction,UpdateOrderStatusAction}` (Action Class Pattern, tiền lệ `modules/Page/Actions/*`).
- **Schema Ecommerce**: `products`, `product_variants`, `orders` (Guest Checkout — `guest_name`/`guest_email`, tiền lệ Comment System), `order_items` (snapshot tên/giá tại thời điểm mua). Luồng trạng thái Đơn hàng qua cột `status` string (tiền lệ `pages.status`/`comments.status`, không ENUM/state machine riêng): `pending → processing → completed`, hoặc `pending/processing → cancelled`.
- **9 Controller Ecommerce** (Admin: Product CRUD ×5, Order List/Show/UpdateStatus ×3; Public: Shop ×2, Cart ×2, Checkout ×2) + **Admin Plugin Toggle UI** (`modules/Admin/{PluginList,PluginToggle}Controller.php`, `/admin/plugins`) — bật/tắt Plugin theo tenant.
- 8 permission mới: `product.{view,create,update,delete}`, `order.{view,update_status}`, `plugin.manage`.
- 6 file test mới, 37 test: `PluginActivationServiceTest` (8), `CartServiceTest` (7), `EcommerceProductManagementTest` (8), `EcommerceCheckoutTest` (4), `EcommerceOrderManagementTest` (6), `ApplicationPluginActivationIntegrationTest` (4).
- **Payment Gateway Integration (PHASE 20, triển khai chung commit với PHASE 19)**: `Plugins\Ecommerce\Services\Payment\PaymentManager` + `PaymentDriverInterface` (Driver Pattern, tiền lệ `Core\Mail\MailerDriver`/`Core\Cache\CacheDriver`) — 3 driver `CodPaymentDriver` (mặc định), `MomoPaymentDriver`, `VnPayPaymentDriver` (tự viết HMAC/HTTP thuần, Zero-dependency). Bảng `payments` mới (`tenant_id`, `order_id`, `driver`, `status`, `amount`, `transaction_ref`, `raw_payload`) tách khỏi `orders` — 1 order có thể có nhiều lượt thử thanh toán.
- `Plugins\Ecommerce\Controllers\Public\PaymentWebhookController` (Public, không CSRF — xác thực bằng chữ ký số HMAC riêng từng cổng) + `PaymentReturnController` — cập nhật idempotent theo `transaction_ref` UNIQUE, tránh xử lý trùng khi cổng gửi lại webhook. Order Notification qua Hook (`order.created`/`order.payment_completed`/`order.shipped`) tái dùng `Core\Mail\Mailer`/`modules/Admin/NotificationService.php` đã có từ Phase 15, không viết lại hạ tầng email/notification.
- 6 file test mới bổ sung cho Payment: `PaymentManagerTest`, `MomoPaymentDriverTest`, `VnPayPaymentDriverTest`, `PaymentWebhookControllerTest`, `OrderNotificationHookTest`, cùng mở rộng `EcommerceOrderManagementTest`/`EcommerceCheckoutTest`.

### Architecture Decisions (1 điều chỉnh quan trọng so với đặc tả gốc — phát hiện trước khi code)

- **Không thể lọc `enabledKeys` theo tenant tại `Application::boot()`** như đặc tả gốc yêu cầu — `boot()` chạy **trước** khi `TenantResolverMiddleware` xác định tenant của request (middleware đó chỉ chạy lúc `dispatch()`). `PluginManager::boot()` giữ nguyên hành vi nạp mọi Plugin đã discover (chỉ đăng ký route/hook, không lộ dữ liệu). Việc bật/tắt **thật** theo tenant chuyển sang enforce đúng lúc tenant đã biết — qua `EcommercePluginGuardMiddleware` gắn ở route group của Plugin (dispatch-time), vẫn đóng đúng Technical Debt #9, chỉ khác điểm thực thi.
- **`category` là field VARCHAR đơn trên `products`** (không bảng `product_categories` riêng) — Owner Decision YAGNI, chưa có yêu cầu cây danh mục lồng nhau.
- **Checkout dạng Guest** (không tài khoản Khách hàng) — nhất quán tiền lệ Comment System (Phase 14).

### Fixed (tự phát hiện qua `composer dump-autoload -o`, Owner chạy thật)

- **Lệch casing thư mục Plugin so với PSR-4**: namespace `Plugins\Ecommerce\...` nhưng thư mục vật lý ban đầu là `plugins/ecommerce/` (chữ thường) — lệch quy ước StudlyCase đã áp dụng nhất quán cho mọi `modules/*` (`Modules\Admin` ↔ `modules/Admin/`). Windows (NTFS case-insensitive) không phát hiện được qua PHPUnit thường, chỉ lộ ra qua `composer dump-autoload -o` (quét PSR-4 case-sensitive, cảnh báo "does not comply... Skipping") — sẽ crash "Class not found" thật trên Linux production nếu không sửa. Đổi tên thư mục thành `plugins/Ecommerce/` (khớp namespace), **giữ nguyên** mapping tổng quát `"Plugins\\": "plugins/"` trong `composer.json` (không khai báo mapping riêng từng Plugin — giữ đúng giá trị "Plugin cắm vào không cần sửa Core").

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64), sau `composer dump-autoload -o` (1793 class, 0 cảnh báo PSR-4): **825 tests, 1720 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression trên 788 test trước đó.

## [0.2.0] — 2026-08-16 — CMS-055: UI/UX Admin Dashboard Overhaul & Theme Engine Enhancement (PHASE 18)

### Added

- **Dark/Light Theme Engine** — `public/assets/css/variables.css` bổ sung block `:root[data-theme="light"]` (light-equivalent cho toàn bộ token màu hiện có: `--color-bg`, `--color-text-primary`, `--color-accent`...), giữ nguyên `:root` mặc định (Tech Green/Dark) không đổi 1 dòng nào. `public/assets/js/app.js` toggle qua `[data-theme-toggle]` (nút mới ở `topbar.php`), lưu lựa chọn vào `localStorage` (`cms-theme`), gán `[data-theme]` lên `<html>`. Script inline chống FOUC (Flash of Unstyled/wrong-theme Content) trong `<head>` của `main.php` — đọc `localStorage` và gán `data-theme` **trước khi** `app.js` (cuối `<body>`) tải xong.
- **5 Partial View dùng chung mới** (`themes/default/views/admin/partials/`): `breadcrumb.php`, `pagination.php`, `table_filter.php`, `flash_messages.php`, `confirm_modal.php` — mỗi partial tự `return;` sớm khi rỗng (an toàn tuyệt đối khi chưa có dữ liệu). Include vào `main.php` (`flash_messages`/`breadcrumb`/`confirm_modal` xuất hiện trên MỌI trang Admin), dùng lại tường minh ở `audit_logs/list.php` (`table_filter` + `pagination`).
- **Modal Xác nhận dùng chung** (`#confirm-modal`) thay `window.confirm()` thô — tái sử dụng cơ chế `.modal-overlay`/`[data-modal-open]`/`[data-modal-close]` đã có sẵn từ Media Upload Modal (Phase 7), không tạo cơ chế modal mới. Mọi `form[data-confirm="..."]` hiện có (Media/Comment/AuditLog/SystemSettings) tự động dùng modal mới, không cần sửa từng view.
- **Roles/Permissions — Checkbox/Status Matrix**: `roles/permissions.php` viết lại thành 1 bảng thống nhất (gộp `assigned`/`unassigned`, sort theo tên quyền), giữ nguyên hành vi System Role không render `<form>` và endpoint `POST /admin/roles/{id}/permissions` không đổi.
- **Media Manager — Grid/List Dual View**: `media/list.php` thêm toggle Grid/List thuần CSS (`.is-hidden`) + JS (`[data-view-toggle]`), tái dùng nguyên `$media` đã có, không sửa Controller.
- **Dashboard — Widget khung chờ** (Recent Audit Logs, System Health): dựng UI bọc `isset($recent_audit_logs)`/`isset($system_health)`, hiện chưa render gì (Controller `DashboardController` chưa cấp dữ liệu, xem mục Ghi chú bên dưới) — sẵn sàng bật ngay khi có 1 thay đổi Controller bổ sung thuần additive.
- 24 test mới: `tests/Core/ViewPartialTest.php` (14), 2 test bổ sung trong `tests/Core/AdminUiFoundationTest.php` (theme-toggle + confirm-modal trên layout admin thật đã đăng nhập) — cộng thêm số assertion tăng ở 2 test suite trên tổng cộng 24 test/1620 assertion cho toàn bộ Phase 18 so với 772 test trước đó (kết quả thật: 788 test).

### Architecture Decisions (2 Quyết định Kỹ thuật đã trình Owner duyệt trước khi code + 2 điều chỉnh tự phát hiện)

1. **Vanilla CSS/JS thuần, không Tailwind/AlpineJS** — giữ đúng tiền lệ Zero External Dependency xuyên suốt 18 Phase; theming ở tầng CSS Custom Properties (token), không build step, không npm.
2. **Matrix UI (Roles/Permissions) giữ nguyên endpoint** — chỉ đổi trình bày (1 bảng thay 2 danh sách), không đổi `action`/`method`/tên field của `<form>` đã có.
3. **Ràng buộc cứng: không sửa Controller** — mọi tính năng cần dữ liệu Controller chưa cấp (Dashboard widget) dựng dạng khung chờ bọc `isset()`, không giả lập dữ liệu, không phá vỡ nguyên tắc "View không truy vấn Database/Session trực tiếp".
4. **2 sửa đường dẫn thực tế trước khi viết code** — đặc tả gốc ghi `dashboard/index.php`/`media/index.php`, đường dẫn thật là `dashboard.php`/`media/list.php` (xác minh qua các lệnh `render()` thật trong `modules/Admin/*.php` trước khi tạo file, không tạo nhầm đường dẫn).

### Ghi chú cần Owner quyết định (không chặn release, không phải bug)

- **Dashboard Widget (Audit Log gần đây / System Health) hiện chưa hiển thị dữ liệu thật** — cần 1 thay đổi bổ sung thuần additive ở `DashboardController` (truyền thêm `$recent_audit_logs`/`$system_health` vào View) ở Phase sau nếu Owner muốn kích hoạt; UI đã sẵn sàng, không cần sửa lại View.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **788 tests, 1620 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.7] — 2026-08-15 — CMS-054: System Settings & General Configurations (PHASE 17)

### Added

- **`settings`** (migration `2026_08_15_000001_create_settings_table.php`): bảng key-value **tổng quát**, bổ sung cho `site_settings` (cột cố định, Phase 4) — `tenant_id` nullable (setting cấp hệ thống), `setting_group`, `key`, `value` (TEXT), `is_encrypted`. `UNIQUE (tenant_id, key)`, index `(tenant_id, setting_group)`.
- **`Modules\Settings\SettingManager.php`**: `get(key, default)`/`set(key, value, group, encrypted)`/`getGroup(group)`/`forget(key)`, **Cache-aware** qua `Core\Cache` có sẵn (đọc cache trước, chỉ chạm DB khi cache-miss; `set()`/`forget()` tự invalidate đúng 1 key). Mã hoá AES-256-CBC qua `ext-openssl` (có sẵn trong PHP core, không thêm dependency Composer) cho giá trị nhạy cảm (`is_encrypted=true`), khoá derive từ `app.key`.
- **Admin UI** (`GET/POST /admin/system-settings`, `POST /admin/system-settings/{id}/delete`, permission `settings.manage` mới): danh sách setting gom nhóm theo `setting_group`, giá trị `is_encrypted` **luôn hiện `********`** ở màn hình danh sách (không bao giờ giải mã hiển thị, kể cả khi có quyền).
- 21 test mới: `tests/Core/SettingManagerTest.php` (10), `tests/Core/AdminSettingTest.php` (11).

### Architecture Decisions (2 xung đột kỹ thuật tự phát hiện trước khi code, khác đặc tả gốc)

1. **Đổi tên bảng thành `settings`** — đặc tả gốc đề xuất `site_settings`, nhưng tên này **đã dùng** cho bảng cột-cố-định từ Phase 4 (`2026_08_09_000001_create_site_settings_table.php`); tạo lại cùng tên sẽ lỗi SQL "table already exists".
2. **Đổi route thành `/admin/system-settings`** — `GET`/`POST /admin/settings` **đã đăng ký** từ Phase 4 (`SettingShowEditController`/`SettingUpdateController`); đăng ký lại cùng URL sẽ khiến `Router` ném `DuplicateRouteException` ngay lúc boot ứng dụng, làm sập toàn bộ app chứ không riêng Phase 17.
3. **`SettingManager` đặt tại `Modules\Settings\`, không phải `core/`** — nhất quán tiền lệ `SiteSettingsManager`: đây là khái niệm nghiệp vụ (biết "tenant"), khác thành phần framework thuần như `Cache`/`Database`.
4. **`SettingManager` phụ thuộc `Core\Cache` → `Core\Cache\CacheDriver` là interface** — Container không tự auto-wire được (đúng bài học `MailerDriver` ở Phase 15); `tests/Core/AdminSettingTest.php` phải đăng ký tường minh `FileCacheDriver` trước khi giao (tự phát hiện trước khi chạy PHPUnit thật, không phải sau khi FAIL).

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **772 tests, 1586 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.6] — 2026-08-14 — CMS-053: Security & Audit Log System (PHASE 16)

### Added

- **`audit_logs`** (migration `2026_08_14_000001_create_audit_logs_table.php`): `tenant_id`/`user_id` **nullable** (khác mọi bảng trước — sự kiện `auth.login_failed` với email không tồn tại có thể xảy ra trước khi xác định được user), `event`, `auditable_type`/`auditable_id` (polymorphic), `old_values`/`new_values` (TEXT, JSON tự encode — Owner Decision CMS-040), `ip_address`, `user_agent`. FK `ON DELETE SET NULL` (giữ lại log ngay cả khi tenant/user bị xoá — đúng tinh thần compliance). Index `(tenant_id, created_at)`, `(tenant_id, event)`, `(tenant_id, user_id)`.
- **`core/Security/AuditLogger.php`**: Service inject qua constructor (`Database`, `Session`, `TenantManager`) — **không phải static facade**. `log(Request $request, string $event, ...)` nhận `Request` tường minh (không nằm trong Container, chỉ truyền qua `handle(Request $request)` của Controller). Silent-fail tuyệt đối qua `try/catch` nội bộ.
- **`modules/Admin/AuditLogController.php`** (`GET /admin/audit-logs`, permission `audit_log.view` mới): lọc theo `event`/`date_from`/`date_to`, **phân trang thủ công đầu tiên của dự án** (20 dòng/trang, chưa có helper Paginator chung — MVP tối giản).
- Tích hợp ghi log vào 8 Controller: `LoginController` (`auth.login_success`/`auth.login_failed`), `LogoutController` (`auth.logout`), `PageCreateController`/`PageUpdateController` (có diff `old_values`/`new_values`)/`PageDeleteController`, `CommentApproveController`/`CommentRejectController`, `SettingUpdateController` (diff toàn bộ settings).
- 17 test mới: `tests/Core/AuditLoggerTest.php` (8), `tests/Core/AdminAuditLogTest.php` (9).

### Fixed (tự phát hiện qua PHPUnit thật)

- 2 test tự viết dùng `assertStringNotContainsString()` kiểm tra toàn bộ `$response->getBody()` — dropdown lọc `<select name="event">` trong `list.php` **luôn liệt kê mọi event khác nhau của tenant** (đúng UX, không phải bug), khiến assertion đụng nhầm `<option>` trong dropdown thay vì dữ liệu bảng thật. Sửa bằng assertion nhắm chính xác `<span class="badge...">` (chỉ xuất hiện ở dòng dữ liệu) — cùng loại lỗi "assertion đụng UI chrome" đã gặp ở Phase 14.

### Architecture Decisions (3 điều chỉnh so với đặc tả gốc, đã trình bày ở Architecture Analysis)

- **`AuditLogger::log()` là instance method, không static** — dự án chỉ chấp nhận đúng 2 ngoại lệ static/global có chủ đích (`Session`, `Translator::globalInstance()`); thêm 1 static facade thứ 3 sẽ phá vỡ nguyên tắc "Không static/global mutable state" đã giữ từ CMS-002.
- **Bỏ event "đổi mật khẩu"** — tính năng đổi mật khẩu chưa tồn tại ở bất kỳ đâu trong 15 Phase trước (`UserUpdateController` chỉ sửa `name`/`email`); không tự tạo tính năng mới ngoài phạm vi Audit Log.
- **Không có ngoại lệ SuperAdmin xem xuyên-tenant** — cơ chế Super Admin chưa được xây dựng ở bất kỳ đâu (`config/tenants.php` chỉ khai báo `route_prefix` dự kiến, chưa có Route/Controller/Authorization nào dùng, Technical Debt đã ghi nhận từ CMS-030). `AuditLogController` cách ly tenant tuyệt đối, không ngoại lệ, đúng 100% mọi Controller Admin khác.
- **Zero-regression tự nhiên** (khác Phase 12-15 không cần sửa test cũ): `AuditLogger` là class cụ thể hoàn toàn (không interface như `MailerDriver`), Container tự auto-wire được ở mọi test cũ mà không cần đăng ký tường minh; kết hợp silent-fail nội bộ, không có test suite nào trong 736 test trước đó cần sửa.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **752 tests, 1538 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.5] — 2026-08-13 — CMS-052: Notification & Email System (PHASE 15)

### Added

- **`notifications`** (migration `2026_08_13_000001_create_notifications_table.php`): in-app notification cho Admin, polymorphic `notifiable_type`/`notifiable_id` (MVP chỉ `'comment'`), `read_at NULL` = chưa đọc (đúng quy ước cột timestamp nullable thay boolean, giống `pages.published_at`).
- **`config/mail.php`** (mới, theo khuôn `config/cache.php`): `default` (`env('MAIL_DRIVER','log')`), `from.{address,name}`, `drivers.{log,smtp}`.
- **`Core\Mail\MailerDriver`** (interface) + 3 driver: `LogMailerDriver` (ghi `storage/logs/mail.log` qua `Logger` có sẵn — không gửi thật, dùng local/test), `SmtpMailerDriver` (client SMTP **tự viết** qua `fsockopen()` + `stream_socket_enable_crypto()` cho STARTTLS — không thư viện ngoài, đúng Zero-dependency, EHLO/AUTH LOGIN/MAIL FROM/RCPT TO/DATA thuần văn bản), `ArrayMailerDriver` (test double, lưu email trong mảng — không phải driver production).
- **`Core\Mail\Mailer`** (facade duy nhất, cùng mô hình `Core\Cache`): render template qua `Core\View` có sẵn (không Engine mới), derive bản text qua `strip_tags()` (Owner Decision — không viết template `.txt` riêng). **Silent-fail tuyệt đối**: mọi `Throwable` bị bắt nội bộ, ghi qua `Logger` dùng chung, không bao giờ throw ra Controller.
- **`modules/Admin/NotificationService.php`**: `notifyAdmins()` (tạo notification in-app + gửi email cho mọi Admin thuộc tenant, tự bọc `Throwable` riêng cho phần ghi DB — độc lập với silent-fail của `Mailer`), `markAsRead()`, `unreadCount()`.
- 3 email template `themes/default/views/emails/{comment_new,comment_approved,comment_rejected}.php`.
- Tích hợp vào Phase 14: `CommentSubmitController` → `notifyAdmins()` sau khi tạo comment; `CommentApproveController`/`CommentRejectController` → JOIN `pages` lấy `guest_email`/`page_title`, gửi email qua `Mailer`.
- 21 test mới: `tests/Core/MailerTest.php` (8), `tests/Core/NotificationServiceTest.php` (7), `tests/Core/CommentNotificationIntegrationTest.php` (6).

### Fixed (tự phát hiện qua PHPUnit thật)

- **Tên file template dùng gạch ngang bị `View::resolvePath()` từ chối**: `NAME_PATTERN` của `Core\View` (`/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/`) không chấp nhận dấu `-` — 3 template ban đầu đặt tên `comment-new.php`/`comment-approved.php`/`comment-rejected.php` khiến `View::render()` throw `ViewNotFoundException` ngay ở bước validate tên (trước cả khi kiểm tra file tồn tại), `Mailer::send()` bắt và trả `false` âm thầm theo đúng thiết kế silent-fail — khiến lỗi đặt sai tên bị che giấu hoàn toàn. Đổi tên 3 file sang underscore (`comment_new.php`...), không sửa `Core\View` (giữ nguyên Core Component đã ổn định).
- 2 test tự viết đang PASS sai lý do cũng được sửa cùng lúc: `testMailerSendReturnsFalseWhenTemplateMissing` (dùng tên có gạch ngang nên pass nhờ regex-reject chứ không phải file-not-found thật) và `testMailerSendReturnsFalseWhenDriverThrows` (chưa từng chạm tới `driver->send()` vì template đã fail trước đó).

### Architecture Decisions

- **`MailerDriver` là interface → không auto-wire được** (khác `AnalyticsService`/`LocaleDetectionMiddleware` là class cụ thể ở Phase 12/13) — bắt buộc đăng ký tường minh trong `core/Application.php`, và 2 test suite Phase 14 cũ (`CommentSubmissionTest.php`, `AdminCommentModerationTest.php`) phải bổ sung `ArrayMailerDriver` vào `setUp()` để không vỡ khi Controller giờ cần `Mailer`.
- **Sync-only, không Queue/Worker** — dự án chưa có hạ tầng cron/worker nào, `Mailer` tự bọc try/catch nên gửi mail chậm/lỗi không làm crash request; để dành Queue thật (bảng `jobs` + `bin/queue-worker.php`) cho CMS riêng nếu traffic tăng.
- **`NotificationService` đặt tại `modules/Admin/`** theo đúng chỉ định Owner, dù bị gọi cross-module từ `Modules\Public\CommentSubmitController` — khác tiền lệ `AnalyticsService` (Phase 12) đặt ở thư mục riêng không thuộc module nào; không sai kỹ thuật (PSR-4 không phụ thuộc `module.json`), chỉ khác tổ chức thư mục.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **736 tests, 1499 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.4] — 2026-08-12 — CMS-051: Comment/Review System (PHASE 14)

### Added

- **`comments`** (migration `2026_08_12_000001_create_comments_table.php`): `tenant_id`, `entity_type`/`entity_id` (polymorphic, MVP chỉ `'page'` — đúng tiền lệ `seo_meta` CMS-043), `guest_name`, `guest_email` (không hiển thị công khai), `body`, `status` (`pending`|`approved`|`rejected`, mặc định `pending` — moderation-first), `ip_hash`. Index `(tenant_id, entity_type, entity_id, status)` và `(tenant_id, status)`. Không `parent_id` (không threaded-reply, khoá phạm vi MVP theo Architecture Analysis).
- **`modules/Public/CommentSubmitController.php`** (`POST /{slug}/comments`, route POST đầu tiên của Public module — cần `CsrfMiddleware` lần đầu ở module này): validate (`guest_name`/`guest_email`/`body` required, email đúng định dạng), anti-spam qua `core/RateLimiter.php` có sẵn (5 lần/10 phút/session, tái dùng nguyên, không CAPTCHA), `ip_hash` dùng kỹ thuật sha256+`app.key` giống `AnalyticsService` (Phase 12). Redirect kèm Flash Message (đúng quy ước CMS-017).
- **Public rendering** (`PublicPageController.php`): hiển thị comment `status='approved'`, Form gửi comment mới. **Chỉ áp dụng `PublicPageController`, không áp dụng `HomeController`** (Owner Decision phạm vi Phase 14).
- **Admin Moderation**: `CommentListController` (lọc `?status=`, mặc định `pending`), `CommentApproveController`, `CommentRejectController` (ẩn vĩnh viễn, không xoá — phục vụ chống gửi lại/điều tra spam), `CommentDeleteController` (hard delete — UGC/log, không soft-delete, đúng tiền lệ `analytics_views`). Menu "Comments" mới trong Sidebar.
- 3 permission mới: `comment.view`, `comment.moderate`, `comment.delete` (`bin/bootstrap.php`).
- 26 test mới: `tests/Core/CommentSubmissionTest.php` (9), `tests/Core/AdminCommentModerationTest.php` (10), `tests/Core/PublicCommentRenderingTest.php` (7).

### Fixed (tự phát hiện qua PHPUnit thật, 2 vòng)

1. **Regression tiềm ẩn ở `PublicPageController.php`**: gọi `Csrf::token()` vô điều kiện sẽ `throw SessionException` ở 2 test suite cũ chưa `Session::start()` (`PublicLandingPageTest`, `AnalyticsTrackingTest`) — sửa bằng cách chỉ sinh token khi `Session::isStarted()`, `null` khi chưa start (Form tự ẩn, không crash).
2. **`RouteNotFoundException`**: test nhét query string thẳng vào URI thay vì dùng tham số `query` riêng của `Request`.
3. **Assertion sai do trùng chữ với UI**: `guest_name` test trùng label nút filter tab ("Cho duyet"/"Da duyet") — label này luôn xuất hiện trong HTML bất kể đang lọc status nào, gây `assertStringNotContainsString` fail sai lý do.
4. **419 thay vì 404** ở test cách ly tenant: `list.php` chỉ render `_token` bên trong vòng lặp từng dòng comment — tenant chưa có comment nào thì không có token nào để parse từ HTML. Sửa bằng lấy CSRF token trực tiếp từ `Core\Csrf::token()` qua Container thay vì parse HTML (đồng bộ kỹ thuật với `CommentSubmissionTest.php`).

### Architecture Decisions

- **Không JSON Column cho rating/sao** — MVP khoá cứng chỉ Comment văn bản thuần, không rating aggregate (trục dữ liệu khác, để dành CMS riêng nếu có nhu cầu thật).
- **`status='pending'` mặc định** (moderation-first), **`guest_email` không bao giờ render công khai**, **RateLimiter 5 lần/10 phút/session** — 3 quyết định Owner đã duyệt qua Architecture Analysis trước khi code.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **715 tests, 1446 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.3] — 2026-08-11 — CMS-050: Multi-language Support (i18n) & Localization MVP (PHASE 13)

### Added

- **`page_translations`** (migration `2026_08_11_000001_create_page_translations_table.php`): `tenant_id`, `page_id` (FK `pages` CASCADE), `locale`, `title`, `slug`, `content` (TEXT, nullable), `created_at`, `updated_at`. `CONSTRAINT unq_page_locale UNIQUE (page_id, locale)`, `CONSTRAINT unq_tenant_locale_slug UNIQUE (tenant_id, locale, slug)`, index `(tenant_id, locale)`. **Translation Table Pattern** — chọn sau 1 vòng Architecture Review riêng so sánh với JSON Column Pattern (thắng ở cả 5/5 tiêu chí: Index/Query, mở rộng locale, độ phức tạp code, backward compatibility, DX bảo trì).
- **`core/I18n/Translator.php`**: dịch tĩnh UI text từ `resources/lang/{locale}.php`, fallback locale (`vi`), interpolation `:placeholder`. Có 1 static holder cô lập (`globalInstance()`/`setGlobalInstance()`) — ngoại lệ có chủ đích duy nhất phục vụ helper toàn cục `__()` (View template không có Dependency Injection), cùng triết lý cô lập global access như `Session` đã áp dụng cho `$_SESSION`.
- **`__()` helper** (`core/helpers.php`, nạp qua `composer.json` → `autoload.files` — file định nghĩa global function DUY NHẤT của dự án).
- **`core/Middleware/LocaleDetectionMiddleware.php`**: thứ tự ưu tiên route param `{locale}` → query `?lang=` → Session (`locale.current`) → Cookie → `config('app.locale')`. Gắn vào `/`, `/{slug}` (fallback không-prefix) và nhóm route mới `/{locale}`, `/{locale}/{slug}`.
- **Public rendering đa ngôn ngữ** (`modules/Public/HomeController.php`, `PublicPageController.php`): tra `page_translations` theo `(tenant_id, page_id, locale)`, tự động fallback nội dung gốc `pages` khi thiếu bản dịch hoặc locale mặc định `vi` (locale `vi` không bao giờ đụng `page_translations` — đảm bảo 100% backward compatible).
- **Admin UI**: Tab chuyển đổi "Tiếng Việt (gốc)/English" trong `create.php`/`edit.php` (bọc quanh khối Nội dung hiện có, không đổi logic Quill/Block Builder Phase 11 bên trong). `PageCreateController`/`PageUpdateController` lưu/upsert `page_translations`; `PageShowEditController` fetch bản dịch hiện có để pre-fill.
- 23 test mới: `tests/Core/I18nTranslatorTest.php` (9), `tests/Core/LocaleMiddlewareTest.php` (8), `tests/Core/PageTranslationTest.php` (6).

### Architecture Decisions

- **Translation Table Pattern, không JSON Column** — nhất quán với Owner Decision CMS-040 (`pages.content` là `TEXT` thuần, tránh phụ thuộc khả năng JSON column khác nhau giữa SQLite/MySQL); `core/QueryBuilder.php` vốn không có bất kỳ hỗ trợ JSON-query nào.
- **`{slug}` trong URL luôn là slug gốc (tiếng Việt)**, không đổi theo locale — tra bản dịch theo `page_id` đã xác định, không theo slug riêng từng locale, tránh 2 hệ thống slug song song.
- **Router match Route trước, chạy Middleware sau** (xác minh trực tiếp `core/Router.php`) — `LocaleDetectionMiddleware` không thể tự "cắt prefix URL", phải đăng ký thêm 1 nhóm route `/{locale}/...` để `{locale}` trở thành route param thật.
- **Rủi ro khớp Route 2-segment** (mở rộng Technical Debt 1-segment đã chấp nhận ở CMS-044): `/{locale}/{slug}` về cấu trúc có thể khớp `/admin/pages` — an toàn ở thứ tự nạp module hiện tại (`admin` nạp trước `public`) nhưng là thứ tự ngẫu nhiên theo tên thư mục, không phải bất biến hệ thống đảm bảo.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **689 tests, 1389 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.2] — 2026-08-03 — CMS-049: Advanced Analytics Dashboard (PHASE 12)

### Added

- **`analytics_views`** (migration `2026_08_10_000001_create_analytics_views_table.php`): `tenant_id`, `page_id` (nullable, FK `pages(id)` `ON DELETE SET NULL`), `path`, `ip_hash`, `user_agent`, `referrer`, `created_at`. Index kép `(tenant_id, created_at)` và `(tenant_id, path)`.
- **`modules/Analytics/AnalyticsService.php`** (Service Layer, cùng mô hình `SiteSettingsManager` — PHASE 4): `track()` ghi 1 lượt xem (resolve `page_id` theo slug/homepage, hash IP bằng `sha256` + `app.key` làm salt — không lưu IP thô); `totalViews()`/`uniqueVisitors()`/`topPages()`/`topReferrers()`/`dailyViews()` đọc thống kê theo chu kỳ `24h`/`7d`/`30d`, **mọi câu query đều lọc `tenant_id = ?`** (Multi-tenancy Isolation). Không tạo `module.json`/`routes.php` riêng — class không có HTTP endpoint, chỉ autoload PSR-4 + Container auto-wiring.
- **`core/Middleware/AnalyticsTrackingMiddleware.php`**: gắn vào 2 route `/` và `/{slug}` trong `modules/Public/routes.php` (route `/search` không gắn). Chạy như "After" middleware — chỉ ghi log khi Response status `200` (bỏ qua 404/lỗi). **Silent-fail tuyệt đối**: bọc toàn bộ `track()` trong `try/catch (\Throwable)`, không bao giờ làm gián đoạn Response thật của khách.
- **Admin Dashboard nâng cấp** (`modules/Admin/DashboardController.php`, `themes/default/views/admin/pages/dashboard.php`): 2 stat card mới (Tổng lượt xem/Khách truy cập độc nhất trong 7 ngày), bảng Top Pages, biểu đồ cột SVG thuần 7 ngày gần nhất (Zero External JS Chart Library — không dùng Chart.js/D3 hay bất kỳ thư viện ngoài nào).
- `tests/Core/AnalyticsTrackingTest.php` (5 test), `tests/Core/AdminAnalyticsUiTest.php` (5 test).

### Fixed (3 điểm tự phát hiện/đối chiếu source thật trước khi code, khác yêu cầu ban đầu của Owner)

1. **`routes/web.php` không tồn tại** trong dự án (route đăng ký theo từng module qua `ModuleManager`) — gắn middleware vào `modules/Public/routes.php` thay vì đường dẫn Owner đề xuất.
2. **Trùng tên migration** với `2026_08_03_000001_create_media_table.php` đã tồn tại — đổi thành `2026_08_10_000001_create_analytics_views_table.php` (tiếp theo migration mới nhất).
3. **`tests/Core/RealMigrationsTest.php`**: bổ sung `'2026_08_10_000001_create_analytics_views_table'` vào hằng số dùng chung `EXPECTED_ORDER` — 3 test (`testMigrateCreatesAllSevenTablesInOrder`, `testRollbackDropsAllTablesInReverseOrder`, `testMigrateIsIdempotentAfterRollback`) tự động cập nhật theo, không sửa từng test riêng lẻ.
4. **Phòng ngừa regression `AdminUiFoundationTest.php`** (fixture cũ không có bảng `analytics_views`): bọc toàn bộ lệnh gọi `AnalyticsService` trong `DashboardController` qua `fetchAnalyticsSummary()` với `try/catch (\Throwable)`, fallback `0`/`[]` — cùng nguyên tắc đã áp dụng cho bảng `media` ở PHASE 11.

### Architecture Decisions

- **`ip_hash` không phải crypto-secure** — mục đích ẩn danh thống kê (anonymize), không phải bảo mật, dùng `sha256(ip . app.key)`.
- **Cutoff thời gian tính bằng PHP (`date('Y-m-d H:i:s', time() - N)`), không dùng hàm ngày-giờ SQL đặc thù driver** — nhất quán với triết lý Portable SQL đã chốt ở CMS-028 (SQLite/MySQL cùng hoạt động).
- **`dailyViews()` luôn trả đủ N ngày** (kể cả ngày 0 lượt xem) — biểu đồ SVG cần trục X liên tục, không bỏ trống ngày không có dữ liệu.

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **666 tests, 1341 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.1] — 2026-08-03 — CMS-048: Visual Page Builder (PHASE 11)

> **Lưu ý ID**: nhánh Git của Phase này đặt tên `feature/CMS-034-visual-page-builder`, nhưng `CMS-034` **đã được dùng** cho "Module System Bootstrap (Auth Module)" từ trước (xem `[0.0.34]` bên dưới, `core-architecture.md` mục 3.27). Task này được đánh số lại đúng thứ tự thật là **CMS-048** (tiếp theo CMS-047) trong tài liệu — không đổi tên nhánh Git đã checkout.

### Added

- **Block Builder cho Admin Page** — `public/assets/js/page-builder.js` (Vanilla JS, không dependency ngoài): quản lý mảng block, kéo-thả sắp xếp lại (HTML5 native drag-drop, cùng kỹ thuật Menu Builder PHASE 3.3), serialize vào input ẩn `content_blocks_json` trước khi submit form.
- **6 loại block MVP**: `heading`, `paragraph`, `image`, `hero`, `feature_grid`, `cta`.
- **Chế độ soạn thảo kép** trên form Tạo/Sửa Page: toggle "Rich Text" (Quill.js có sẵn) ↔ "Block Builder" (mới) qua input ẩn `editor_mode`, mặc định tính theo nội dung hiện có (`content['blocks']` tồn tại → mở sẵn ở chế độ Block).
- `themes/default/views/admin/pages/pages/blocks/_builder.php` (partial UI) + cập nhật `create.php`/`edit.php`.
- `modules/Admin/PageCreateController.php`, `PageUpdateController.php`: decode + validate `content_blocks_json` phía server khi `editor_mode='block'` — sai type hoặc `media_id` không thuộc tenant hiện tại → **từ chối toàn bộ, silent-redirect** (Owner Decision, cùng nguyên tắc `PagePublishController`), không lưu một phần.
- `themes/default/views/pages/default.php`: render 6 loại block ra đúng CSS class (`.hero`, `.feature-grid`/`.feature-card`, `.cta-footer`...) bằng **closure cục bộ** `$renderBlock` (không phải `function` top-level — tránh Fatal "Cannot redeclare" đã ghi nhận từ Menu Builder PHASE 5, xem mục 3.40).
- `modules/Public/HomeController.php`, `PublicPageController.php`: thêm `resolveBlockImageUrls()` — resolve `media_id → URL` cho block `image` **trước khi** đưa vào View (View không được phép truy vấn Database, nguyên tắc kiến trúc xuyên suốt dự án).
- `tests/Core/AdminPageBuilderTest.php` (9 test), `tests/Core/PublicPageBuilderRenderingTest.php` (6 test).

### Architecture Decisions

- **Không migration, không sửa JSON API/Action Class** — `pages.content` vốn là cột JSON tự do (`'content' => 'nullable|array'`, Owner Decision CMS-040), quy ước mới `{"blocks": [...]}` cùng tồn tại với `{"html": ...}` (Quill) và `{"text": ...}` (legacy) mà không cần đổi schema.
- **Transport 1 input JSON ẩn** (`content_blocks_json`), không dùng mảng bracket-input nhiều field — giảm độ phức tạp parse phía Controller.
- **Trùng lặp `decodeAndValidateBlocks()`/`VALID_BLOCK_TYPES`** giữa `PageCreateController`/`PageUpdateController` — có chủ đích, đúng tiền lệ Action Class Pilot (PHASE 6) và Admin Page/Media/Menu Controller trước đó.

### Fixed (2 vòng Root Cause Analysis từ kết quả PHPUnit thật do Owner chạy)

1. **`tests/Core/PublicPageBuilderRenderingTest.php`**: `Database::class` không có method `lastInsertId()`/`getPdo()` (chỉ có `connection(): PDO` và `insert()` — `insert()` đã tự trả về id mới). Đề xuất ban đầu từ Owner (`getPdo()->lastInsertId()`) cũng sẽ lỗi vì `getPdo()` không tồn tại — xác minh trực tiếp `core/Database.php` trước khi sửa, dùng thẳng giá trị trả về của `insert()`.
2. **`no such table: media`** ở 3 test suite cũ (`AdminPageManagementUiTest`, và các test cũ khác không tạo bảng `media` trong fixture `migrate()`)** — do Phase 11 thêm câu query `media` không điều kiện vào `PageShowCreateController`/`PageShowEditController`/`PageCreateController::renderWithErrors()`/`PageUpdateController::renderWithErrors()` (4 điểm, phát hiện qua 2 vòng chạy PHPUnit thật, vòng đầu bỏ sót 2 điểm `renderWithErrors()`). Bọc `try { ... } catch (\Throwable) { $images = []; }` quanh cả 4 điểm — không migration bảng `media` vào các fixture test cũ (giữ nguyên phạm vi PHPUnit gốc của các test đó).

### Verification

`vendor/bin/phpunit` toàn bộ suite trên môi trường thật (PHP 8.3.30, PHPUnit 10.5.64): **656 tests, 1305 assertions, 0 Errors, 0 Failures, 4 Skipped** (Redis, đúng thiết kế) — không regression.

## [0.1.0-beta] — 2026-08-02 — Beta Release Readiness & Staging Walkthrough (PHASE 8)

> Mốc đóng gói Beta — tổng hợp lại toàn bộ PHASE 7 (đã tag riêng `v0.0.59`) cùng PHASE 8 mới, đánh dấu sản phẩm đã sẵn sàng trình diễn khách hàng doanh nghiệp và triển khai Staging thật. Không sửa code PHP nào trong PHASE 8 — chỉ 2 tài liệu vận hành mới.

### Added — PHASE 8 (mới)

- **`DEMO_WALKTHROUGH.md`**: kịch bản Demo 4 bước cho Sales/Founder — (1) Public Landing Page Showcase (Hero/Feature Grid/Responsive 3 cấp độ), (2) Multi-Tenant Real-time Switch (`cms.test` ↔ `restaurant.test`, điểm bán hàng cốt lõi), (3) Admin Dashboard & Content Management (4 Metric Card, Activity Stream, Quill.js, Upload Media), (4) Dynamic Menu Builder & SEO Meta Automation (kéo-thả AJAX, OG/JSON-LD qua View Source, Sitemap/Robots tự sinh). Mỗi bước: Mục tiêu → Thao tác → Key Selling Points → Điều kiện chuẩn bị.
- **`STAGING_CHECKLIST.md`**: checklist thao tác triển khai Staging/VPS — Web Server Setup (kèm tạo `public/.htaccess` cho Apache), SSL/HTTPS Multi-Domain (chứng chỉ SAN đa-domain qua `certbot -d ... -d ...`, không dùng Wildcard vì domain tenant độc lập không cùng gốc), Data Initialization (chuỗi lệnh đúng thứ tự phụ thuộc: `migrate` → `bootstrap` (1 lần) → `seed_demo` Tenant 1 → `add_site` Tenant 2 → `seed_demo` Tenant 2), Permissions & Environment.

### Architecture Decisions — PHASE 8

- **Chứng chỉ SAN đa-domain, không Wildcard**: `TenantResolverMiddleware` khớp domain chính xác tuyệt đối, không phải subdomain của 1 domain gốc — Wildcard Certificate không áp dụng được cho mô hình multi-tenant của dự án.
- **Đính chính**: `public/uploads/` (tồn tại vật lý, còn trong `.gitignore` cũ) **không** phải nơi lưu Media thật — `STAGING_CHECKLIST.md` chỉ liệt kê phân quyền cho `storage/app/media/` (đúng nơi `UploadMediaController` thực sự ghi file), tránh lan truyền nhầm lẫn đường dẫn sang tài liệu vận hành.

### Recap — PHASE 7: UI/UX Demo Polish & Enterprise Showcase Pack (đã tag `v0.0.59`, xem chi tiết ở mục riêng bên dưới)

- Public Landing Page (Hero/Feature Grid/Showcase/CTA qua `content['html']`, CSS3 thuần, breakpoint Tablet mới).
- Enterprise Seeder 2 content pack thật (`tech`/`restaurant`) + `bin/add_site.php` (Tenant thứ 2 trở đi, tái dùng Admin/Role hệ thống).
- Admin Dashboard: `page_count`/`media_count`/Activity Stream (UNION Page+Media+User).
- 2 lần Root Cause Analysis bác bỏ đề xuất sai từ Owner (RCA đúng: test fixture thiếu `created_at`, không phải `registered_at`).

### Quality Assurance

- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 641 tests, 1264 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế). Không regression sau khi bổ sung 2 tài liệu Markdown (không đụng code PHP).

## [0.0.59] — 2026-08-02 — UI/UX Demo Polish & Enterprise Showcase Pack (PHASE 7)

### Added

- **Public Landing Page**: `public/assets/css/public.css` bổ sung `.hero-cta`, `.hero-mockup`, `.feature-grid`, `.feature-card`, `.showcase-block`, `.cta-footer` (CSS3 thuần, dùng token Design System sẵn có, không thêm dependency) + breakpoint Tablet (`max-width: 992px`) cho Feature Grid/Showcase co lại 2 cột/1 cột.
- **Enterprise Seeder** (`bin/seed_demo.php`, viết lại hoàn toàn): 2 content pack thật — `tech` (SaaS CMS Technology Co. — Hero/6 Feature Card/Showcase/CTA/bài blog) và `restaurant` (Green Gourmet Restaurant & Cafe — ngành F&B). Nhận tham số `[domain] [pack]`, tương thích ngược với cách gọi cũ không tham số. Xóa toàn bộ text kỹ thuật ("Test Page", nội dung mẫu chung chung).
- **`bin/add_site.php`** (mới): tạo Tenant thứ 2 trở đi — tái sử dụng Admin User + System Admin Role đã có (`roles.tenant_id IS NULL`), chỉ thêm `sites`/`site_domains`/`user_site_roles`. Không sửa `bin/bootstrap.php` (chỉ chạy được 1 lần, giữ nguyên).
- **Admin Dashboard nâng cấp**: `modules/Admin/DashboardController.php` bổ sung `page_count`, `media_count`, Activity Stream (UNION `pages`+`media`+`users` theo thời gian gần nhất, `LIMIT 8`). `themes/default/views/admin/pages/dashboard.php`: 4 Metric Card, Quick Action Bar (Tạo trang/Tải Media/Cấu hình SEO/Xem Public Site), bảng Activity Stream.
- `tests/Core/PublicLandingPageTest.php` (mới, 4 test) — dùng `themes/default/` thật (không phải fixture) để kiểm chứng render Landing Page.

### Refactored & Changed

- `themes/default/views/pages/default.php`: khi nội dung dùng `content['html']`, **không** render `<h1>{title}</h1>` chung nữa (tránh trùng lặp với heading riêng trong Hero Section) — trang `content['text']`/scalar giữ nguyên hành vi cũ.
- `SETUP_LOCAL.md`: +mục 12 hướng dẫn demo Multi-tenant 2 domain (`cms.test` + `restaurant.test`).

### Fixed

- **Root Cause Analysis — bác bỏ RCA sai từ Owner (lần thứ 2 trong dự án)**: Owner đề xuất đổi `DashboardController.php` sang `users.registered_at AS created_at`, cho rằng bảng `users` dùng cột `registered_at`. Xác minh trực tiếp migration thật (`database/migrations/2026_08_01_000003_create_users_table.php:21`) xác nhận **cột `users.created_at` tồn tại thật, không có cột `registered_at` nào trong toàn bộ codebase** — áp dụng đề xuất sẽ khiến code tham chiếu cột không tồn tại. Nguyên nhân thật: bảng `users` tự tạo riêng trong `tests/Core/AdminUiFoundationTest.php::migrate()` thiếu cột `created_at` (sót lại từ trước Phase 7, chỉ lộ ra khi `DashboardController` bắt đầu query cột này). Sửa đúng 1 dòng trong fixture test — **không đụng `DashboardController.php`**.
- Bổ sung bảng `pages`/`media` còn thiếu trong `tests/Core/AdminUiFoundationTest.php::migrate()` (tự phát hiện trước khi giao Owner test) — `DashboardController` giờ luôn query 2 bảng này.

### Architecture Decisions

- **Nội dung Landing Page qua `content['html']` có sẵn** (Fork A1) — không sửa schema/Admin UI, 2 Tenant tự nhiên có nội dung khác nhau vì mỗi tenant có `content` riêng trong `pages`.
- **Mở rộng `bin/seed_demo.php` procedural** (Fork B1) — không tạo pattern Seeder Class mới (`database/seeders/`), giữ đúng convention `bin/` đã dùng xuyên suốt.
- **Activity Stream chỉ Page + Media + User** (Fork C1) — `menus`/`menu_items` không có cột timestamp nào, đưa vào sẽ cần migration mới, vượt phạm vi "KHÔNG migration" đã khóa cho Phase 7.

### Verification

- `vendor/bin/phpunit tests/Core/AdminUiFoundationTest.php` trên môi trường thật: **PASS** — 8 tests, 24 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 641 tests, 1264 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.58] — 2026-08-02 — Release Preparation & Technical Debt Clearance (PHASE 6)

### Added

- **CI/CD**: `.github/workflows/phpunit.yml` — GitHub Actions, matrix PHP 8.2 + 8.3 (`pdo_sqlite`, `mbstring`), chạy trên push/PR vào `main`/`develop`. Không thêm PHP 8.1 dù `composer.json` cho phép `>=8.1` — chưa từng verify thật.
- **`DEPLOYMENT.md`**: hướng dẫn triển khai Production — Yêu cầu hệ thống, Web Server Single Domain Multi-tenancy (Nginx/Apache), Multi-tenancy DNS, Environment Variables (bảng đối chiếu bắt buộc đổi so với `.env.example` demo), File Storage Permissions, Database Migration an toàn, Cronjobs (xác nhận chưa có tác vụ định kỳ nào), Checklist Go-Live.
- **Pilot Action Class Pattern** (`modules/Page/Actions/{PageActionException,PageNotFoundException,PageValidationException,CreatePageAction,UpdatePageAction,DeletePageAction,PublishPageAction,SetHomepageAction}.php`): tách business logic (validate + DB) khỏi tầng Response — thí điểm trên Module `Page`, giải quyết trùng lặp Admin↔JSON API đã lặp lại ≥7 lần từ CMS-045.

### Changed

- Refactor 10 Controller Module `Page` (5 JSON API + 5 Admin UI) inject Action qua Constructor Injection, giữ nguyên tầng Response (JSON vs HTML/redirect).
- `.gitignore`: bổ sung `/storage/app/media/*` (giữ `.gitkeep`) — phát hiện thư mục upload thật chưa từng được gitignore đúng cách.

### Fixed

- **Đính chính tài liệu cũ sai** trong `core-architecture.md` (mục Role Model, gần CMS-037): từng ghi "migration không có cột `is_system`" — sai, đọc lại migration thật (`2026_08_01_000004_create_roles_table.php:19`) xác nhận cột **tồn tại**, chỉ không Controller nào dùng.
- 1 lỗi gõ nhầm tự phát hiện trong lúc refactor: `PageSetHomepageController.php` (Admin) — sai status code `403 Forbidden` thành `404`, sửa lại đúng trước khi giao test.
- 1 vi phạm convention tự phát hiện: 2 Exception mới (`PageNotFoundException`/`PageValidationException`) ban đầu extend thẳng `\RuntimeException` — bổ sung base `PageActionException` cho khớp convention `Core\Database\{DatabaseException,QueryException}`.

### Documentation & Maintenance

- Đánh dấu chính thức `roles.is_system` deprecated (giữ nguyên cột, không `DROP COLUMN` — Owner Decision).
- Đánh dấu trạng thái Standby chính thức cho `core/Cache.php`/`core/Hook.php` (không Module nào dùng qua toàn bộ vòng đời dự án).
- Đóng dứt điểm nợ tài liệu tồn đọng từ v0.0.51 (Local Demo Foundation, 2 Critical Bug Fix, UI Kit Tech Green/Dark) trong `TODO.md`.

### Architecture Decisions

- **Chỉ pilot 1 nghiệp vụ (`Page`), chưa nhân rộng** ra 24 cặp Controller Admin↔JSON còn lại — quyết định nhân rộng hoãn tới khi Owner đánh giá kết quả pilot thật.
- **2 discrepancy hành vi nhỏ giữa JSON/Admin gốc đã hợp nhất** (xác nhận an toàn qua đọc trực tiếp test suite trước khi hợp nhất — không test nào assert vào 2 nhánh này): xử lý `template`/`parent_id` rỗng khi Create (theo hướng Admin, chặt hơn); cấu trúc `errors` cho lỗi slug trùng/parent không hợp lệ (theo hướng Admin, đầy đủ hơn — JSON API giờ trả thông tin lỗi chi tiết hơn trước).

### Verification

- `vendor/bin/phpunit tests/Core/ModulePageIntegrationTest.php` trên môi trường thật: **PASS** — 17 tests, 27 assertions.
- `vendor/bin/phpunit tests/Core/AdminPageManagementUiTest.php` trên môi trường thật: **PASS** — 11 tests, 24 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 636 tests, 1247 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế) — **không regression** sau khi refactor 10 Controller đã chạy production.

## [0.0.57] — 2026-08-02 — Public Engine Polish & Public Media Delivery (PHASE 5)

### Added

- **Public Media Serve Route** (`GET /media/{filename}`, `modules/Media/MediaServeController.php`): phục vụ file `storage/app/media/*` công khai, kèm `Cache-Control`/`ETag`/`Last-Modified`. `tenant_id` **luôn** lấy từ `TenantManager::id()` (domain) — không nhận từ URL, chặn IDOR (đã sửa từ đề xuất gốc `/media/{tenant_id}/{filename}` qua Architecture Analysis). `{filename}` khớp theo `media.path` (tên file vật lý duy nhất do `uniqid()` sinh), không phải `media.file_name` (tên gốc, không duy nhất).
- **Public Search** (`GET /search?q=`, `modules/Public/SearchController.php`): `LIKE` trên `title`/`content`, `status=published`, `deleted_at IS NULL`, scoped tenant, `LIMIT 50` (hằng số nối trực tiếp, không bind). View `pages/search.php` escape `$query` qua `$this->e()`.
- **Breadcrumb** (`modules/Public/BreadcrumbBuilder.php`): walk ngược `pages.parent_id`, giới hạn `MAX_DEPTH=20` chống vòng lặp vô hạn từ dữ liệu hỏng. Tách class dùng chung giữa `HomeController`/`PublicPageController` (khác tiền lệ trùng lặp method — do logic phức tạp hơn `fetchNavigation()`).
- **Public SEO Header Integration**: `<meta name="robots">` (dựa `seo_meta.is_index`/`is_follow`, mặc định `index,follow` khi chưa có `seo_meta`), `<link rel="icon">` (từ `site_settings.favicon_id`), `og:image` fallback (`seo_meta.og_image_id` → `site_settings.default_og_image_id`) — cả 2 đều resolve qua Media Serve Route mới.

### Fixed

- **Root Cause Analysis đúng vs RCA đề xuất sai**: 2 FAILURE tại `PublicSearchTest` (`testSearchExcludesDeletedPages`, `testSearchLimitsResultsTo50`) — xác minh trực tiếp source **không phải lỗi SQL** (`SearchController.php` đã có sẵn `deleted_at IS NULL` và `LIMIT 50` đúng từ đầu). Nguyên nhân thật: view `pages/search.php` luôn echo lại chính từ khóa tìm kiếm (`<p>Query: {q}</p>`), khiến assertion trên chuỗi thô bị nhiễu (đếm trùng/match giả). Sửa 2 assertion trong test dùng tiền tố markup `<p>{title}</p>` thay vì chuỗi thô — không đụng `SearchController.php`.
- Sai vị trí tham số `query` khi dựng `new Request(...)` trong `PublicSearchTest.php` (constructor thật: `method, uri, host, query, body, headers, routeParams, files`) — rà soát và sửa toàn bộ.

### Architecture Decisions

- **Loại bỏ `tenant_id` khỏi URL Media Serve Route** (khác đề xuất ban đầu) — phát hiện qua Architecture Analysis: mọi nơi khác trong hệ thống đều resolve tenant qua domain (`TenantResolverMiddleware`), đặt `tenant_id` làm tham số URL do client cung cấp sẽ tạo IDOR (đổi số trong URL để xem file tenant khác).
- **Chỉ `Cache-Control`/`ETag`/`Last-Modified`, không làm `304 Not Modified` thật** ở Phase 5 (Owner Decision, MVP).
- **`BreadcrumbBuilder` tách class riêng** (ngoại lệ thứ 2 sau `SiteSettingsManager` — nhưng đây không phải Service nghiệp vụ, chỉ là hàm dùng chung thuần, không ghi dữ liệu).
- **Không sửa `modules/Media/{ListMediaController,UploadMediaController,UpdateMediaController,DeleteMediaController}.php`, `core/*`** — `MediaServeController` là Controller hoàn toàn mới, không copy/sửa logic cũ.
- `modules/Public/module.json` thêm `"media"` vào `dependencies` (đúng pattern Route Collision Resolution đã dùng cho `"settings"` ở Phase 4).

### Verification

- `vendor/bin/phpunit tests/Core/{PublicMediaServeTest,PublicSearchTest,PublicPageRenderingTest,ModuleSettingsIntegrationTest}.php` trên môi trường thật: **PASS**.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 636 tests, 1247 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.56] — 2026-08-02 — Global Settings Module & SEO Infrastructure (PHASE 4)

### Added

- **Global Settings Module & Service Layer**:
  - `modules/Settings/SiteSettingsManager.php` — Service layer đầu tiên của dự án, quản lý cài đặt tenant-scoped (`site_name`, `site_tagline`, `default_meta_description`, `default_og_image_id`, `favicon_id`, `robots_txt_custom`) với runtime array cache (theo tenant, sống trong 1 request/1 instance Container — không dùng `core/Cache.php`).
  - `modules/Settings/SitemapController.php` (`GET /sitemap.xml`) — sinh XML động từ toàn bộ Page đã publish của tenant hiện tại.
  - `modules/Settings/RobotsController.php` (`GET /robots.txt`) — phục vụ nội dung tùy chỉnh (`robots_txt_custom`) hoặc mặc định an toàn (`Allow: /` + trỏ `Sitemap:`).
  - `modules/Admin/{SettingShowEditController,SettingUpdateController}.php` + `themes/default/views/admin/pages/settings/edit.php` — Admin UI Global Settings, mục "Cài đặt chung" mới trong sidebar.
  - `site_name` được inject vào logo header Public layout (`themes/default/views/layouts/main.php`).
- **Extended SEO Meta Fields**: bổ sung `og_title`, `og_description`, `is_index`, `is_follow` vào `seo_meta` (migration `2026_08_08_000001_alter_seo_meta_add_og_robots_fields.php`) — cập nhật `modules/Seo/UpdateSeoMetaController.php` (JSON API) và `modules/Admin/SeoUpdateController.php` + view (Admin UI, kèm semantics checkbox HTML: thiếu key = bỏ chọn = `false`).
- `database/migrations/2026_08_09_000001_create_site_settings_table.php` — bảng `site_settings` (1 bản ghi/tenant).
- `tests/Core/ModuleSettingsIntegrationTest.php` (9 test, gồm test route-collision trực tiếp), `tests/Core/AdminSettingsManagementUiTest.php` (9 test).

### Changed & Fixed

- **Giải quyết Route Collision `/sitemap.xml`/`/robots.txt`** (đã hoãn từ CMS-043 và Public Website Polish): khai báo `"settings"` trong `dependencies` của `modules/Public/module.json` — buộc Module `Settings` boot (và đăng ký route) **trước** `modules/Public` trong `ModuleManager::resolveLoadOrder()` (topological sort), đảm bảo `Router::match()` (first-match-wins theo thứ tự đăng ký) khớp đúng `/sitemap.xml`/`/robots.txt` trước khi thử `GET /{slug}`.
- `tests/Core/PublicPageRenderingTest.php` — bổ sung `'settings'` vào danh sách module enabled (bắt buộc do dependency mới) + bảng `site_settings` trong `migrate()`.
- **Root Cause Analysis sau PHPUnit thật**: `ModuleSettingsIntegrationTest` FAIL 3 test (`NOT NULL constraint failed: pages.created_by`) — lỗi ở helper `seedPage()` của chính test mới viết (thiếu `created_by` khi INSERT), không phải lỗi Controller/Migration. Sửa: thêm helper `seedUser()` + bổ sung `created_by` vào INSERT.

### Architecture Decisions

- **`SiteSettingsManager` là Service Layer đầu tiên** trong toàn bộ dự án — phá lệ nguyên tắc "Controller gọi `Database` trực tiếp" đã giữ xuyên suốt Page/Media/Menu/Seo. Lý do: được dùng bởi ≥4 điểm độc lập (Sitemap, Robots, Public layout, Admin Settings) — khác hẳn pattern "1 Controller sở hữu logic" của các Module trước.
- **Không render `<meta name="robots">` ra Public** dựa trên `is_index`/`is_follow` — chỉ lưu DB ở giai đoạn này (Owner Decision).
- **Không tự sinh fallback `description`/OG mặc định** ở Public — giữ nguyên "Option A: không tự sinh mặc định" đã chốt ở Public Website Polish; chỉ `site_name` được inject vào layout.
- **Chưa render `favicon` thật** — chưa có route Public phục vụ file Media (giới hạn đã ghi nhận từ Public Website Polish, chưa giải quyết).

### Verification

- `vendor/bin/phpunit tests/Core/ModuleSettingsIntegrationTest.php`, `AdminSettingsManagementUiTest.php`, `ModuleSeoIntegrationTest.php`, `AdminSeoManagementUiTest.php`, `PublicPageRenderingTest.php`, `RealMigrationsTest.php` trên môi trường thật: **PASS**.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 613 tests, 1208 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.55] — SEO Meta Settings Admin UI (hoàn tất PHASE 3)

### Added

- `modules/Admin/{SeoListController,SeoShowEditController,SeoUpdateController}.php` — 3 Controller Admin UI cho SEO Meta theo từng Page, copy logic upsert từ `Modules\Seo\UpdateSeoMetaController` y hệt.
- `themes/default/views/admin/pages/seo/{list,edit}.php` — List Page (badge Đã/Chưa cấu hình SEO) + form sửa (title/description/canonical/OG image/schema type/schema data).
- Mở khóa "SEO" trong sidebar — **badge "Soon" cuối cùng đã được gỡ, hoàn tất toàn bộ PHASE 3** (Pages/Media/Menu/SEO Admin UI).
- `tests/Core/AdminSeoManagementUiTest.php` — 16 test case.

### Architecture Decisions

- **Phạm vi được thu hẹp qua `AskUserQuestion`**: yêu cầu ban đầu nhắc tới "Global SEO Settings" (Site Title format, Robots.txt/Sitemap toggle) — xác nhận **không tồn tại** trong `modules/Seo/*` hay schema DB hiện tại (chỉ có SEO Meta theo từng Page). Owner chọn giữ đúng phạm vi hiện có, không mở bảng/Controller mới cho Global Settings — ghi nhận là đề xuất riêng cho Phase sau, không lẫn vào phạm vi này.
- **`schema_data` qua form HTML**: JSON API gốc nhận `array` (JSON body), không có cách nhập mảng lồng nhau qua form text truyền thống — thêm field mới `schema_data_json` (textarea JSON thô), Controller `json_decode` trước khi áp dụng logic upsert gốc; JSON không hợp lệ → silent-redirect, không lưu.
- **`entity_type` cố định `'page'`** trong Controller Admin (khác JSON API nhận qua route param) — Admin UI chỉ phục vụ Page, đúng phạm vi đã duyệt.
- **Không sửa `modules/Seo/*` (JSON API), `core/*`, `bin/bootstrap.php`** (permission `seo.view/seo.update` đã có sẵn từ CMS-043), không migration mới.

### Fixed

- **Tự phát hiện trong lúc code**: cùng bug pattern `Validator`'s `nullable` không bỏ qua chuỗi rỗng `''` đã gặp ở Menu Builder — `<select>` OG Image "-- Không có --" gửi `og_image_id=''`, luôn bị từ chối trước khi chạm logic. Sửa bằng chuẩn hóa `''` → `null` trước `validate()`.

### Verification

- `vendor/bin/phpunit tests/Core/AdminSeoManagementUiTest.php` trên môi trường thật: **PASS** — 16 tests, 32 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 592 tests, 1144 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.54] — Menu Builder Admin UI

### Added

- `modules/Admin/{MenuListController,MenuCreateController,MenuShowController,MenuUpdateController,MenuDeleteController}.php` — 5 Controller CRUD Menu, copy logic từ `Modules\Menu\*Controller`.
- `modules/Admin/{MenuItemCreateController,MenuItemUpdateController,MenuItemDeleteController}.php` — 3 Controller CRUD Menu Item, copy logic từ `Modules\Menu\*ItemController` (kèm BFS xóa nhánh con cháu).
- `themes/default/views/admin/pages/menus/{list,show}.php` — List Menu (form Create inline) + Show (cây `menu_items` lồng nhau, form Add Item, kéo-thả HTML5 `draggable` thuần).
- Kéo-thả cấu trúc menu: `public/assets/js/app.js` bổ sung handler `dragstart/dragover/drop`, gọi `fetch()` POST `/admin/menu-items/{id}` kèm header `X-Requested-With: XMLHttpRequest` để đổi `parent_id` không cần reload trang — **lần đầu dự án dùng AJAX trong Admin UI** (mọi màn hình trước đó đều full-page form POST).
- `public/assets/css/components.css` — `.menu-tree/.menu-tree-item/.is-dragging/.is-drop-target/.drag-handle`.
- Mở khóa link "Menu" trong `admin/partials/sidebar.php`.
- `tests/Core/AdminMenuManagementUiTest.php` — 18 test case.

### Architecture Decisions

- **`MenuItemUpdateController` phục vụ 2 use case** (Edit form thường → HTML redirect; Drag-drop AJAX → JSON) qua `Request::ajax()` có sẵn từ CMS-015 — không tạo Controller/route thứ 2 trùng logic.
- **Đơn giản hóa kéo-thả có chủ đích**: thả item vào 1 item khác chỉ đổi `parent_id` (reparent), reload trang để cập nhật cây — không tính lại `sort_order` chi tiết giữa các anh em cùng cấp. Đủ đúng nghĩa "kéo-thả cấu trúc" theo yêu cầu Owner, tránh mở rộng phạm vi thành bulk-reorder engine.
- **Silent-redirect khi lỗi** (Create/Update Menu, Create Menu Item) — không có trang riêng render lỗi (form inline trên `list.php`/`show.php`), cùng mẫu Media/Page.
- **Không sửa `modules/Menu/*` (JSON API), `core/*`, `bin/bootstrap.php`** (permission `menu.view/create/update/delete` đã có sẵn từ CMS-042).

### Fixed

- **Tự phát hiện trong lúc code**: bản nháp đầu của `show.php` khai báo `function flatten()`/`function renderNodes()` ở top-level file view — vì `View::renderTemplate()` dùng `include` (không `include_once`), sẽ Fatal "Cannot redeclare function" khi view render lần 2 trong cùng tiến trình PHP. Sửa bằng closure cục bộ (`$flatten`, `$renderNode`) trước khi giao Owner test.
- **Tự phát hiện trong lúc code**: `Validator`'s `nullable` chỉ bỏ qua khi giá trị `=== null` (không bỏ qua `''`) — cả `<select>` "Cấp gốc" và drag-drop "thả về gốc" đều gửi `parent_id=''`, sẽ luôn bị `filter_var('', FILTER_VALIDATE_INT)` từ chối. Sửa bằng chuẩn hóa `''` → `null` trước validate trong `MenuItemCreateController`/`MenuItemUpdateController`.
- `testUpdateMenuCrossTenantReturns404`, `testUpdateMenuItemCrossTenantReturns404` FAIL ban đầu (419 thay vì 404) — cùng nguyên nhân đã gặp ở Pages Admin UI: `CsrfMiddleware` chặn trước khi Controller kịp kiểm tra tenant ownership. Sửa: lấy token hợp lệ qua `Core\Csrf::token()` trực tiếp trong 2 test.

### Verification

- `vendor/bin/phpunit tests/Core/AdminMenuManagementUiTest.php` trên môi trường thật: **PASS** — 18 tests, 37 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 576 tests, 1112 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.53] — Media Manager Admin UI

### Added

- `modules/Admin/{MediaListController,MediaFileController,MediaUploadController,MediaUpdateController,MediaDeleteController}.php` — 5 Controller HTML quản lý Media, copy logic từ `Modules\Media\*Controller` (JSON API), không sửa file gốc.
- `MediaFileController` (**route mới, chưa có tiền lệ**): `GET /admin/media/{id}/file` — phục vụ byte file từ `storage/app/media` cho preview trong Admin Grid (chỉ dùng nội bộ Admin, gated `media.view`; Public site vẫn chưa có Media URL — quyết định hoãn từ Public Website Polish không đổi).
- `themes/default/views/admin/pages/media/list.php` — Grid ảnh/file, inline edit form (alt/title/caption), Upload Modal (drag-drop qua vanilla JS, progressive enhancement — form vẫn submit thường nếu JS tắt).
- Mở khóa link "Media" trong `admin/partials/sidebar.php`.
- `public/assets/css/components.css` — `.media-grid/.media-card/.modal-overlay/.modal/.media-dropzone` (tái dùng token màu/spacing hệ thống, không style mới ngoài hệ thống).
- `public/assets/js/app.js` — handler `data-modal-open/close`, drag-drop file vào input ẩn.
- `tests/Core/AdminMediaManagementUiTest.php` — 11 test case, `storagePath` override qua `Container::singleton()` với thư mục TEMP riêng (không ghi file thật vào `storage/app/media` của repo).

### Architecture Decisions

- **Route serve file mới** (`GET /admin/media/{id}/file`) — quyết định qua `AskUserQuestion` (Owner chọn "Thêm route serve file mới" thay vì Grid chỉ hiển thị icon/filename). Không đụng `core/Http/Response.php` — `MediaFileController` tự dựng `new Response($bytes, 200, ['Content-Type' => $mimeType])` (constructor đã public, đủ dùng).
- **Silent-redirect khi lỗi** (Upload sai mime/size, Update validate fail) — không có trang riêng để render lại lỗi (Modal/inline form trên chính `list.php`, không phải trang Create/Edit độc lập như Page), cùng mẫu `PagePublishController`.
- **Không sửa `modules/Media/*` (JSON API), `core/*`, `bin/bootstrap.php`** (permission `media.view/upload/update/delete` đã có sẵn từ CMS-041).
- **Trùng lặp logic có chủ đích** giữa `modules/Admin/Media*Controller.php` và `modules/Media/*Controller.php` — không tạo Service/Repository dùng chung, đúng tiền lệ CMS-046/047/Pages Admin UI.

### Verification

- `vendor/bin/phpunit tests/Core/AdminMediaManagementUiTest.php` trên môi trường thật: **PASS** — 11 tests, 26 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 558 tests, 1075 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.52] — Pages Management Admin UI

### Added

- `modules/Admin/{PageListController,PageShowCreateController,PageCreateController,PageShowEditController,PageUpdateController,PageDeleteController,PagePublishController,PageSetHomepageController}.php` — 8 Controller HTML quản lý Page, copy logic từ `Modules\Page\*Controller` (JSON API), không sửa file gốc.
- 3 GET + 5 POST route mới trong `modules/Admin/routes.php` (route ghi bọc `CsrfMiddleware`).
- `themes/default/views/admin/pages/pages/{list,create,edit}.php` — List (toggle Publish/Unpublish, Đặt trang chủ, Delete), Create/Edit tích hợp Rich Text Editor **Quill.js** (CDN, không thêm Composer/npm dependency).
- 2 yield point mới `head_extra`/`scripts_extra` trong `admin/layouts/main.php` (thuần bổ sung, chỉ Page Create/Edit dùng để nạp Quill.js CDN).
- Mở khóa link "Pages" trong `admin/partials/sidebar.php` (bỏ badge "Soon").
- `tests/Core/AdminPageManagementUiTest.php` — 11 test case.

### Changed

- Quy ước `pages.content` (tầng Application/View, không phải schema DB) đổi từ `{"text": "..."}` sang `{"html": "..."}` cho page tạo/sửa qua Admin UI Rich Text — `modules/Page/*` (JSON API) không đổi (Validator chỉ kiểm `content` là `array`).
- `themes/default/views/pages/default.php` (Public site) — render content theo 4 nhánh fallback: `content['html']` (raw HTML) → `content['text']` (escaped, tương thích ngược dữ liệu cũ từ `bin/seed_demo.php`) → mảng generic (JSON dump) → scalar/null.

### Architecture Decisions

- **Rich Text = Quill.js qua CDN** — quyết định qua `AskUserQuestion` (2 vòng: có/không thêm thư viện; chọn thư viện + đổi schema content) vì yêu cầu "Rich Text thật" xung đột với chính sách zero-dependency gốc; Quill CDN là phương án dung hòa (frontend-only, không Composer/npm).
- **XSS trust model chấp nhận có chủ đích**: Public site render `content['html']` bằng `$this->raw()` không sanitize — chỉ Admin có quyền `page.create`/`page.update` mới tạo được nội dung này (cùng mô hình tin cậy WordPress).
- **Không sửa `modules/Page/*`, `core/*`, `bin/bootstrap.php`** (permission `page.view/create/update/delete/publish` đã có sẵn từ CMS-040) — đúng phạm vi đã khóa.
- **Trùng lặp logic có chủ đích** giữa `modules/Admin/Page*Controller.php` và `modules/Page/*Controller.php` — không tạo Service/Repository dùng chung, đúng tiền lệ CMS-046/047.

### Fixed

- `tests/Core/AdminPageManagementUiTest.php::testCreatePageMissingPermissionReturns403Html` — FAIL ban đầu (419 thay vì 403) do request test thiếu CSRF token hợp lệ, bị `CsrfMiddleware` chặn trước khi chạm logic permission (đúng thiết kế pipeline, không phải bug). Sửa: lấy token hợp lệ qua `Core\Csrf::token()` trực tiếp trong test.

### Verification

- `vendor/bin/phpunit tests/Core/AdminPageManagementUiTest.php` trên môi trường thật: **PASS** — 11 tests, 24 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 547 tests, 1049 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

### Ghi chú nợ tài liệu

Các hạng mục sau đã triển khai và được Owner xác nhận hoạt động qua screenshot thật, nhưng **chưa có Documentation Completion riêng** (nằm ngoài phạm vi cập nhật lần này — sẽ bổ sung khi Owner yêu cầu): Local Demo Foundation (`bin/load_env.php`, `bin/create_database.php`, `bin/seed_demo.php`, `SETUP_LOCAL.md`), 2 Critical Bug Fix môi trường thật (`MigrationManager::runInTransactionIfSupported()`, `core/Middleware/StartSessionMiddleware.php`), UI Kit Tech Green/Dark (`public/assets/css/*`, `public/assets/js/app.js`).

## [0.0.51] — Public Website Polish

### Added

- 404 themed page (`themes/default/views/pages/404.php`) — `HomeController`/`PublicPageController` render qua theme khi `View::exists('pages.404')`, fallback text thuần nếu theme không có view này.
- Header/Footer khung tĩnh trong `themes/default/views/layouts/main.php`.
- SEO meta injection vào `<head>` (`<meta description>`, `<link canonical>`, `<meta og:title>`, `<meta og:description>`) từ bảng `seo_meta` (CMS-043) — chỉ render khi có bản ghi.
- JSON-LD (`<script type="application/ld+json">`) từ `seo_meta.schema_data`.
- Navigation Menu render (`<nav>`, 2 cấp cha/con) từ `menus`/`menu_items` (CMS-042), location cố định `'header'`.
- Active menu — đánh dấu `class="active"` cho menu item `type=page` trỏ tới page đang xem.

### Architecture Decisions

- **Không `og:image`** — chưa có route phục vụ file Media qua HTTP, tránh tạo URL chết.
- **Fallback khi `seo_meta` chưa tồn tại (Option A)**: giữ nguyên hành vi cũ (`<title>` = `pages.title`), không tự sinh description/OG/JSON-LD mặc định.
- **Ẩn hẳn `<nav>`** khi tenant chưa tạo Menu cho `location_key='header'` (không hiển thị khung rỗng).
- **Trùng lặp logic có chủ đích** giữa `HomeController`/`PublicPageController` (query SEO/Menu, dựng cây) — không tạo Service/Trait/Helper dùng chung, đúng tiền lệ toàn dự án.
- **Dựng cây Menu bằng PHP thuần** (1 query `menu_items` + 1 query `IN (...)` lấy slug các page tham chiếu, không N+1) — copy logic từ `Modules\Menu\ShowMenuController::buildTree()`, không tái sử dụng chéo Module.
- **JSON-LD dùng `json_encode(..., JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)`**, không dùng `$this->e()` (sai ngữ cảnh HTML-escape cho JSON) — chặn `</script>` breakout injection.
- **Hoãn hoàn toàn**: Breadcrumb, Media URL (serve file qua HTTP), Search, `/sitemap.xml`, `/robots.txt`, Redirect support, Asset optimization — đều ngoài phạm vi "polish" (cần thiết kế route/route collision riêng hoặc feature mới hoàn toàn, đã phân tích ở PHASE 1/2).
- **Không sửa Core, không Service/Repository/Interface/Trait/Middleware/Event/Hook/Cache mới**.

### Verification

- `vendor/bin/phpunit tests/Core/PublicPageRenderingTest.php` trên môi trường thật: **PASS** — 8 tests, 14 assertions (không đổi số lượng — chỉ bổ sung fixture bảng `menus`/`menu_items`/`seo_meta` vào `migrate()`, không thêm/bớt test method).
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 536 tests, 1025 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.50] — CMS-043: SEO Management (Module JSON API)

### Added

- `modules/Seo/` — SEO Module (JSON API thuần): `ShowSeoMetaController` (`GET /seo/{entity_type}/{entity_id}`), `UpdateSeoMetaController` (`PATCH /seo/{entity_type}/{entity_id}`, upsert).
- `database/migrations/2026_08_05_000001_create_seo_meta_table.php` — bảng `seo_meta` (`tenant_id, entity_type, entity_id, title, description, canonical, og_image_id, schema_type, schema_data`), UNIQUE `(tenant_id, entity_type, entity_id)`, FK `tenant_id → sites CASCADE`, FK `og_image_id → media ON DELETE SET NULL`.
- Permission `seo.view/update` — mở rộng `bin/bootstrap.php` (24 → 26 permission).
- `tests/Core/ModuleSeoIntegrationTest.php` (17 test).

### Architecture Decisions

- **MVP tối giản, cắt bỏ khỏi spec gốc `10-module-seo.md`**: chỉ bảng `seo_meta`, **không** `redirects`, **không** `sitemap_cache`; `entity_type` chỉ hỗ trợ thật `page` (bỏ `post`/`product` — module chưa tồn tại).
- **Không `/sitemap.xml`, không `/robots.txt`, không route public nào** — cả 2 đều là route tĩnh 1-segment sẽ bị `GET /{slug}` (Public, CMS-044) nuốt mất nếu Module `Seo` load sau `Public` (mặc định theo thứ tự alphabet), và cách khắc phục duy nhất (thêm `"seo"` vào `modules/Public/module.json.dependencies`) đòi hỏi chạm Module đã hoàn thành — ngoài phạm vi CMS-043.
- **Redirect (`redirects`) bị hoãn hoàn toàn** — về bản chất đòi hỏi can thiệp toàn cục vào luồng dispatch (kiểm tra trước 404), không phải CRUD đơn thuần; cần Middleware toàn cục mới hoặc sửa `modules/Public/PublicPageController.php` (đã khoá).
- **Không Hook** (`seo.meta_updated`, lắng nghe `page.published`...) — dự án chưa có tiền lệ Module nghiệp vụ nào bắn/lắng nghe Hook thật.
- **Không Admin UI, không Public rendering (`<head>` inject title/OG/schema)** — đúng tiền lệ Page (CMS-040)/Media (CMS-041)/Menu (CMS-042): Module JSON trước, UI/tích hợp là task riêng sau.
- **Permission chỉ `seo.view`/`seo.update`** (không `seo.manage` như spec gốc, không `seo.create`/`seo.delete`) — `seo_meta` là upsert-theo-entity, không có hành động "tạo"/"xoá" độc lập nào trong route table đã duyệt.
- **Upsert bằng `SELECT` rồi rẽ nhánh `INSERT`/`UPDATE`** — không `Database::transaction()` (mỗi lần gọi chỉ đúng 1 câu SQL ghi), không retry, không lock. Ghi nhận race condition lý thuyết (2 request PATCH đồng thời cùng entity chưa có `seo_meta`) là Technical Debt chấp nhận được cho thao tác Admin tần suất thấp.
- **`og_image_id` FK `ON DELETE SET NULL`** (khả thi thật vì `media` đã tồn tại từ CMS-041, khác `menu_items.reference_id` không FK được) — xoá ảnh không xoá theo `seo_meta`, không chặn xoá ảnh vì đang làm OG image.
- **`schema_data` lưu `TEXT`** (JSON string, Application layer tự `json_encode`/`json_decode`) — đúng Owner Decision CMS-040 đã áp dụng cho `pages.content`.

### Verification

- `vendor/bin/phpunit tests/Core/ModuleSeoIntegrationTest.php` trên môi trường thật: **PASS** — 17 tests, 28 assertions.
- **Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu `2026_08_05_000001_create_seo_meta_table` (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật, không phải lỗi migration — đúng root cause đã gặp ở CMS-040/041/042) — sửa đúng 1 dòng.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 536 tests, 1025 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.49] — CMS-042: Menu Management (Module JSON API)

### Added

- `modules/Menu/` — Menu Module (JSON API thuần): `ListMenusController` (`GET /menus`), `CreateMenuController` (`POST /menus`), `ShowMenuController` (`GET /menus/{id}`), `UpdateMenuController` (`PATCH /menus/{id}`), `DeleteMenuController` (`DELETE /menus/{id}`), `CreateMenuItemController` (`POST /menus/{id}/items`), `UpdateMenuItemController` (`PATCH /menu-items/{id}`), `DeleteMenuItemController` (`DELETE /menu-items/{id}`).
- `database/migrations/2026_08_04_000001_create_menus_table.php` + `2026_08_04_000002_create_menu_items_table.php` — bảng `menus` (`tenant_id, name, location_key`, UNIQUE `(tenant_id, location_key)`) và `menu_items` (`menu_id, parent_id self, label, type, reference_id, url, target, sort_order`).
- Permission `menu.view/create/update/delete` — mở rộng `bin/bootstrap.php` (20 → 24 permission).
- `tests/Core/ModuleMenuIntegrationTest.php` (20 test).

### Architecture Decisions

- **MVP tối giản, cắt bỏ khỏi spec gốc `08-module-menu.md`**: `menu_items.type` chỉ còn `page`/`custom` (bỏ `post_category`/`product_category` — Module Post/Product chưa tồn tại); không kéo-thả, không endpoint thay toàn bộ cấu trúc (`PUT` bulk-replace) — CRUD từng bản ghi; không Hook (`menu.updated`...), không Cache invalidation — chưa có consumer thật (chưa Public rendering).
- **Không Admin UI HTML, không Public rendering, không sửa `modules/Public/*`/theme layout trong CMS-042** — đúng tiền lệ Page (CMS-040)/Media (CMS-041): Module JSON trước, UI/tích hợp là task riêng sau.
- **Không route public nào cho Menu** — tránh rủi ro collision `GET /menus/{id}` (admin, numeric) vs khả năng tương lai `GET /menus/{location}` (public) cùng "shape" segment. Route Item dùng tiền tố riêng `/menu-items` (không lồng `/menus/{menuId}/items/{id}`) để chủ động không tạo thêm nguy cơ va chạm.
- **Permission hạt mịn `menu.view/create/update/delete`** (không dùng `menu.manage` như spec gốc) — nhất quán convention `resource.action` toàn dự án. Thao tác trên `menu_items` dùng chung `menu.update` (không tạo `menu_item.*` riêng — Item luôn phụ thuộc Menu, không phải resource độc lập).
- **`location_key` là chuỗi tự do** (`required|string|max:50`) — không xây cơ chế Theme khai báo location hợp lệ (chưa có bằng chứng cần, `ThemeManager`/`theme.json` không có cơ chế này).
- **`ShowMenuController` dựng cây bằng PHP thuần** (1 query duy nhất `SELECT ... WHERE menu_id = ?`, gom theo `parent_id`) — không recursive SQL, không N+1.
- **`DeleteMenuController`**: `Database::transaction()` bọc 2 câu `DELETE` liên quan (`menu_items` rồi `menus`) — không dựa FK CASCADE thật (SQLite test không enforce mặc định).
- **`DeleteMenuItemController`**: BFS gom id con cháu chỉ dùng `SELECT` (không tính write), kết quả cuối chỉ 1 câu `DELETE ... WHERE id IN (...)` duy nhất — **không** bọc transaction (chỉ 1 câu ghi thì không cần).
- **Chặn self-parent**: `parent_id` không được trùng chính `id` của item đang sửa → `422` (`UpdateMenuItemController`).

### Verification

- `vendor/bin/phpunit tests/Core/ModuleMenuIntegrationTest.php` trên môi trường thật: **PASS** — 20 tests, 42 assertions.
- **Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu 2 migration mới (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật, không phải lỗi migration — đúng root cause đã gặp ở CMS-040/041) — sửa đúng 2 dòng.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 519 tests, 992 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.48] — CMS-041: Media Library (Module JSON API)

### Added

- `modules/Media/` — Media Module (JSON API thuần): `ListMediaController` (`GET /media`), `UploadMediaController` (`POST /media`), `UpdateMediaController` (`PATCH /media/{id}`), `DeleteMediaController` (`DELETE /media/{id}`).
- `database/migrations/2026_08_03_000001_create_media_table.php` — bảng `media` (MVP tối giản): `tenant_id, file_name, path, mime_type, size, alt_text, title, caption, uploaded_by, created_at`.
- Permission `media.view/upload/update/delete` — mở rộng `bin/bootstrap.php` (16 → 20 permission).
- Kích hoạt `sites.storage_used_bytes` (tồn tại từ CMS-028, chưa từng dùng) — cộng khi upload, trừ khi xoá, cùng transaction với thao tác `media`.
- `tests/Core/ModuleMediaIntegrationTest.php` (13 test).

### Architecture Decisions

- **MVP tối giản, cắt bỏ toàn bộ khỏi spec gốc `07-module-media.md`**: không `media_folders`, không `media_variants` (resize/WebP), không `media_usages`, không `StorageDriver` (S3), không Queue — lý do: chưa có consumer thật nào tham chiếu `media` (kể cả `pages`), chưa có thư viện xử lý ảnh trong `composer.json`, chưa có Admin UI Media.
- **Không Admin UI HTML cho Media trong CMS-041** — chỉ Module JSON, đúng tiền lệ Page (CMS-040 JSON trước, Admin UI User/Role là task riêng biệt sau ở CMS-046/047).
- **Validate file thủ công trong Controller** (bắt buộc file + `UPLOAD_ERR_OK`, size ≤ 5MB, mime whitelist `image/jpeg,image/png,image/gif,application/pdf`) — không rule Validator mới, không sửa `core/Validator.php`.
- **Storage Local filesystem thuần** (`storage/app/media/{tenant_id}/{tên_file_duy_nhất}`) — không interface `StorageDriver` khi chỉ có 1 implementation (nhất quán nguyên tắc đã áp dụng cho `Database`/`View`).
- **`move_uploaded_file()` với fallback `rename()`** khi `is_uploaded_file()` false — giải quyết giới hạn PHP thật (không thể test `move_uploaded_file()` qua CLI/PHPUnit, hàm này luôn trả `false` ngoài request HTTP thật) — `move_uploaded_file()` vẫn là đường đi chính cho request thật (giữ bảo mật), `rename()` chỉ kích hoạt ngoài HTTP thật — không thêm abstraction/dependency mới, đúng pattern Laravel/Symfony.
- **Transaction**: Upload = `INSERT media` + `UPDATE sites.storage_used_bytes +=`; Delete = `DELETE media` + `UPDATE sites.storage_used_bytes -=` — cùng 1 `Database::transaction()`, đúng nguyên tắc "multi-step writes" (`database-design.md` mục 6.3).

### Verification

- `vendor/bin/phpunit tests/Core/ModuleMediaIntegrationTest.php` trên môi trường thật: **PASS** — 13 tests, 35 assertions.
- **Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu `2026_08_03_000001_create_media_table` (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật, không phải lỗi migration) — sửa đúng 1 dòng.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 499 tests, 940 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.47] — CMS-047: Admin Role Management UI

### Added

- Admin Role Management UI (`modules/Admin/Role*Controller.php`) — mở rộng `modules/Admin/` với 8 Controller mới: `RoleListController`, `RoleShowCreateController`, `RoleCreateController`, `RoleShowEditController`, `RoleUpdateController`, `RoleDeleteController`, `RoleShowPermissionsController`, `RoleAssignPermissionsController`.
- Role listing HTML (`GET /admin/roles`) — hiển thị cả System Role và Tenant Role của tenant hiện tại.
- Create/Edit role HTML form (`GET/POST /admin/roles`, `GET /admin/roles/{id}/edit`, `POST /admin/roles/{id}`).
- Delete role (`POST /admin/roles/{id}/delete`).
- Permission assignment UI (`GET/POST /admin/roles/{id}/permissions`) — hiển thị "Đã gán"/"Chưa gán", nút "Gán" cho từng permission chưa gán.
- `themes/default/views/admin/pages/roles/{list,create,edit,permissions}.php`.
- `tests/Core/AdminRoleManagementUiTest.php` (14 test).

### Architecture Decisions

- **Route `/admin/roles/*`** — cùng convention `modules/Admin/` đã có từ CMS-045/046, không tạo Module riêng.
- **`POST` thay `PATCH`/`DELETE`** cho Edit/Delete — `core/Http/Request.php` không hỗ trợ Method Spoofing, form trình duyệt chỉ gửi được `GET`/`POST` thật (đúng tiền lệ CMS-046).
- **Permission assignment chỉ ADD, không REMOVE** (Owner Decision #1) — khớp đúng capability thật của `Modules\Role\AssignPermissionController` (INSERT idempotent, không có endpoint gỡ nào trong dự án); UI chỉ hiển thị nút "Gán" cho permission chưa gán, không có nút "Gỡ".
- **Delete Role thất bại trả HTML rõ lý do** (Owner Decision #2) — `Response::html('403 Forbidden', 403)` (System Role) / `Response::html('409 Role dang duoc su dung', 409)` (đang được user dùng) — không redirect im lặng, vì Delete là hành động phá huỷ dữ liệu.
- **System Role xác định bằng `tenant_id IS NULL`** (Owner Decision #3) — nhất quán tuyệt đối với `modules/Role/*`, không dùng cột `roles.is_system` (tồn tại trong migration nhưng không Controller nào đọc/ghi).
- **CSRF tái sử dụng nguyên `CsrfMiddleware` group** đã kích hoạt từ CMS-045 — không sửa `core/Csrf.php`/`core/Middleware/*`.
- **System Role vẫn xem được trang Permissions** (200, "View allowed" — Owner Decision 3 gốc CMS-038) — chỉ hành động `POST` assign mới bị chặn 403.

### Verification

- `vendor/bin/phpunit tests/Core/AdminRoleManagementUiTest.php` trên môi trường thật: **PASS** — 14 tests, 32 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 486 tests, 900 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế). PHP 8.3.30.

## [0.0.46] — CMS-046: Admin User Management UI

### Added

- Admin User Management UI (`modules/Admin/User*Controller.php`) — mở rộng `modules/Admin/` (CMS-045) với 8 Controller mới: `UserListController`, `UserShowCreateController`, `UserCreateController`, `UserShowEditController`, `UserUpdateController`, `UserLockController`, `UserUnlockController`, `UserAssignRoleController`.
- User listing HTML (`GET /admin/users`) — scoped tenant hiện tại, kèm dropdown gán role ngay trong danh sách.
- Create user HTML form (`GET/POST /admin/users`) — validate, transaction (role + user + user_site_roles), render lại form khi lỗi.
- Edit user HTML form (`GET /admin/users/{id}/edit`, `POST /admin/users/{id}`).
- Lock/Unlock user (`POST /admin/users/{id}/lock`, `POST /admin/users/{id}/unlock`).
- Assign role (`POST /admin/users/{id}/role`).
- CSRF bảo vệ toàn bộ route `POST` mới, mở rộng `CsrfMiddleware` group đã có từ CMS-045.
- `themes/default/views/admin/pages/users/{list,create,edit}.php`.
- `tests/Core/AdminUserManagementUiTest.php` (12 test).

### Architecture Decisions

- **Admin UI tiếp tục nằm trong `modules/Admin/`** — không tạo Module `Admin\User` riêng.
- **Không sửa `modules/User/*` (API JSON đã hoàn thành)** — hành vi/response không đổi.
- **Không tạo Service Layer mới** — dự án chưa có Repository/Service, giữ nguyên kiến trúc CMS-034 → CMS-045.
- **`UserCreateController` (Admin) copy nguyên transaction logic từ `Modules\User\CreateUserController`** — chấp nhận trùng lặp (Owner Decision Phương án A, CMS-046), không trích xuất abstraction chỉ để tránh duplication.
- **HTML form dùng `POST` thay vì `PATCH`** cho Edit — `core/Http/Request.php` không hỗ trợ Method Spoofing (`_method`), form trình duyệt chỉ gửi được `GET`/`POST` thật.
- **Không có `GET /admin/users/{id}`** (trang xem chi tiết riêng) và không Delete — đúng phạm vi đã duyệt.

### Verification

- `vendor/bin/phpunit tests/Core/AdminUserManagementUiTest.php` trên môi trường thật: **PASS** — 12 tests, 28 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 472 tests, 868 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.45] — CMS-045: Admin UI Foundation

### Added

- `modules/Admin/` — Admin UI Foundation Module (Module thứ 6 của dự án, `dependencies: [auth]`): `ShowLoginController` (`GET /admin/login`), `LoginController` (`POST /admin/login`), `LogoutController` (`POST /admin/logout`), `DashboardController` (`GET /admin/dashboard`).
- HTML login flow — form đăng nhập server-rendered đầu tiên của dự án, tái sử dụng nguyên `AuthenticationService`/`Auth`/`Session` (không viết lại logic xác thực).
- Admin dashboard shell — HTML dashboard đầu tiên, hiển thị `user_count`/`role_count`.
- CSRF kích hoạt thật lần đầu cho admin POST route (`POST /admin/login`, `POST /admin/logout`) — dùng nguyên `core/Csrf.php`/`core/Middleware/CsrfMiddleware.php` đã tồn tại từ trước nhưng chưa từng gắn vào route thật.
- `themes/default/views/admin/{layouts/main.php,pages/login.php,pages/dashboard.php}` — Admin theme structure, dùng chung `themes/default/` (không tạo theme riêng).
- `tests/Core/AdminUiFoundationTest.php` (7 test).

### Architecture Decisions

- **Admin UI tách riêng `modules/Admin/`** — không sửa `modules/Auth/*` (giữ nguyên `POST /login` JSON API hiện có, không đổi hành vi/response).
- **Tái sử dụng `AuthenticationService`/`Auth`/`Session`** — không viết lại rate-limit/verify/session logic.
- **Admin dùng `View` với `activeTheme = defaultTheme = 'default'`** — tận dụng cơ chế fallback 2 cấp có sẵn của `View::resolvePath()` (CMS-005), template Admin đặt dưới `themes/default/views/admin/*`, không tạo theme riêng, không sửa `core/View.php`.
- **`POST /admin/logout` bắt buộc CSRF** — không có route logout dùng GET.
- **Không PRG (Post-Redirect-Get) khi login thất bại** — render lại form login ngay trong cùng response (Owner Decision CMS-045), không dùng `Session::flash()`, không tạo Form Helper.
- **`AuthMiddleware` không được dùng** — trả JSON 401, sai ngữ nghĩa cho luồng HTML; Controller tự `Auth::check()` + `Response::redirect('/admin/login')`.
- **`DashboardController` (Admin) copy nguyên logic SQL** từ `Modules\Dashboard\DashboardController` (JSON) — không gọi lại Controller đó, không sửa `modules/Dashboard/*`.

### Verification

- `vendor/bin/phpunit tests/Core/AdminUiFoundationTest.php` trên môi trường thật: **PASS** — 7 tests, 19 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 460 tests, 840 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.44] — CMS-044: Theme/Layout Runtime & Public Website Rendering

### Added

- `modules/Public/` — Public Website Module (Module thứ 5 của dự án, load **sau cùng** qua `module.json.dependencies: [auth,user,role,dashboard,page]`): `HomeController` (`GET /`), `PublicPageController` (`GET /{slug}`).
- Public HTML rendering route — trả `Response::html()`, không JSON, không `Authorization::can()` (public, không yêu cầu đăng nhập).
- Theme runtime integration — `View` (đã có từ CMS-005) lần đầu render thật trong production, qua `activeTheme`/`defaultTheme` (CMS-030).
- `themes/default/` — Default theme structure đầu tiên của dự án: `theme.json`, `views/layouts/main.php`, `views/pages/default.php`.
- Page rendering từ `pages` đã publish: `WHERE tenant_id = ? AND slug = ? AND status = 'published' AND deleted_at IS NULL` (slug), tương tự cho `is_homepage = 1` (homepage).
- `tests/Core/PublicPageRenderingTest.php` (8 test) + fixture theme riêng `tests/Fixtures/themes/test-theme/`.

### Architecture Decisions

- **Public route `GET /{slug}`** (không `/pages/{slug}`) — ưu tiên URL website thật (`/`, `/about`, `/contact`); rủi ro collision với route Admin GET 1-segment (`/users`, `/roles`...) giải quyết bằng cách buộc Module `Public` load **cuối cùng** qua `dependencies` trong `module.json` (dùng đúng cơ chế topological sort có sẵn của `ModuleManager`, không sửa `Router`).
- **`TenantResolverMiddleware` dùng lại nguyên trạng** — route Public nằm chung `Router::group()` hiện có, không tạo group/middleware riêng.
- **Không `Authorization::can()` cho public page** — nhất quán với việc dự án chưa dùng Middleware cho permission ở bất kỳ route nào (mọi check đều trong Controller).
- **Theme resolution `activeTheme → defaultTheme`** — dùng nguyên `View::resolvePath()` từ CMS-005, không sửa.
- **Template fallback trong Controller** (`View::exists()` trước khi `render()`) — không phải cơ chế N-path trong `View`, giữ đúng thiết kế 2 cấp cố định của `View`.
- **Không migration mới** — schema `pages` (CMS-040) đã đủ dữ liệu để render.
- **Reserved slug collision được chấp nhận là Technical Debt** — page có slug trùng route hệ thống (`login`, `users`...) sẽ không truy cập public được (route Admin luôn thắng vì đăng ký trước); không blacklist slug, không sửa `CreatePageController`.

### Verification

- `vendor/bin/phpunit tests/Core/PublicPageRenderingTest.php` trên môi trường thật: **PASS** — 8 tests, 14 assertions.
- `vendor/bin/phpunit` toàn bộ suite trên môi trường thật: **PASS** — 453 tests, 821 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis, đúng thiết kế).

## [0.0.40] — CMS-040: Page Management

### Added

- `database/migrations/2026_08_02_000001_create_pages_table.php` — bảng `pages` (Content Schema thật đầu tiên của dự án): `tenant_id, parent_id (self), title, slug, content TEXT, template, status, published_at, is_homepage, created_by, created_at/updated_at/deleted_at`. UNIQUE `(tenant_id, slug)`, index `(tenant_id, status)`, index `(parent_id)`.
- `modules/Page/` — Module thứ 4 của dự án (sau `Auth`/`User`/`Role`+`Dashboard`): 6 Controller — `ListPagesController`, `CreatePageController`, `EditPageController`, `DeletePageController`, `PublishPageController`, `SetHomepageController`.
- CRUD Page API: `GET/POST /pages`, `PATCH/DELETE /pages/{id}`.
- Publish workflow: `POST /pages/{id}/publish` — `status` (`draft/published/scheduled`), `published_at` chỉ set lần đầu (`COALESCE`), không ghi đè khi publish lại.
- Soft delete workflow: `DELETE /pages/{id}` chỉ `UPDATE deleted_at`, không xoá thật, không restore/trash.
- Homepage transaction: `POST /pages/{id}/homepage` — `Database::transaction()` bọc 2 bước UPDATE (bỏ homepage cũ, gán homepage mới), đúng `database-design.md` mục 6.1.
- `bin/bootstrap.php` — mở rộng permission bootstrap từ 11 → 16 (thêm `page.view/create/update/delete/publish`).
- `tests/Core/ModulePageIntegrationTest.php` (17 test).

### Design Decisions

- **`content` lưu `TEXT`**, không dùng cột `JSON` — Application layer (Controller) tự `json_encode()`/`json_decode()`.
- **Permission bootstrap là tiền lệ chính thức** cho Module tương lai cần permission mới — mở rộng mảng `$permissionKeys` trong `bin/bootstrap.php`, không tạo Permission Module/migration seed riêng.
- **Không transaction** cho Create/Edit/Delete/Publish (1 câu SQL/thao tác, atomic tự nhiên) — chỉ `SetHomepage` cần `Database::transaction()` (2 UPDATE liên quan).
- **Không `PageService`/Repository** — Controller gọi `Database` trực tiếp, đúng tiền lệ `User`/`Role` Module.
- Cross-tenant và page đã xoá mềm đều trả 404 (không 403) — nhất quán nguyên tắc đã dùng từ `User`/`Role` Module.

### Verification

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — `ModulePageIntegrationTest`: 17 tests/27 assertions; toàn bộ suite: 445 tests, 807 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis) đúng thiết kế.

## [0.0.38] — CMS Foundation Completion

### Added

- `bin/bootstrap.php` — CLI khởi tạo CMS lần đầu (one-time), tạo Site + Site Domain + Admin User + System Admin Role + 11 permission bootstrap + gán toàn bộ cho role Admin + liên kết `user_site_roles`, toàn bộ trong 1 `Database::transaction()`.
- `modules/Role/` — Role Management Module: `GET /roles` (System + Tenant role), `POST /roles` (luôn tạo Tenant Role), `PATCH /roles/{id}`, `DELETE /roles/{id}`, `POST /roles/{id}/permissions` (gán permission).
- `modules/Role/ListPermissionsController.php` — Permission Management (danh mục toàn hệ thống): `GET /permissions`.
- `modules/Dashboard/` — Dashboard Foundation: `GET /dashboard` (`user_count`, `role_count`, scoped tenant hiện tại).
- `tests/Core/ModuleRoleIntegrationTest.php` (12 test), `tests/Core/ModuleDashboardIntegrationTest.php` (2 test).

### Architecture Decisions

- **System Role (`roles.tenant_id NULL`)**: dùng chung mọi tenant, chỉ được `bin/bootstrap.php` tạo — qua Module chỉ **View allowed**, `Update`/`Delete`/`Permission modification` đều **403 Forbidden**.
- **Tenant Role (`roles.tenant_id = site`)**: CRUD đầy đủ qua `Role` Module, scoped tuyệt đối theo `TenantManager::id()`, cross-tenant → 404 (không 403, nhất quán nguyên tắc đã dùng từ `User` Module).
- **CLI bootstrap one-time**: kiểm tra `SELECT COUNT(*) FROM users` trước khi chạy — nếu đã có user, từ chối chạy lại, không tạo Admin trùng lặp.
- **Không migration mới** — toàn bộ Role/Permission/Dashboard dùng nguyên schema 7 bảng đã có từ CMS-028.
- **Permission bootstrap tách khỏi `User` Module** — `bin/bootstrap.php` độc lập, không import bất kỳ class nào từ `modules/`, không đưa logic bootstrap vào `Auth`/`User`.
- **Xoá Role dùng application-level check** (không dựa vào FK exception) — `SELECT COUNT(*) FROM user_site_roles WHERE role_id = ?` trước `DELETE`, vì SQLite trong `Database::connect()` không bật `PRAGMA foreign_keys = ON` mặc định (giới hạn đã ghi nhận từ CMS-030).

### Verification

- `vendor/bin/phpunit` trên môi trường thật: **PASS** — `ModuleRoleIntegrationTest`: 12 tests/17 assertions; `ModuleDashboardIntegrationTest`: 2 tests/4 assertions; toàn bộ suite: 428 tests, 775 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis) đúng thiết kế.

## [0.0.37] — CMS-037: User Management Module

### Added

- `modules/User/` — Module thứ 2 của dự án (sau `Auth`, CMS-034/035). 6 User Management endpoints:
  - `GET /users` — List (scoped theo tenant hiện tại).
  - `POST /users` — Create User.
  - `PATCH /users/{id}` — Edit User.
  - `POST /users/{id}/lock` — Lock User.
  - `POST /users/{id}/unlock` — Unlock User.
  - `POST /users/{id}/role` — Assign Role.
- User CRUD operations — List/Create/Edit, permission-based qua `Authorization::can()`.
- Lock/Unlock User — đổi `users.status` giữa `active`/`locked`.
- Assign Role — đổi role của user đã thuộc tenant hiện tại (không tạo user_site_roles mới).
- `tests/Core/ModuleUserIntegrationTest.php` — 7 integration test, dùng `ModuleManager` trỏ `modules/` thật.

### Design Decisions

- **No permission migration seed** — CMS-037 giả định permission key (`user.view/create/update/lock/assign_role`) đã tồn tại sẵn; bootstrap để CMS-038/cơ chế riêng, không tạo migration data-seed trong CMS-037.
- **Lock/Unlock dùng chung `user.lock`** — không tách `user.unlock` riêng, cùng 1 capability.
- **No Delete User** — out of scope CMS-037.
- **No Repository/Service layer** — Controller gọi `Database` trực tiếp (YAGNI, đúng tiền lệ `TenantResolverMiddleware`/`AuthenticationService`).
- **Transaction bảo vệ `users` + `user_site_roles`** — `CreateUserController` dùng `Database::transaction()` (đã có từ CMS-004), validate role **bên trong** transaction (`\InvalidArgumentException` built-in, không Exception class mới), đảm bảo không orphan user nếu role không hợp lệ hoặc email trùng.
- Toàn bộ thao tác scoped theo `TenantManager::id()`; cross-tenant trả 404 (không 403).
- Không sửa `core/*`/`modules/Auth/*`/migration nào.

### Verification

- CMS-037 tests PASS — `ModuleUserIntegrationTest`: 7 tests/21 assertions.
- Full PHPUnit PASS — 414 tests, 754 assertions, 0 Errors, 0 Failures, 4 Skipped (Redis).

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
