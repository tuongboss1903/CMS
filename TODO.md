# TODO — CMS Đa Website

> Theo dõi tiến độ theo Phase/Task. Task ID dạng `CMS-XXX`, đánh số tuần tự theo thứ tự triển khai thực tế (không nhảy số).

## Phase 1 — Core Framework Foundation

Mục tiêu: dựng khung core tự viết (không framework nền), đủ để boot 1 request qua Router → Middleware → View/JSON. Chi tiết xem `cms-architecture-proposal.md` mục 2 và phần "QUYẾT ĐỊNH CHÍNH THỨC".

- [x] **CMS-001** — Khởi tạo project skeleton
  - [x] Tạo cấu trúc thư mục đầy đủ (`app/`, `core/`, `modules/`, `plugins/`, `themes/`, `public/`, `storage/`, `resources/`, `database/`, `config/`, `docs/`)
  - [x] `.gitkeep` cho các thư mục rỗng
  - [x] `composer.json` với PSR-4 autoload (`Core\`, `App\`, `Modules\`)
  - [x] `.gitignore`
  - [x] `git init` + commit đầu tiên — đã chạy thủ công (xác nhận qua `.git/COMMIT_EDITMSG`, `.git/index`).
  - [x] `composer install` / `composer dump-autoload` — đã chạy thủ công (xác nhận qua `vendor/autoload.php`).
- [x] **CMS-002** — Config loader + file cấu hình
  - [x] `core/Config.php` — sửa từ static class (vi phạm "No Global Variable"/"Dependency Injection") sang instance-based, inject `configPath` qua constructor
  - [x] `config/app.php`, `config/database.php` — sửa để đọc qua `getenv()` với default an toàn thay vì hard-code `debug=true`/`env=development` trực tiếp trong file version-controlled
  - [x] `config/cache.php`, `config/auth.php`, `config/tenants.php` — tạo mới (còn thiếu so với danh sách đã chốt trong `cms-architecture-proposal.md`)
  - [x] Dọn thư mục thừa `config/core/` (rỗng, không thuộc kiến trúc)
- [x] **Architecture/Code Review (sau CMS-002)** — phát hiện `public/index.php` (tự tạo, không thuộc task nào) vẫn gọi API static cũ của `Config` (`Config::init()`/`Config::get()`) → lỗi runtime `Call to undefined method`. Đã sửa: chuyển sang instance-based (`new Config(...)`), bỏ echo trực tiếp `database.connections.mysql.host` ra output (rủi ro rò rỉ thông tin hạ tầng). Kết luận: Phase 1 (CMS-001, CMS-002) đạt tiêu chuẩn sau khi sửa.
- [ ] **Kỹ thuật nợ cần theo dõi**: chưa có cơ chế nạp `.env` (`config/*.php` đọc `getenv()` nhưng chưa quyết định dotenv library hay set qua server) — chưa gây lỗi vì luôn có default, cần xử lý trước khi deploy production, chưa thuộc phạm vi task nào hiện tại.
- [ ] **Quyết định kiến trúc cần chốt trước Phase 3** (phát hiện ở Architecture Review tổng thể sau CMS-005): `Database` và `View` đều `final class`, không có interface (`DatabaseInterface`/`ViewInterface`). Hiện chấp nhận được (YAGNI, chỉ 1 implementation), nhưng phải quyết định rõ trước khi module Auth/User đầu tiên viết Repository/Controller — thêm interface sau khi nhiều module đã phụ thuộc sẽ là breaking change tốn kém.
- [x] **CMS-003** — `core/Container.php` (DI Container, PSR-11)
  - [x] Design Review — 9 điểm bổ sung: PSR-11 thật (`psr/container`), chỉ Constructor Injection, Circular Dependency detection, 3 exception (`ContainerException`, `BindingNotFoundException`, `CircularDependencyException`), singleton theo vòng đời instance (không static/global), quy ước Service Provider (chưa tạo file — hoãn tới task Plugin/Module), Reflection cache, API giữ nguyên 6 method, chuẩn bị Unit Test
  - [x] `core/Container.php`, `core/ContainerException.php`, `core/BindingNotFoundException.php`, `core/CircularDependencyException.php`
  - [x] `composer.json` — thêm `psr/container ^2.0` (require), `phpunit/phpunit ^10.5` (require-dev), autoload-dev `Tests\` → `tests/`
  - [x] `tests/Core/ContainerTest.php` + `tests/Fixtures/*.php` — 12 test case bao phủ resolve class/interface, singleton, auto-wiring đệ quy, circular dependency (đúng kịch bản `UserService → RoleService → PermissionService → UserService`), binding not found, `instance()`, scalar có/không default, `make()`, `has()`
  - [x] `phpunit.xml`
  - [x] Code Review — trace tay toàn bộ luồng logic, không phát hiện lỗi
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (PHPUnit 10.5.64, PHP 8.3.30), 0 Errors/Failures/Warnings/Deprecations → **CMS-003 COMPLETED — tag `v0.0.3`**
- [x] **CMS-004** — `core/Database.php` + `core/QueryBuilder.php` (PDO wrapper, transaction API)
  - [x] Design Review — 14 mục: vai trò Database (connection+execute+transaction+exception+log) tách bạch QueryBuilder (chỉ dựng SQL, luôn giao Database thực thi), Transaction API nested-safe, không Connection Pool (ghi nhận lý do), 4 Exception chuẩn hoá, Prepared Statement 100%, Multi-tenant qua `forTenant()` sugar (không business logic), Query Logging chỉ hook/interface, không tối ưu sớm, sẵn sàng multiple connections + Database-per-Tenant
  - [x] `core/Database.php`, `core/QueryBuilder.php`, `core/Database/{DatabaseException,ConnectionException,QueryException,TransactionException,IdentifierValidator,SqlCompiler}.php`
  - [x] `tests/Fixtures/config/database.php` (SQLite in-memory), `tests/Core/DatabaseTest.php`, `tests/Core/QueryBuilderTest.php` — 31 test case (transaction commit/rollback/nested, exception, query log, CRUD, whereIn/forTenant, identifier injection, edge case mảng rỗng, count()/aggregate regression, SQL injection qua identifier)
  - [x] Self Code Review — phát hiện & sửa 4 bug thật: `whereIn([])`/`insert([])`/`update([])` sinh SQL sai cú pháp; `count()` đưa `"COUNT(*) as aggregate"` qua `IdentifierValidator` như 1 identifier (phát hiện qua PHPUnit thật) → tách `core/Database/SqlCompiler.php` (`@internal`, chỉ `QueryBuilder` dùng, không Business Logic) để `count()` tự dựng SQL riêng, đồng thời đưa `QueryBuilder.php` về dưới 300 dòng
  - [x] Self Architecture Review — đối chiếu đủ các nguyên tắc đã chốt, không phát hiện vấn đề
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (PHPUnit 10.5.64, PHP 8.3.30), 0 Errors/Failures/Warnings/Deprecations → **CMS-004 COMPLETED — tag `v0.0.4`**
- [x] **CMS-005** — `core/View.php` (Theme Engine PHP thuần)
  - [x] Design Review + 9 điều chỉnh: View Resolution 2 cấp cố định (active theme → default theme → `ViewNotFoundException`, bỏ `addPath()`/N-path đã đề xuất trước), Layout 1 cấp (không đa cấp), Data Binding chỉ qua `render()` (không global/static), Escape tường minh (không tự động), Partial/Component dùng chung `include()`, Exception rút gọn còn 2 lớp (`ViewException`, `ViewNotFoundException`)
  - [x] `core/View.php`, `core/View/ViewException.php`, `core/View/ViewNotFoundException.php`
  - [x] `tests/Fixtures/themes/{active,default}/views/*` (fixture 2 theme) + `tests/Core/ViewTest.php` (15 test: resolve/dot notation/theme fallback/view not found/path traversal/data binding/escape/layout+section+yield/partial/section không cân bằng/reset state) + `tests/Core/ViewContainerIntegrationTest.php` (regression: ráp qua Container CMS-003)
  - [x] Self Code Review — phát hiện & sửa rò rỉ output buffer khi `section()` gọi không cân bằng (cả trong test lẫn production `render()` — thêm cơ chế tự dọn buffer trong `finally`)
  - [x] Self Architecture Review — đối chiếu đủ 9 nguyên tắc, không phát hiện vấn đề
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (PHPUnit 10.5.64, PHP 8.3.30), 0 Errors/Failures/Warnings/Deprecations → **CMS-005 COMPLETED — tag `v0.0.5`**
- [x] **CMS-006** — `core/Router.php` (Router + HTTP Layer: Request/Response/Route/Middleware Pipeline/Controller Resolver)
  - [x] Design Review + 10 điểm bổ sung: Router chỉ Routing (không Database/View/Auth/Validation), Request/Response Immutable (`new self(...)` thay vì `clone` — PHP 8.1 không cho gán lại `readonly` trong `__clone()`), Middleware Pipeline Onion (Before/After + short-circuit), Route Parameter không ép kiểu/validate, Duplicate Route throw ngay lúc đăng ký (không đợi runtime), Group chỉ merge prefix/middleware/domain, phân biệt rõ 404/405, Controller Contract (chỉ nhận Request, phải trả Response), tách `ControllerResolver` riêng theo sơ đồ `Middleware Pipeline → Controller Resolver → Controller`, HTTP layer tự viết nhẹ (không PSR-7/15, giữ tinh thần "không framework nền")
  - [x] `core/Http/{Request,Response}.php`, `core/Route.php`, `core/Router.php`, `core/Router/{ControllerResolver,RouteNotFoundException,MethodNotAllowedException,DuplicateRouteException}.php`, `core/Middleware/{MiddlewareInterface,MiddlewarePipeline}.php`
  - [x] `tests/Fixtures/Http/*.php` (8 fixture) + `tests/Core/RouterTest.php` (14 test) + `tests/Core/Router/ControllerResolverTest.php` (3 test) + `tests/Core/Http/RequestTest.php` (3 test) + `tests/Core/RouterContainerDatabaseViewIntegrationTest.php` (1 test — Regression: Router ráp đúng Container+Database+View, không sửa 3 file đó)
  - [x] Self Code Review — trace tay toàn bộ 21 test case (đặc biệt thứ tự Onion và phân biệt 404/405), không phát hiện lỗi
  - [x] Self Architecture Review — đối chiếu đủ 10 nguyên tắc, không phát hiện vấn đề
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật, 0 Errors/Failures/Warnings/Deprecations → **CMS-006 COMPLETED — tag `v0.0.6`**
  - [x] **Architecture Review tổng thể HTTP Layer (sau khi Completed)** — phát hiện `Request::fromGlobals()` chỉ đọc `$_POST`, bỏ sót JSON body (PHP không tự điền `$_POST` khi `Content-Type: application/json`) → sẽ chặn cứng `POST /api/v1/auth/login` (module Auth, Phase 3) ngay khi bắt đầu. Đã vá: `resolveBody()` đọc `php://input` + `json_decode` khi Content-Type là JSON, fallback `$_POST` cho form thường; sửa `extractHeaders()` bắt thêm `CONTENT_TYPE`/`CONTENT_LENGTH` (2 header không có tiền tố `HTTP_` trong `$_SERVER`, khác mọi header khác). Thêm 3 test (`tests/Core/Http/RequestFromGlobalsTest.php`, dùng stream wrapper giả lập `php://input`). **Cần chạy lại `vendor/bin/phpunit` để xác nhận không phá vỡ 24 test cũ của CMS-006 trước khi coi v0.0.6 thực sự ổn định.**
  - [ ] Ghi nhận (không chặn): `Response::json()` chưa tự bọc chuẩn `{success, data, message, errors}` — cân nhắc thêm `Response::apiSuccess()/apiError()` khi module API đầu tiên viết, không bắt buộc phải làm ở CMS-006/007. `dispatch()` ném exception (không trả `Response`) cho 404/405 — CMS-011/012 phải bắt và map thành `Response`, ghi rõ ở đây để không quên.
- [x] **CMS-007** — `core/Session.php` (wrapper duy nhất quanh PHP Session)
  - [x] Design Review + 8 điểm bổ sung: Session chỉ Storage (không login/logout/check quyền), Lazy Start (không tự start trong constructor), Flash đúng vòng đời 1 request (hết hạn theo tuổi request, không phải "xoá khi đọc"), Namespace dot-notation lồng nhau giống `Config::get()` (`auth.user_id/roles/permissions`, `csrf.token`, `locale.current`, `tenant.current`), Security (`regenerate()`, `destroy()` xoá cả cookie, cookie params đầy đủ từ Config), API tối giản (không `push()/increment()/decrement()`)
  - [x] `core/Session.php`, `core/SessionException.php` (guard gọi trước `start()`)
  - [x] `tests/Fixtures/config/auth.php` (fixture) + `tests/Core/SessionTest.php` (13 test — mô phỏng 3 "request" trong 1 test qua `session_write_close()`/`start()` lại để test đúng vòng đời Flash)
  - [x] Self Code Review — trace tay Flash lifecycle qua 3 "request" mô phỏng, không phát hiện lỗi
  - [x] Self Architecture Review — đối chiếu đủ 8 nguyên tắc, không phát hiện vấn đề
  - [x] **Sửa Risky Test** (phát hiện qua PHPUnit thật) — `RouterTest::testDoesNotConfuseNotFoundWithMethodNotAllowed` dựa vào type của `catch` để "xác minh ngầm", không có assertion thật → PHPUnit báo Risky. Viết lại: `catch (Throwable)` rộng + `assertInstanceOf()`/`assertNotInstanceOf()` tường minh cho cả 2 chiều (404 không được là 405, 405 không được là 404) trong cùng 1 test — không dùng `@doesNotPerformAssertions`. Rà soát toàn bộ `catch` khác trong test suite, xác nhận không còn risky nào khác.
  - [x] **Sửa Deprecation** (phát hiện qua PHPUnit thật, root cause do người dùng phân tích) — `Tests\Fixtures\Http\PhpInputStreamStub` không khai báo property `$context` tường minh; PHP Stream Wrapper API tự gán `$stream->context` khi đăng ký wrapper → PHP 8.2+ báo "Creation of dynamic property" Deprecated. Sửa: khai báo `public mixed $context = null;` tường minh (không dùng `#[AllowDynamicProperties]` vì chỉ che cảnh báo, không sửa gốc). Rà soát toàn bộ project — xác nhận đây là stream wrapper tự viết DUY NHẤT, không còn chỗ nào khác.
  - [ ] **Cần chạy lại `vendor/bin/phpunit`** để xác nhận 0 Errors/Failures/Warnings/Risky/Deprecations trước khi đánh dấu CMS-007 Completed
- [ ] **CMS-008** — `core/Cache.php` + `core/Cache/CacheDriver.php` (interface) + `FileCacheDriver` + `RedisCacheDriver`
- [ ] **CMS-009** — `core/Hook.php` (Event Dispatcher lõi + Action/Filter)
- [ ] **CMS-010** — Middleware cụ thể: `TenantResolverMiddleware` (stub), `AuthMiddleware`, `CsrfMiddleware`... (cơ chế Pipeline đã xong ở CMS-006, đây là các implementation nghiệp vụ cắm vào)
- [ ] **CMS-011** — `public/index.php` (entry point, bootstrap Container, đăng ký binding, chạy Router)
- [ ] **CMS-012** — Exception/Error Handler cơ bản (log ra `storage/logs`)
- [ ] **CMS-013** — Route kiểm thử tạm `/health` (JSON chuẩn `{success, data, message, errors}`) — xác minh pipeline end-to-end, sẽ gỡ bỏ khi có module thật

## Phase 2 — Database Migration (Tenant/Auth/User)

Chưa bắt đầu. Xem `database-design.md` mục 2.

## Phase 3 — Module Auth / User / Role

Chưa bắt đầu.

## Phase 4+ — Theme Engine, Page, Post, Product, Media, Menu, Form, SEO, Settings, Plugin

Chưa bắt đầu. Thứ tự tham khảo `00-master-spec.md`.
