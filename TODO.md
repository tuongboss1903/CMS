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
  - [x] **Architecture Review tổng thể HTTP Layer (sau khi Completed)** — phát hiện `Request::fromGlobals()` chỉ đọc `$_POST`, bỏ sót JSON body (PHP không tự điền `$_POST` khi `Content-Type: application/json`) → sẽ chặn cứng `POST /api/v1/auth/login` (module Auth, Phase 3) ngay khi bắt đầu. Đã vá: `resolveBody()` đọc `php://input` + `json_decode` khi Content-Type là JSON, fallback `$_POST` cho form thường; sửa `extractHeaders()` bắt thêm `CONTENT_TYPE`/`CONTENT_LENGTH` (2 header không có tiền tố `HTTP_` trong `$_SERVER`, khác mọi header khác). Thêm 3 test (`tests/Core/Http/RequestFromGlobalsTest.php`, dùng stream wrapper giả lập `php://input`). **Verified** — xác nhận không phá vỡ test cũ.
  - [ ] Ghi nhận (không chặn): `Response::json()` chưa tự bọc chuẩn `{success, data, message, errors}` — cân nhắc thêm `Response::apiSuccess()/apiError()` khi module API đầu tiên viết, không bắt buộc phải làm ở CMS-006/007. `dispatch()` ném exception (không trả `Response`) cho 404/405 — CMS-011/012 phải bắt và map thành `Response`, ghi rõ ở đây để không quên.
