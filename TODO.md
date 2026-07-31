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
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (144 tests, 212 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-010 COMPLETED — tag `v0.0.10`**
- [x] **CMS-011** — Application / Bootstrap (`core/Application.php` + `public/index.php`)
  - [x] Design Review + 4 quyết định xác nhận: `View` dùng `config('app.theme')` cho cả active/default theme (chưa có TenantManager), `ModuleManager::boot()` mặc định bật tất cả module đã `discover()`, log lỗi qua `file_put_contents` trực tiếp (chưa xây `Core\Logger` đầy đủ — ngoài phạm vi), response lỗi luôn JSON `{success,data,message,errors}` (chưa phân biệt SSR/API)
  - [x] `core/Application.php` — `handle(Request): Response` thuần (test được) tách khỏi `run(): void` (I/O boundary duy nhất). `boot()` idempotent (guard `$booted`). Debug gate cho message lỗi 500 (`config('app.debug')`). Bổ sung 1 method ngoài thiết kế đã duyệt: `container(): Container` (cần thiết để test xác nhận Core Service đăng ký đúng, đã ghi rõ trong docblock)
  - [x] Sửa `config/app.php` (thêm key `theme`), sửa `public/index.php` (thay smoke test bằng bootstrap thật, còn 3 dòng)
  - [x] `tests/Fixtures/App/*` (config đầy đủ + module + theme fixture), `tests/Fixtures/AppProduction/*` (fixture riêng cho test `debug=false`) + `tests/Core/ApplicationTest.php` (11 test: health route, module route, 404/405/500, debug gate, logging, Container wiring, singleton, boot idempotent)
  - [x] Self Code Review — trace tay toàn bộ 11 test case, không phát hiện lỗi. Không sửa file nào của 9 Core Component trước đó ngoài `config/app.php` (đã duyệt) và `public/index.php` (đúng phạm vi task)
  - [x] Self Architecture Review — SOLID/KISS/YAGNI/Security/Performance/Testability đều đạt
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (154 tests, 237 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-011 COMPLETED — tag `v0.0.11`**
- [x] **CMS-012** — Plugin Manager (`core/PluginManager.php` + `core/Plugin/*`)
  - [x] Design Review + quyết định chốt trước khi code: `PluginManager` độc lập hoàn toàn với `ModuleManager` (không refactor, không chia sẻ abstraction, chấp nhận trùng lặp logic topological sort để giữ ổn định), `discover()` memoize trong instance (khác chủ đích với `ModuleManager::discover()`), `boot()` reset `failures` mỗi lần gọi, cách ly lỗi tuyệt đối ở tầng `require Hooks.php` (không rethrow), giữ public API tối giản (không thêm interface/abstraction ngoài phạm vi)
  - [x] `core/PluginManager.php`, `core/Plugin/{PluginDescriptor,PluginException,PluginNotFoundException,CircularPluginDependencyException}.php`
  - [x] `tests/Fixtures/Plugins/{GoodPluginA,GoodPluginB,BrokenPlugin,NoHooksPlugin,CircularA,CircularB,ScopeCheckPlugin}/*`, `tests/Fixtures/PluginsInvalid/BadPlugin/plugin.json`, `tests/Fixtures/PluginsDuplicate/{PluginX,PluginY}/plugin.json` + `tests/Core/PluginManagerTest.php` (16 test) + `tests/Core/PluginManagerContainerIntegrationTest.php` (2 test Regression)
  - [x] Sửa `core/Application.php` — CHỈ 2 điểm: đăng ký `PluginManager` vào Container, gọi `pluginManager->boot()` trong `boot()` ngay sau `ModuleManager`, không sửa gì khác
  - [x] Self Code Review — trace tay toàn bộ 18 test case (đặc biệt: thứ tự nạp theo dependency qua filter chain `plugin.trace`, cô lập lỗi 1 plugin không ảnh hưởng plugin khác, reset `failures` mỗi lần `boot()`, scope `Hooks.php` chỉ thấy đúng `$hook`), không phát hiện lỗi. Không sửa file nào của `ModuleManager`/`Hook`/`Container`/9 Core Component trước đó ngoài `core/Application.php` (đúng phạm vi đã duyệt)
  - [x] Self Architecture Review — SRP/Dependency/KISS/YAGNI/không premature optimization/không trùng Public API/`ModuleManager` không bị ảnh hưởng/không phát sinh Technical Debt mới ngoài trùng lặp đã được chấp nhận trước — đều đạt
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (171 tests, 273 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-012 COMPLETED — tag `v0.0.12`**
- [x] **CMS-013** — Migration System (`core/MigrationManager.php` + `core/Migration/*` + `bin/migrate.php`)
  - [x] Adversarial Architecture Review trước khi code — phát hiện & xử lý: driver detection không được tự đọc PDO (`getAttribute()`), không thêm API mới cho `Database`; Container trong `bin/migrate.php` sẽ trùng lặp logic wiring `Database` với `Application.php` nên bị loại; định dạng migration file chọn Closure-array (không interface, không class, không DSL)
  - [x] Final Design 8 quyết định chốt: (1) Closure-array `['up'=>Closure,'down'=>Closure]`, (2) DDL raw SQL qua `Database::statement()` — không Schema Builder, (3) rollback theo batch, (4) CLI entry point riêng `bin/migrate.php` — không sửa `Application.php`/`public/index.php`, (5) không dùng Container trong CLI, (6) fail-fast tuyệt đối (khác `ModuleManager`/`PluginManager`), (7) giữ `status()` trong Public API, (8) `driver: string` truyền qua constructor từ `Config`, `MigrationManager` không biết PDO
  - [x] `core/MigrationManager.php`, `core/Migration/{MigrationException,MigrationNotFoundException}.php`, `bin/migrate.php`
  - [x] `tests/Fixtures/Migrations/{Valid,Failing,Malformed}/*` + `tests/Core/MigrationManagerTest.php` (16 test: discover, migrate thành công, batch tăng dần qua nhiều lần chạy, status applied/pending, rollback theo batch đảo ngược, rollback rỗng khi chưa có batch, malformed migration, fail-fast dừng ngay khi lỗi, `MigrationNotFoundException` khi file bị xoá, validate driver, regression không ảnh hưởng bảng khác)
  - [x] Không sửa file nào trong 12 Core Component đã hoàn thành (`Application`/`Database`/`Container`/`Config`/`Router`/`View`/`Session`/`Cache`/`Hook`/`ModuleManager`/`PluginManager`) — 0 file Core cũ bị đụng, đúng Decision #4/#5/#8
  - [x] Self Code Review — trace tay toàn bộ 16 test case, phát hiện 1 sai sót đặt tên (không phải bug logic): fixture ban đầu dùng `ALTER TABLE ... DROP COLUMN` (rủi ro không tương thích SQLite cũ) + tên file không khớp nội dung — đã sửa thành 2 migration `CREATE TABLE` độc lập
  - [x] Self Architecture Review — SRP (chấp nhận mức gộp trách nhiệm tương đương `ModuleManager`), Dependency (không PDO, không Container, không API mới cho `Database`), KISS/YAGNI đạt
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (185 tests, 299 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-013 COMPLETED — tag `v0.0.13`**
- [x] **CMS-014** — Validation Layer (`core/Validator.php` + `core/Validation/*`)
  - [x] Design Review + 6 quyết định chốt: registry rule dạng closure nội bộ (không class-per-rule), trả `ValidationResult` thay vì throw cho input sai (chỉ throw `ValidationException` khi rule name không tồn tại — lỗi cấu hình), không đăng ký Container/Application (giống `MigrationManager`, không có state cần singleton), chỉ 1 rule format string kiểu Laravel, giữ `extend()` custom rule, validate hết toàn bộ rule của 1 field (không bail)
  - [x] `core/Validator.php`, `core/Validation/{ValidationResult,ValidationException}.php`
  - [x] `tests/Core/ValidatorTest.php` (31 test: passes/fails/errors/firstError, 16 rule built-in, custom rule qua `extend()`, custom message, unknown rule throw, field optional bị bỏ qua, nhiều lỗi tích luỹ trên 1 field)
  - [x] Không sửa file nào trong 13 Core Component đã hoàn thành — 0 dependency vào Config/Container/Database/Router/Session/Hook/ModuleManager/PluginManager/MigrationManager
  - [x] Self Code Review — phát hiện & sửa 2 vấn đề: (1) test tự viết sai logic (`min`/`max` mâu thuẫn số học trên cùng 1 giá trị) — sửa lại rule độc lập không mâu thuẫn; (2) bug thật trong rule `in` — ép `(string) $value` trực tiếp gây PHP Warning nếu `$value` là mảng, sửa bằng guard `is_scalar()`
  - [x] Self Architecture Review — SRP/Coupling/Cohesion/KISS/YAGNI đạt, 0 dependency vào Core Component khác
  - [x] **Verified** — `vendor/bin/phpunit` PASS (bao gồm trong lần chạy toàn bộ suite cùng CMS-015, 229 tests/382 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-014 COMPLETED — tag `v0.0.14`**
- [x] **CMS-015** — HTTP Request Layer (mở rộng `core/Http/Request.php` đã có từ CMS-006)
  - [x] Architecture Analysis phát hiện xung đột quan trọng: yêu cầu ban đầu mô tả trùng trách nhiệm với `Core\Http\Request` đã hoàn thành (v0.0.6) — chốt Decision #0: CMS-015 là MỞ RỘNG file đã có (không tạo Request thứ 2), mọi API mới additive, không breaking method cũ
  - [x] Final Design 9 quyết định chốt: giữ nguyên 8 method cũ (chỉ thêm alias), tham số constructor mới (`files/cookies/server`) đặt cuối với default `[]`, không Container/singleton, copy dữ liệu 1 lần, giữ eager JSON parsing, không phụ thuộc Session/Validator/Database/Auth, không Method Spoofing (`_method`), không Trusted Proxy (`ip()` chỉ đọc `REMOTE_ADDR`)
  - [x] Sửa `core/Http/Request.php` — CHỈ additive: thêm `files/cookies/server` (cuối constructor), `fromGlobals()` đọc thêm `$_FILES/$_COOKIE/$_SERVER`, `withRouteParams()` giữ nguyên 3 property mới, thêm 13 method (`method/uri/path/all/has/filled/cookie/file/ip/userAgent/isMethod/ajax/json`)
  - [x] `tests/Core/Http/RequestTest.php` (+14 test), `tests/Core/Http/RequestFromGlobalsTest.php` (+1 test)
  - [x] Rà soát toàn bộ ~30 lệnh gọi `new Request(...)` hiện có trong codebase — xác nhận 100% backward compatible (đều dùng ≤7 tham số, không bị ảnh hưởng bởi 3 tham số mới)
  - [x] Self Code Review — phát hiện & sửa 2 failure PHPUnit thật: test `ajax()`/`json()` tự viết dùng header key sai convention (chưa uppercase, trong khi `header()` chỉ chuẩn hoá phía tra cứu, không chuẩn hoá key đã lưu — hành vi đã có từ CMS-006, không sửa production code) — sửa đúng phạm vi trong test data
  - [x] Self Architecture Review — 0 dependency mới, đúng cả 9 Decision, không breaking Public API
  - [x] **Verified** — `vendor/bin/phpunit` PASS trên môi trường thật (229 tests, 382 assertions), 0 Errors/Failures/Warnings/Risky/Deprecations, 4 Skipped đúng thiết kế (Redis) → **CMS-015 COMPLETED — tag `v0.0.15`**
- [ ] **CMS-016** — HTTP Response Layer (mở rộng `core/Http/Response.php` đã có từ CMS-006, theo cùng tinh thần CMS-015)
- [ ] Redirect — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Middleware Pipeline (mở rộng) — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Exception Handler — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] CSRF — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Authentication (`AuthService`, `TenantResolverMiddleware`, `AuthMiddleware`) — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Authorization (RBAC) — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Event / Queue — chưa đánh số CMS, sẽ xác nhận khi bắt đầu
- [ ] Module System (Module: User/Page/SEO...) — chưa đánh số CMS, sẽ xác nhận khi bắt đầu

## Phase 2 — Database Migration thật (bảng `sites`, `users`, `roles`...)

Chưa bắt đầu, phụ thuộc CMS-013 (Migration System). Xem `database-design.md` mục 2.

## Phase 3+ — Module Auth/User/Role/Page/SEO...

Tương ứng CMS-014 → CMS-019 ở trên và các module tiếp theo tham khảo `00-master-spec.md`.