- [x] **CMS-007** — `core/Session.php` (wrapper duy nhất quanh PHP Session)
  - [x] Design Review + 8 điểm bổ sung: Session chỉ Storage (không login/logout/check quyền), Lazy Start (không tự start trong constructor), Flash đúng vòng đời 1 request (hết hạn theo tuổi request, không phải "xoá khi đọc"), Namespace dot-notation lồng nhau giống `Config::get()` (`auth.user_id/roles/permissions`, `csrf.token`, `locale.current`, `tenant.current`), Security (`regenerate()`, `destroy()` xoá cả cookie, cookie params đầy đủ từ Config), API tối giản (không `push()/increment()/decrement()`)
  - [x] `core/Session.php`, `core/SessionException.php` (guard gọi trước `start()`)
  - [x] `tests/Fixtures/config/auth.php` (fixture) + `tests/Core/SessionTest.php` (13 test — mô phỏng 3 "request" trong 1 test qua `session_write_close()`/`start()` lại để test đúng vòng đời Flash)
  - [x] Self Code Review — trace tay Flash lifecycle qua 3 "request" mô phỏng, không phát hiện lỗi
  - [x] Self Architecture Review — đối chiếu đủ 8 nguyên tắc, không phát hiện vấn đề
  - [x] **Sửa Risky Test** (phát hiện qua PHPUnit thật) — `RouterTest::testDoesNotConfuseNotFoundWithMethodNotAllowed` dựa vào type của `catch` để "xác minh ngầm", không có assertion thật → PHPUnit báo Risky. Viết lại: `catch (Throwable)` rộng + `assertInstanceOf()`/`assertNotInstanceOf()` tường minh cho cả 2 chiều (404 không được là 405, 405 không được là 404) trong cùng 1 test — không dùng `@doesNotPerformAssertions`. Rà soát toàn bộ `catch` khác trong test suite, xác nhận không còn risky nào khác.
  - [x] **Sửa Deprecation** (phát hiện qua PHPUnit thật, root cause do người dùng phân tích) — `Tests\Fixtures\Http\PhpInputStreamStub` không khai báo property `$context` tường minh; PHP Stream Wrapper API tự gán `$stream->context` khi đăng ký wrapper → PHP 8.2+ báo "Creation of dynamic property" Deprecated. Sửa: khai báo `public mixed $context = null;` tường minh (không dùng `#[AllowDynamicProperties]` vì chỉ che cảnh báo, không sửa gốc). Rà soát toàn bộ project — xác nhận đây là stream wrapper tự viết DUY NHẤT, không còn chỗ nào khác.
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (94 tests, 140 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations → **CMS-007 COMPLETED — tag `v0.0.7`**
- [x] **CMS-008** — `core/Cache.php` + `core/Cache/{CacheDriver,FileCacheDriver,RedisCacheDriver,CacheException}.php` (Cache Layer)
  - [x] Design Review: interface `CacheDriver` tối giản (không tag ở driver), Tag support đặt ở `Cache` facade (registry key portable qua mọi driver), Tenant key là quy ước đặt tên (không phải API riêng, nhất quán `Database`/`QueryBuilder`), `RedisCacheDriver` dùng `ext-redis` (không thêm composer dependency), Redis test có điều kiện (`markTestSkipped` nếu không có Redis thật)
  - [x] `core/Cache.php`, `core/Cache/CacheDriver.php`, `core/Cache/FileCacheDriver.php` (atomic write: file tạm + `rename()`, chống corrupt khi nhiều PHP-FPM worker ghi đồng thời), `core/Cache/RedisCacheDriver.php`, `core/Cache/CacheException.php`
  - [x] `tests/Core/Cache/FileCacheDriverTest.php` (8 test), `tests/Core/CacheTest.php` (8 test — facade, tag, prefix, `remember()`), `tests/Core/Cache/RedisCacheDriverTest.php` (4 test, tự skip nếu môi trường không có Redis), `tests/Core/CacheContainerIntegrationTest.php` (1 test — Regression: ráp qua Container)
  - [x] Self Code Review — trace tay toàn bộ 21 test case mới (đặc biệt cơ chế tag qua registry key và atomic write), không phát hiện lỗi. Không sửa file nào của 7 Core Component trước đó — không có rủi ro regression từ phía đó.
  - [x] Self Architecture Review — SOLID/KISS/DRY/YAGNI/Security/Performance/Testability đều đạt, không phát hiện vấn đề
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (115 tests, 170 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (`RedisCacheDriverTest` — môi trường không có `ext-redis`) → **CMS-008 COMPLETED — tag `v0.0.8`**
  - [x] **Architecture Review riêng Cache Layer (sau khi Completed)** — phát hiện & sửa: `RedisCacheDriver::connection()` chỉ bắt `RedisException`, nhưng `ext-redis` mặc định KHÔNG ném exception cho `auth()`/`select()` thất bại (chỉ trả `false`) → sai password/database index bị bỏ qua âm thầm. Sửa: kiểm tra tường minh giá trị trả về của `connect()/auth()/select()`, ném `CacheException` rõ ràng ngay tại điểm lỗi. Ghi nhận Technical Debt: File cache không có cơ chế dọn định kỳ entry hết hạn chưa đọc lại (lazy-only expiry), registry tag không có TTL riêng — cần cron dọn khi lên production thật.
- [x] **CMS-009** — `core/Hook.php` (Hook System kiểu WordPress — Action/Filter/priority/wildcard, qua Container)
  - [x] Design: unified registry cho Action+Filter (khác nhau ở cách gọi `do()`/`apply()`, không khác cách đăng ký — đúng cách WordPress làm bên trong), priority mặc định 10 (số nhỏ chạy trước), wildcard trộn đúng thứ tự priority với exact match (không tách riêng trước/sau), cách ly lỗi try/catch riêng từng callback + điểm mở `onError()` (đúng `13-module-plugin.md`), không static/global/ham global, instance duy nhất trong 1 request qua Container
  - [x] `core/Hook.php`
  - [x] `tests/Core/HookTest.php` (17 test: priority, insertion order, filter chain, remove, wildcard, cách ly lỗi, onError, truyền tham số) + `tests/Core/HookContainerIntegrationTest.php` (2 test — Regression: singleton qua Container, auto-wire không cần bind tường minh)
  - [x] Self Code Review — trace tay toàn bộ 19 test case (đặc biệt thứ tự trộn wildcard+exact theo priority, và filter giữ nguyên giá trị khi 1 callback lỗi giữa chuỗi), không phát hiện lỗi. Không sửa file nào của 8 Core Component trước đó.
  - [x] Self Architecture Review — SOLID/KISS/DRY/YAGNI/Security/Performance/Testability đều đạt
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (133 tests, 192 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-009 COMPLETED — tag `v0.0.9`**

## Roadmap Phase 1+ (phần còn lại) — CHÍNH THỨC, xác nhận ngày hôm nay

Thay thế hoàn toàn kế hoạch cũ (Middleware cụ thể / `public/index.php` riêng / Error Handler riêng / `/health` riêng). Đối chiếu đã chốt: **Error Handler + `/health`** gộp vào CMS-012 (Application/Bootstrap — nơi tự nhiên để wire global exception handling và route kiểm thử pipeline); **`TenantResolverMiddleware` + `AuthMiddleware`/`CsrfMiddleware`** gộp vào CMS-015 (Authentication — lúc đó mới thực sự cần Auth/Tenant context trong Middleware Pipeline).

- [x] **CMS-010** — Module Manager (`core/ModuleManager.php` + `core/Module/*`)
  - [x] Design: discover module qua `module.json` (glob), resolve thứ tự load bằng topological sort + phát hiện circular dependency (**cùng mô hình** `Container::resolve()` — stack `resolving`, chặn tại chỗ), `boot()` nạp `routes.php` của module đã bật vào `Router` qua closure cô lập scope. Không tự query DB để biết module nào "bật" — nhận `enabledKeys` từ bên ngoài (giữ core trung lập, nhất quán `Database`/`View`/`Cache`).
  - [x] `core/ModuleManager.php`, `core/Module/{ModuleDescriptor,ModuleException,ModuleNotFoundException,CircularModuleDependencyException}.php`
  - [x] `tests/Fixtures/Modules/{Alpha,Beta,Circular1,Circular2,NoRoutes}/*`, `tests/Fixtures/ModulesInvalid/BadModule/module.json` + `tests/Core/ModuleManagerTest.php` (9 test) + `tests/Core/ModuleManagerContainerIntegrationTest.php` (1 test Regression — qua Container+Router)
  - [x] Self Code Review — trace tay toàn bộ 10 test case (đặc biệt: thứ tự load đúng dependency dù `enabledKeys` liệt kê ngược thứ tự), không phát hiện lỗi. Không sửa file nào của 9 Core Component trước đó.
  - [x] Self Architecture Review — SOLID/KISS/DRY/YAGNI/Security/Performance/Testability đều đạt
  - [ ] **Cần chạy `vendor/bin/phpunit`** để xác nhận PASS thật trên máy (môi trường này không có PHP)
- [ ] **CMS-011** — Plugin Manager (`core/PluginManager.php`) — load/enable/disable plugin theo site, cách ly lỗi (đúng `13-module-plugin.md`)
- [ ] **CMS-012** — Application / Bootstrap (`public/index.php`) — entry point thật, đăng ký binding Container, chạy Router, **Exception/Error Handler** (log `storage/logs`), route kiểm thử `/health`
- [ ] **CMS-013** — Migration System (`core/Database` migration runner) — hạ tầng chạy migration, chưa phải migration Phase 2 thật
- [ ] **CMS-014** — Validation Layer
- [ ] **CMS-015** — Authentication (`AuthService`, `TenantResolverMiddleware`, `AuthMiddleware`, `CsrfMiddleware`)
- [ ] **CMS-016** — Authorization (RBAC)
- [ ] **CMS-017** — Module: User
- [ ] **CMS-018** — Module: Page
- [ ] **CMS-019** — Module: SEO

## Phase 2 — Database Migration thật (bảng `sites`, `users`, `roles`...)

Chưa bắt đầu, phụ thuộc CMS-013 (Migration System). Xem `database-design.md` mục 2.

## Phase 3+ — Module Auth/User/Role/Page/SEO...

Tương ứng CMS-014 → CMS-019 ở trên và các module tiếp theo tham khảo `00-master-spec.md`.
