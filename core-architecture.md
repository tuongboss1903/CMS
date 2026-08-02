# CORE ARCHITECTURE — CMS Đa Website

> Trạng thái: **CHÍNH THỨC** — mô tả kiến trúc Core Foundation đã hoàn thành (CMS-001 → CMS-043 + Public Website Polish, tag `v0.0.1` → `v0.0.51`; không có `v0.0.17` — CMS-017 chỉ là Architecture Decision, không phát sinh code; không có `v0.0.32` — nhãn "CMS-032" bị huỷ ngay khi phát hiện trùng lặp phạm vi, công việc dồn thẳng vào CMS-033; không có `v0.0.39` — bỏ qua khi chuyển từ CMS Foundation Completion sang Product Development, CMS-040 nối tiếp trực tiếp CMS-038; CMS-044/045/046/047 triển khai trước CMS-041/042/043 — tag không theo thứ tự số task, mà theo thứ tự hoàn thành thật). Từ CMS-034, `modules/` không còn rỗng — Module thật đầu tiên (`Auth`) đã tồn tại, xem mục 3.27. Từ CMS-038, có thêm `modules/Role/`+`modules/Dashboard/`+`bin/bootstrap.php`, xem mục 3.28. Từ CMS-040, có thêm `modules/Page/` + Content Schema thật đầu tiên (`pages`), xem mục 3.29. Từ CMS-044, có thêm `modules/Public/` + `themes/default/` — CMS lần đầu render được website HTML thật (không còn thuần Headless API), xem mục 3.30. Từ CMS-045, có thêm `modules/Admin/` + `themes/default/views/admin/` — CMS lần đầu có giao diện quản trị HTML (login + dashboard) và CSRF lần đầu được gắn vào route thật, xem mục 3.31. Từ CMS-046, `modules/Admin/` mở rộng với Admin User Management UI (List/Create/Edit/Lock/Unlock/Assign Role dạng HTML), xem mục 3.32. Từ CMS-047, `modules/Admin/` mở rộng với Admin Role Management UI (List/Create/Edit/Delete/Permission Assignment dạng HTML), xem mục 3.33. Từ CMS-041, có thêm `modules/Media/` — Content Module thứ 2 (sau `pages`), lần đầu kích hoạt `sites.storage_used_bytes`, xem mục 3.34. Từ CMS-042, có thêm `modules/Menu/` — Content Module thứ 3, xem mục 3.35. Từ CMS-043, có thêm `modules/Seo/` — Content Module thứ 4, lần đầu dùng pattern upsert (SELECT rồi rẽ nhánh INSERT/UPDATE), xem mục 3.36. Từ Public Website Polish, `modules/Public/*` lần đầu render Navigation Menu + SEO meta + JSON-LD + 404 themed page (trước đó chỉ render `pages` thuần), xem mục 3.37. **Lưu ý khoảng trống tài liệu đã biết**: `modules/User/` (CMS-037) chưa có mục riêng trong tài liệu này (Documentation Completion của CMS-037 chỉ giới hạn `TODO.md`/`CHANGELOG.md` theo yêu cầu lúc đó) — không phải sai sót của lượt cập nhật này. Tài liệu này tổng hợp lại toàn bộ quyết định thiết kế đã chốt qua các vòng Design Review/Code Review/Architecture Review — dùng làm tài liệu tham chiếu khi viết Module (Phase 3+), không lặp lại chi tiết đã có trong `cms-architecture-proposal.md`/`database-design.md`.
>
> **`public/index.php` nay đã là bootstrap thật** (`Application::bootstrap(dirname(__DIR__))->run()`), không còn là smoke test — sơ đồ mục 2 dưới đây giờ mô tả đúng luồng chạy thực tế.

---

## 1. Mục tiêu Core Foundation

Dựng đủ hạ tầng tự viết (không framework nền, đúng quyết định chính thức) để 1 request HTTP đi trọn vẹn qua: `Request → Router → Middleware Pipeline → Controller Resolver → Controller (dùng Container/Database/View/Session) → Response`. Đây là nền tảng mọi Module (Auth, Page, Post...) ở Phase 3+ sẽ xây trên đó — **không sửa core khi viết module**, chỉ tiêu thụ API đã ổn định.

## 2. Sơ đồ tổng thể

```
                              ┌─────────────┐
                              │   Config    │  (doc config/*.php, dot-notation)
                              └──────┬──────┘
                                     │ inject
              ┌──────────────────────┼──────────────────────┐
              ▼                      ▼                      ▼
        ┌───────────┐          ┌──────────┐           ┌───────────┐
        │ Database  │          │   View   │           │  Session  │
        │(+QueryB.) │          │(Theme    │           │(PHP native│
        │           │          │ Engine)  │           │ wrapper)  │
        └───────────┘          └──────────┘           └───────────┘
              ▲                      ▲                      ▲
              │                      │                      │
              └──────────────────────┼──────────────────────┘
                                      │ dung qua Controller
                                      │
Request ──► Router ──► MiddlewarePipeline ──► ControllerResolver ──► Controller ──► Response
              │                                      │
              └──────────── resolve qua ─────────────┘
                              Container (PSR-11 DI)
```

`Cache` không nằm trong sơ đồ trên vì là **cross-cutting concern** (giống `Config`) — Repository/Controller/Middleware tương lai gọi trực tiếp khi cần, không phải 1 bước cố định trong pipeline request.

**Nguyên tắc quan hệ cốt lõi:**
- `Config` là gốc — mọi component khác nhận cấu hình qua nó (constructor injection), không đọc `getenv()` trực tiếp ở đâu khác.
- `Database`, `View`, `Session` **độc lập với nhau**, không component nào phụ thuộc trực tiếp 2 component còn lại — chỉ Controller (do `ControllerResolver` dựng qua `Container`) mới kết hợp chúng.
- `Router` không phụ thuộc `Database`/`View`/`Session` — chỉ điều phối, đúng "Router chỉ Routing".
- `Container` là cơ chế lắp ráp trung tâm — mọi Controller/Service/Repository (module sau này) đều được resolve qua đây, không `new` trực tiếp.

## 3. Chi tiết từng Component

### 3.1. `Config` (`core/Config.php`) — v0.0.2

Nạp toàn bộ `config/*.php`, đọc qua dot-notation (`get('database.connections.mysql.host')`). Instance-based (không static — sửa từ bản đầu vi phạm "No Global Variable"). API: `get()/has()/all()`.

### 3.2. `Container` (`core/Container.php` + 3 exception) — v0.0.3

DI Container tự viết, implements `Psr\Container\ContainerInterface` (PSR-11 thật). Auto-wiring qua Reflection — **chỉ Constructor Injection**. Phát hiện Circular Dependency (stack `resolving`, chặn tại chỗ, không đệ quy vô hạn). Singleton chỉ sống trong vòng đời 1 Container instance — **mỗi request phải tạo Container mới** (chưa thực thi vì `public/index.php` là CMS-011, chưa làm). API: `bind()/singleton()/instance()/get()/has()/make()`.

### 3.3. `Database` + `QueryBuilder` (`core/Database.php`, `core/QueryBuilder.php`, `core/Database/*`) — v0.0.4

`Database`: class duy nhất chạm PDO trực tiếp — connection (lazy, hỗ trợ `mysql`/`sqlite`), execute, Transaction API nested-safe (`transaction(Closure)` khuyến dùng), query log tối giản. `QueryBuilder`: fluent SQL builder, không tự thực thi, luôn giao `Database`. `SqlCompiler` (`@internal`, chỉ `QueryBuilder` dùng): biên dịch SQL thuần function. `IdentifierValidator`: whitelist tên cột/bảng chống injection. `forTenant()` là sugar cho multi-tenant, không business logic.

### 3.4. `View` (`core/View.php` + 2 exception) — v0.0.5

Theme Engine PHP thuần (không compiler). View Resolution 2 cấp cố định: `themes/{active}/views/...` → fallback `themes/{default}/views/...` → `ViewNotFoundException`. Layout 1 cấp (`extend/section/endSection/yield`), Partial/Component dùng chung `include()`, escape tường minh (`e()/escape()/raw()`, không tự động).

### 3.5. `Router` + HTTP Layer (`core/Router.php`, `core/Route.php`, `core/Http/*`, `core/Middleware/*`, `core/Router/*`) — v0.0.6

`Request`/`Response`: tự viết nhẹ (không PSR-7/15), Immutable (`with*()` dùng `new self(...)`, không `clone`). `Router`: đăng ký/match route, phân biệt 404 (`RouteNotFoundException`)/405 (`MethodNotAllowedException`), chặn đăng ký trùng Method+URI+Domain ngay lúc boot (`DuplicateRouteException`). `MiddlewarePipeline`: mô hình Onion (Before/After + short-circuit). `ControllerResolver`: bước cuối, resolve Controller qua `Container`.

**Middleware Pipeline (CMS-018 mở rộng `Router`, không đổi `MiddlewareInterface`/`MiddlewarePipeline`)**: `Router` là owner **duy nhất** của middleware lifecycle — `Application` không biết middleware tồn tại. `Router::middleware(array): static` đăng ký Global Middleware (chạy trên MỌI route). `get()/post()/put()/patch()/delete()` nhận thêm tham số cuối `array $middleware = []` để gán middleware cho 1 route đơn lẻ mà không cần bọc `group()`. Thứ tự onion cuối cùng: `Global → Group → Route-specific → Controller` — Global gộp **runtime** trong `dispatch()` (không lưu vào `Route` lúc đăng ký), đảm bảo mọi route luôn nhận đúng global middleware hiện tại bất kể thứ tự gọi. `Route.php` không đổi (vẫn nhận middleware phẳng, không biết nguồn gốc global/group/route).

**Middleware Parameterization (CMS-027, xem chi tiết mục 3.24)**: `MiddlewarePipeline::handle()` từ v0.0.27 chấp nhận `list<class-string<MiddlewareInterface>|MiddlewareInterface>` — mỗi phần tử có thể là class-string (resolve qua `Container`, hành vi cũ) hoặc `MiddlewareInterface` instance đã cấu hình sẵn (dùng trực tiếp). `Router`/`Route` chỉ cập nhật PHPDoc, runtime không đổi.

### 3.6. `Session` (`core/Session.php` + 1 exception) — v0.0.7

Wrapper duy nhất quanh `$_SESSION`/`session_*()`. Chỉ Storage (không login/logout/authorization — thuộc `AuthService` Phase 3). Lazy start. Namespace dot-notation lồng nhau **giống `Config::get()`** (`auth.user_id`, `csrf.token`, `locale.current`, `tenant.current`...). Flash message hết hạn theo tuổi request (2-bucket `_flash_old`/`_flash_new`).

### 3.7. `Cache` (`core/Cache.php` + `core/Cache/*`) — v0.0.8

Facade duy nhất của Cache Layer, qua interface `CacheDriver` (`FileCacheDriver` cho dev/local, `RedisCacheDriver` cho production — dùng `ext-redis`, không composer package). `remember(key, ttl, Closure)` phục vụ Object cache. Tag support (`put(..., tags: [])`/`flushTags()`) đặt ở facade (registry key portable qua mọi driver, không viết riêng cho từng driver). `FileCacheDriver` ghi atomic (file tạm + `rename()`), tên file là `hash(key)`. Tenant key là quy ước đặt tên (`"tenant:{id}:..."`), không phải API riêng — nhất quán `QueryBuilder::forTenant()`.

### 3.8. `Hook` (`core/Hook.php`) — v0.0.9

Hook System kiểu WordPress (Action + Filter) trên 1 registry dùng chung — action/filter chỉ khác cách gọi (`do()` bỏ qua giá trị trả về, `apply()` truyền giá trị qua từng callback), không khác cách đăng ký, đúng cách WordPress triển khai bên trong. Priority mặc định 10 (số nhỏ chạy trước). Wildcard hook (`"post.*"`) trộn đúng thứ tự priority với hook chính xác. Mỗi callback chạy trong `try/catch` riêng (đúng `13-module-plugin.md`), `onError()` là điểm mở cho `PluginManager` (task sau). Không static, không hàm global — 1 instance dùng chung qua `Container` trong 1 request.

### 3.9. `ModuleManager` (`core/ModuleManager.php` + `core/Module/*`) — v0.0.10

Discover module qua `module.json` (glob), resolve thứ tự load bằng topological sort + phát hiện circular dependency (cùng mô hình `Container::resolve()` — stack `resolving`, chặn tại chỗ). `boot(Router, enabledKeys)` nạp `routes.php` của module đã bật vào `Router` qua closure cô lập scope, trả về danh sách key đã nạp (phục vụ log/debug). Không tự query Database để biết module nào "bật" cho tenant nào — nhận `enabledKeys` từ bên ngoài, giữ core trung lập (nhất quán `Database`/`View`/`Cache`).

### 3.10. `Application` (`core/Application.php`) — v0.0.11, cập nhật CMS-012/CMS-019/CMS-029/CMS-030

Điểm khởi động **duy nhất** của framework — `public/index.php` chỉ còn 3 dòng (`Application::bootstrap(dirname(__DIR__))->run()`). `handle(Request): Response` thuần (test được, không cần superglobal) tách khỏi `run(): void` (I/O boundary duy nhất — `Request::fromGlobals()`/`Response::send()`), cùng triết lý `Router::dispatch()` vs `Response::send()`. `boot()` idempotent — nạp `ModuleManager` (mặc định bật tất cả module đã `discover()` cho tới khi có bảng `site_modules` thật, Phase 2+), đăng ký `/health`. Đăng ký toàn bộ Core Service vào `Container` qua Closure lazy. Từ CMS-012, `boot()` cũng nạp `PluginManager` ngay sau `ModuleManager`.

**Logger Integration (CMS-029, `v0.0.29`)** — lần đầu sửa `Application.php` kể từ CMS-019: (1) `registerCoreServices()` đăng ký `Logger` singleton (`logPath` cố định `storage/logs/app.log`, giữ nguyên path cũ) — `Logger` không tự auto-wire được (constructor cần `string $logPath` không default) nên phải đăng ký tường minh, khác các Foundation Component trước; (2) `logException()` (gọi khi response status ≥ 500, điều kiện không đổi) chuyển từ tự viết `mkdir`/`sprintf`/`file_put_contents` sang gọi `Logger::log('error', $message, ['exception_class', 'file', 'line', 'trace'])`; (3) `boot()` đăng ký `Hook::onError()` listener (điểm mở từ CMS-009, trước đó chưa từng có listener) ghi log qua `Logger` khi callback Action/Filter throw, context `['hook', 'exception_class', 'file', 'line']`, không đổi cơ chế cô lập callback của `Hook`. **Không triển khai**: `Database::onQueryExecuted()` (điểm mở từ CMS-004, vẫn chưa có listener — fire cho mọi query, chưa có requirement debug/performance), `PluginManager::getFailures()` logging (khác domain — lifecycle error Plugin, không phải runtime Hook error), log rotation. Không sửa `Logger.php`/`Database.php`/`Hook.php`/`PluginManager.php`/`ExceptionHandler.php`.

**TenantManager Integration (CMS-030, `v0.0.30`)** — lần thứ 2 liên tiếp sửa `Application.php`: (1) `registerCoreServices()` đăng ký `TenantManager::class` **singleton** (bắt buộc — phát hiện qua trace `Container::get()` trước khi báo cáo PHPUnit: không có binding thì không cache dù `class_exists()` cho phép auto-wire, mỗi lần gọi sẽ tạo instance mới, tích hợp sẽ không hoạt động nếu thiếu bước này); sửa Closure `View::class` đọc `TenantManager::current()['theme_active'] ?? config('app.theme')` làm `activeTheme`, giữ `defaultTheme = config('app.theme')` như cũ; (2) `boot()` bọc `$moduleManager->boot($router, ...)` trong `$router->group(['middleware' => [TenantResolverMiddleware::class]], ...)`, `/health` giữ nguyên đăng ký ngoài group. Chữ ký `boot()` không đổi, tính idempotent giữ nguyên. Chi tiết đầy đủ ở mục 3.25. Không sửa `TenantManager.php`/`View.php`/`Router.php`/`Route.php`/`MiddlewarePipeline.php`.

**Exception handling (CMS-019)**: `handle()` chỉ còn **1 nhánh `catch (Throwable)` duy nhất**, delegate mapping cho `ExceptionHandler` (3.16). Quyết định có gọi `logException()` hay không dựa trên `$response->getStatusCode() >= 500` (không còn `instanceof` theo từng loại exception) — `Application` không cần biết tên class exception cụ thể nào. `logException()` giữ nguyên (ghi trực tiếp qua `file_put_contents` vào `storage/logs/app.log`, chưa xây `Core\Logger` đầy đủ tính năng — vẫn ngoài phạm vi, đã xác nhận lại ở CMS-019).

### 3.11. `PluginManager` (`core/PluginManager.php` + `core/Plugin/*`) — v0.0.12

Discover plugin qua `plugin.json` (glob), **memoize kết quả trong instance** (`discover()` chỉ glob + parse 1 lần cho cả vòng đời 1 `PluginManager` — khác chủ đích với `ModuleManager::discover()` không memoize). `resolveLoadOrder()` — topological sort + phát hiện circular dependency, **code độc lập hoàn toàn với `ModuleManager`** (không chia sẻ abstraction dù logic tương tự, chấp nhận trùng lặp có chủ đích để không đụng component đã ổn định, đúng nguyên tắc "không tạo abstraction chỉ để DRY"). `boot(Hook, enabledKeys)` **reset `failures` ở đầu mỗi lần gọi**, nạp `Hooks.php` của từng plugin đã bật theo đúng thứ tự dependency qua closure cô lập scope (chỉ `$hook` khả kiến, cùng kỹ thuật `ModuleManager` dùng cho `routes.php`/`$router`). **Cách ly lỗi tuyệt đối**: chỉ đoạn `require Hooks.php` được try/catch riêng — 1 plugin lỗi được ghi vào `failures[key]` (đọc qua `getFailures()`), không rethrow, không chặn các plugin còn lại nạp tiếp. Lỗi ở tầng `resolveLoadOrder()` (key không tồn tại/dependency chưa bật/circular) vẫn ném ra ngoài `boot()` — coi là lỗi cấu hình, khác bản chất lỗi runtime của 1 plugin. `discover()` phát hiện và ném lỗi tường minh nếu 2 plugin khai trùng `key` (không âm thầm ghi đè).

### 3.12. `MigrationManager` (`core/MigrationManager.php` + `core/Migration/*` + `bin/migrate.php`) — v0.0.13

Quản lý schema database, không chứa business logic, **hoàn toàn tách khỏi HTTP lifecycle** — không có Module/Plugin/Application nào biết tới `MigrationManager`, chạy qua CLI entry point riêng `bin/migrate.php` (tự bootstrap `Config`/`Database` trực tiếp, không qua Container). Migration file trả `['up' => Closure, 'down' => Closure]` (không interface, không class, không DSL). `discover()` glob + sort theo tên file, không memoize. DDL bằng raw SQL qua `Database::statement()` — không Schema Builder/Blueprint. `migrate()`/`rollback()` **fail-fast tuyệt đối** (khác `ModuleManager`/`PluginManager` — không có `getFailures()`, vì các bước thay đổi schema có tính tuần tự/phụ thuộc, cách ly lỗi có thể phá schema). `rollback()` hoàn tác theo batch (`MAX(batch)+1` khi migrate, `ORDER BY id DESC` khi rollback). `driver: string` truyền qua constructor (từ `Config`) — `MigrationManager` **bản thân nó** không bao giờ đọc PDO/`getAttribute()`, không có API mới nào được thêm vào `Database`.

**Migration thật đầu tiên — CMS-028, `v0.0.28`, nhóm Tenant/Auth/Role**: `database/migrations/2026_08_01_00000{1-7}_*.php` — 7 bảng `sites/site_domains/users/roles/permissions/role_permissions/user_site_roles`, đúng schema `database-design.md` mục 2 (đã lược bỏ `ENUM`/`UNSIGNED`/`ON UPDATE CURRENT_TIMESTAMP` — coi là chi tiết triển khai DB, xử lý ở Service layer sau; không seed data; không FK `plans` — bảng chưa tồn tại). **Điểm rẽ nhánh driver duy nhất được chấp thuận** (khác nguyên tắc "MigrationManager không đọc PDO" ở trên — nguyên tắc đó áp dụng cho chính class `MigrationManager`, không cấm migration FILE tự đọc): mỗi migration Closure gọi `$db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME)` (dùng `Database::connection(): PDO` đã public từ CMS-004) để chọn `AUTOINCREMENT` (SQLite) hoặc `AUTO_INCREMENT` (MySQL) cho mệnh đề Primary Key — không có cú pháp SQL chung cho 2 engine ở đúng điểm này. Không rẽ nhánh thêm ở phần schema khác nếu chưa có Architecture Issue Report riêng.

**Technical Debt phát sinh (không sửa trong CMS-028)**: UNIQUE `(tenant_id, name)` ở bảng `roles` không ngăn được 2 role hệ thống (`tenant_id IS NULL`) trùng tên — đúng ANSI SQL semantics (`NULL ≠ NULL` trong composite UNIQUE, giống nhau ở cả SQLite lẫn MySQL, không phải bug migration). Phát hiện qua PHPUnit thật, xử lý để dành CMS Role/Auth Service sau (Service layer tự kiểm tra, không Trigger — nhất quán ràng buộc homepage `database-design.md` mục 6.1).

### 3.13. `Validator` (`core/Validator.php` + `core/Validation/*`) — v0.0.14

Validate `array $data` theo rule string kiểu Laravel (`'required|email|max:255'`), trả `ValidationResult` (không throw cho input sai — chỉ throw `ValidationException` khi rule name không tồn tại, lỗi cấu hình). Registry rule dạng `Closure` là state riêng từng instance, 16 rule built-in đăng ký qua chính `extend()` — built-in và custom dùng chung 1 cơ chế, cho phép ghi đè. Chạy hết toàn bộ rule của 1 field (không bail). **0 dependency vào Core Component khác** — không Container/Application, module tự `new Validator()`.

### 3.14. `Request` — mở rộng ở CMS-015 (v0.0.15)

`core/Http/Request.php` (gốc từ CMS-006, v0.0.6) được mở rộng **additive**: thêm `files/cookies/server` (constructor, cuối cùng, default `[]`), `fromGlobals()` đọc thêm `$_FILES/$_COOKIE/$_SERVER`. Thêm method `method()/uri()/path()` (alias), `all()/has()/filled()`, `cookie()`, `file()` (raw, không abstraction), `ip()` (chỉ `REMOTE_ADDR`, không Trusted Proxy), `userAgent()`, `isMethod()`, `ajax()`, `json()`. Không Method Spoofing. Toàn bộ method cũ (`getMethod/getUri/getHost/query/input/header/routeParam/withRouteParams`) giữ nguyên — 100% backward compatible.

### 3.15. `Response` — mở rộng ở CMS-016 (v0.0.16)

`core/Http/Response.php` (gốc từ CMS-006, v0.0.6) được mở rộng **additive**: thêm `cookies` (constructor, cuối cùng, default `[]`, tách riêng khỏi `headers` vì HTTP cho phép nhiều `Set-Cookie` cùng lúc). Thêm `withHeader()/withHeaders()/withStatus()` (immutable, trả instance mới), `withCookie()` (không đọc Config, caller tự truyền `secure/httponly/samesite`, `httponly` mặc định `true`), `withCache()/noCache()` (chỉ `Cache-Control`, không ETag/Last-Modified/Vary), `getCookies()`. **Có chủ đích KHÔNG** làm `apiSuccess()/apiError()` (business convention, không phải HTTP contract) và **KHÔNG** `download()/file()` (filesystem là chủ đề riêng, để dành module Media). Toàn bộ method cũ (`json/html/redirect/getStatusCode/getBody/getHeaders/send`) giữ nguyên — 100% backward compatible.

### Quy ước "Redirect kèm Flash Message" (CMS-017 — quyết định kiến trúc, không có code mới)

`Response::redirect()` (3.5) + `Session::flash()`/`getFlash()` (3.6) đã đủ để Controller tự dựng pattern "redirect kèm dữ liệu tạm" (validation errors, old input) mà không cần lớp trung gian:
```php
$session->flash('errors', $result->errors());
$session->flash('old', $request->all());
return Response::redirect('/login');
```
**Quyết định có chủ đích KHÔNG tạo `core/Redirector.php` hay bất kỳ class nào cầu nối `Response`↔`Session`** — làm vậy sẽ phá vỡ nguyên tắc "Response/Session độc lập tuyệt đối" đã xuyên suốt từ CMS-001 và vừa tái khẳng định ở CMS-016 (giữ `Response` 0 dependency). Chỉ xem xét lại nếu Module Auth/Form (Phase 3+) thực sự lặp lại boilerplate này ở ≥2 nơi — đúng nguyên tắc "không tạo abstraction chỉ để DRY".

### 3.16. `ExceptionHandler` (`core/ExceptionHandler.php`) — v0.0.19

Tách khỏi `Application` để cải thiện SRP. `final class`, **0 dependency** (không Config/Container/logging/Session/Database/View/Request) — mức cô lập cao nhất cùng `Validator`. Public API duy nhất: `handle(Throwable $exception, bool $debug): Response`. Mapping **tĩnh** (không registry/`extend()` — YAGNI, chỉ 2/23 exception hiện có cần map riêng): `RouteNotFoundException`→404, `MethodNotAllowedException`→405, mọi `Throwable` khác→500 (`debug ? getMessage() : 'Internal Server Error'`). Debug block (`exception`/`file`/`line`/`trace`) chỉ xuất hiện khi `status===500 && debug===true` — `trace` dùng `explode("\n", getTraceAsString())`, không dùng `getTrace()` (tránh lỗi serialize JSON với object/resource không encode được). Không logging — `Application` tiếp tục sở hữu `logException()`, quyết định gọi hay không dựa trên status code trả về từ `ExceptionHandler`. **Phát hiện quan trọng khi thiết kế**: `Router::match()` ném `RouteNotFoundException`/`MethodNotAllowedException` TRƯỚC KHI `MiddlewarePipeline` (CMS-006/CMS-018) được gọi — vì vậy Exception Handler KHÔNG thể triển khai dưới dạng Middleware (dù CMS-018 vừa có Global Middleware), phải nằm ở `Application::handle()`.

### 3.17. `Csrf` + `CsrfMiddleware` (`core/Csrf.php` + `core/Middleware/CsrfMiddleware.php`) — v0.0.20

**`Csrf`** — thuần quản lý vòng đời token, không biết HTTP. `token(): string` get-or-generate qua `Session` (namespace `csrf.token`, đã dự trù từ CMS-007), sinh bằng `bin2hex(random_bytes(32))` (256-bit). `verify(string $submitted): bool` so khớp timing-safe bằng `hash_equals()`. Không tự `Session::start()`, không throw exception mới. Token sống theo Session, không regenerate mỗi request.

**`CsrfMiddleware`** — implement `MiddlewareInterface` (CMS-006, không đổi contract). Safe methods (`GET/HEAD/OPTIONS`) bỏ qua. Unsafe methods (`POST/PUT/PATCH/DELETE`) đọc token theo thứ tự `_token` (input) → `X-CSRF-TOKEN` (header) → `X-XSRF-TOKEN` (header), guard `is_string()` trước khi verify (không ép kiểu, tránh Warning nếu client gửi mảng), fail trả `Response::json({success:false,data:null,message:"CSRF token mismatch.",errors:[]}, 419)` — không dùng `CsrfException`, không mở rộng `ExceptionHandler`.

**Dependency Graph**: `CsrfMiddleware → Csrf → Session` (tuyến tính, không nhánh phụ, không circular). **0 file Core cũ nào bị sửa** — đã xác nhận qua đọc trực tiếp `Container.php`: `Container::resolve()` tự fallback `class_exists($id) → autoWire()` khi không có binding tường minh, nên `Csrf`/`CsrfMiddleware` auto-wire được mà không cần đăng ký trong `Application::registerCoreServices()`. Hệ quả: 2 class này **không phải singleton** trong Container (tạo instance mới mỗi lần resolve) — vô hại vì cả 2 hoàn toàn stateless, `Session` (dependency thật giữ state) vẫn đúng singleton như đã đăng ký.

**Middleware Flow**: `Request → Router::match() → MiddlewarePipeline (Global→Group→Route) → CsrfMiddleware::process() → [safe: next() ngay | unsafe: verify token, fail=419 short-circuit hoặc pass=next()] → Controller → Response`. **CSRF hoàn toàn opt-in** — CMS-020 không tự gắn `CsrfMiddleware::class` vào bất kỳ route/group nào; việc này thuộc phạm vi Module/App khi khai báo route Admin thật (không áp dụng cho `/api/*`, đúng định hướng gốc `cms-architecture-proposal.md`: Session cho Admin Panel dùng CSRF, JWT cho `/api/*` không cần).

**Security Design**: Synchronizer Token Pattern chuẩn (không Cookie CSRF/Double-Submit). Entropy 256-bit CSPRNG (`random_bytes`), so sánh timing-safe (`hash_equals`), safe methods loại trừ đúng OWASP Cheat Sheet, thiếu token = fail tuyệt đối (không bypass). Ghi nhận cho CMS-021: `Session::regenerate()` không tự cascade đổi `csrf.token` — rotate token khi đăng nhập (nếu cần) dùng `Session::remove('csrf.token')` + `Csrf::token()` (API sẵn có).

### 3.18. `Auth` + `AuthMiddleware` (`core/Auth.php` + `core/Middleware/AuthMiddleware.php`) — v0.0.21

Quản lý trạng thái "đã đăng nhập hay chưa" trong Session (`auth.user_id`/`auth.user`) — **không verify credential, không Database, không password**. `login(int|string $userId, array $user = []): void`: `regenerate()` → `remove('csrf.token')` → `set('auth.user_id')` → `set('auth.user')`. `logout(): void`: `Session::destroy()`. `check()/id()/user()` đọc lại từ Session. `AuthMiddleware` chặn request chưa đăng nhập → 401 JSON, không redirect/Config. Module Auth đầy đủ (verify password, JWT, password reset — theo `02-module-auth.md`) là phạm vi Phase 3+ riêng, gọi `Auth::login()` SAU KHI tự xác thực xong.

**Xác nhận sau CMS-031**: `Auth.php` **không sửa 1 dòng nào**, tiếp tục **không chứa password logic** — việc verify password thật thuộc `AuthenticationService` (mục 3.26), gọi `Auth::login()` đúng như thiết kế gốc "SAU KHI tự xác thực xong".

### 3.19. `Authorization` + `AuthorizationMiddleware` (`core/Authorization.php` + `core/Middleware/AuthorizationMiddleware.php`) — v0.0.22

Đọc `roles`/`permissions` từ Session (`auth.roles`/`auth.permissions`), thuần đọc, 0 ghi, 0 DB, 0 dependency vào `Auth`. `roles()/permissions(): list<string>` (default `[]`). `hasRole()/hasAnyRole()/hasAllRoles()/hasPermission()/hasAnyPermission()/hasAllPermissions()`, `can()` = alias thuần `hasPermission()`. `AuthorizationMiddleware` là gate chung (403 nếu `roles()===[] && permissions()===[]`) — **không tham số hoá per-route** (xem Technical Debt #16 — giới hạn kiến trúc Middleware hiện tại), kiểm tra quyền cụ thể per-route là trách nhiệm Controller tự gọi `hasRole()/can()` trực tiếp.

**Xác nhận sau CMS-031**: `Authorization.php` **không sửa 1 dòng nào**, tiếp tục **chỉ đọc Session** — `AuthenticationService` (mục 3.26) ghi `auth.roles`/`auth.permissions` qua `Session::set()` công khai đã có sẵn, không cần API mới ở `Authorization.php`.

### 3.20. `RateLimiter` + `RateLimitMiddleware` (`core/RateLimiter.php` + `core/Middleware/RateLimitMiddleware.php`) — v0.0.23

Đếm "hit" theo key trong 1 cửa sổ decay, lưu Session (`rate_limit.{key}` = `{attempts:int, expires_at:int}`, chỉ integer timestamp). API: `hit()/tooManyAttempts()/attempts()/remaining()/clear()/availableIn()`. **`RateLimitMiddleware` là placeholder có chủ đích** — chỉ `implements MiddlewareInterface` và `return $next($request);`, không tự xác định key/limit, không gọi `hit()` (cùng giới hạn kiến trúc như `AuthorizationMiddleware` — xem Technical Debt #16). Logic rate-limit thật do Module tương lai tự gọi `RateLimiter` trực tiếp (biết đủ business context để xác định bucket, VD `hit('login:'.$ip, 5, 60)`).

### 3.21. `Logger` (`core/Logger.php`) — v0.0.24

**Responsibility**: Ghi 1 dòng có cấu trúc vào 1 file log cố định. Không bắt exception, không quyết định policy, không biết HTTP/Database/Hook/ExceptionHandler/Config.

**Public API**: `__construct(string $logPath)`, `log(string $level, string $message, array $context = []): void`.

**Dependency Graph**: `Logger` — lá trong dependency graph, 0 dependency vào Core Component nào khác. Không PSR-3 (không thêm Composer package), không level filtering/channel/formatter/handler/rotation/async/buffering.

**Lifecycle**: Stateless theo mỗi lời gọi `log()`, không singleton bắt buộc, không đăng ký `Container`/`Application` (Foundation thuần, chưa nối dây vào luồng chính).

**Security Notes**: Không tự lọc dữ liệu nhạy cảm trong `$context` (trách nhiệm caller). Ghi qua `@file_put_contents(..., FILE_APPEND|LOCK_EX)`, tự `mkdir()` thư mục cha nếu thiếu — không throw khi ghi thất bại (nhất quán `Application::logException()`).

**Testing**: `tests/Core/LoggerTest.php` (8 test, filesystem thật/temp dir).

**Quan hệ với `Application::logException()` và 2 điểm mở đã dự trù từ trước**: `Database::onQueryExecuted()` (CMS-004) và `Hook::onError()` (CMS-009) đều có docblock chờ sẵn "Logger thật sẽ gọi hàm này ở task sau" — `Logger` (CMS-024) **chưa** được nối vào 2 điểm này, cũng chưa thay thế `Application::logException()` — đây là Foundation Component độc lập, việc tích hợp để dành 1 CMS sau (xem Technical Debt #17).

### 3.22. `TenantManager` (`core/TenantManager.php`) — v0.0.25

**Responsibility**: Giữ state "tenant hiện tại" trong phạm vi 1 request. Không resolve domain→tenant (bảng `sites`/`site_domains` chưa tồn tại — chưa có migration thật), không Database, không Session, không Request.

**Public API**: `setCurrent(int|string $tenantId, array $data = []): void`, `check(): bool`, `id(): int|string|null`, `current(): ?array`.

**Dependency Graph**: `TenantManager` — **0 dependency**, không constructor. Lá tuyệt đối, mức cô lập cao nhất trong toàn bộ Core (thấp hơn cả `Logger`).

**Lifecycle**: State per-instance, tự cô lập theo từng request vì `Container` đã là 1-per-request — không cần cơ chế dọn dẹp nào (khác `Session`).

**Quyết định khác biệt có chủ đích**: KHÔNG dùng Session làm nơi lưu (dù `Session.php` đã dự trù `tenant.current` từ CMS-007) — vì tenant cần xác định cho MỌI request kể cả API/JWT không cookie, ép `Session::start()` chỉ để biết site nào sẽ vi phạm triết lý lazy-start của `Session`.

**Quan hệ với các điểm mở đã dự trù trước đó**: `View` (docblock CMS-005: `$activeTheme` "tương lai: TenantManager"), `config/app.php` (comment CMS-011: chờ "TenantManager thật Phase 2+"), `Cache`/`QueryBuilder::forTenant()` (chờ nguồn cung cấp `{tenantId}`) — **chưa được nối dây** trong CMS-025, để dành 1 CMS sau khi có migration `sites`/`site_domains` thật (xem Technical Debt #18).

**Integration thật (CMS-030, `v0.0.30`)** — ~~Technical Debt #18~~ **✅ đã giải quyết phần domain resolution + View**: `TenantManager` giờ đăng ký **singleton** trong `Application::registerCoreServices()` (bắt buộc — không có binding thì `Container::get()` không cache, mỗi lần gọi tạo instance mới, tích hợp sẽ không hoạt động nếu thiếu bước này). `core/Middleware/TenantResolverMiddleware.php` (mới) gọi `setCurrent()` sau khi resolve domain→site qua `sites JOIN site_domains`; `View` Closure factory đọc `TenantManager::current()['theme_active']`. `TenantManager.php` **bản thân không đổi 1 dòng** — vẫn đúng vai trò thuần state holder. Chi tiết đầy đủ ở mục 3.24. `Cache`/`QueryBuilder::forTenant()` **vẫn chưa nối dây** (ngoài phạm vi CMS-030).

### 3.23. `ThemeManager` (`core/ThemeManager.php` + `core/Theme/*`) — v0.0.26

**Responsibility**: discover installed theme (glob `{themesPath}/*/theme.json`), parse metadata, tra cứu 1 theme theo key. Không render (thuộc `View`), không biết theme nào active (business state), không Database.

**Public API**: `ThemeManager::discover(): array<string, ThemeDescriptor>`, `find(string $key): ?ThemeDescriptor`. `ThemeDescriptor`: `key/name/version/screenshot/path` (readonly).

**Dependency Graph**: `ThemeManager` — **0 dependency Core** (chỉ 1 string `$themesPath`), giống `ModuleManager`/`PluginManager`.

**Lifecycle**: **Không memoize** — mỗi `discover()` đọc lại filesystem độc lập (khác `PluginManager`, giống `ModuleManager`) — filesystem luôn là source of truth, theme có thể cài/gỡ giữa các lần gọi. Không đăng ký `Container`/`Application` (Foundation trước).

**Architecture boundary** (không đổi ranh giới đã có): `ThemeManager` — biết theme nào tồn tại + metadata. `View` — render template dùng theme đã biết trước (không đổi, vẫn nhận `$activeTheme` qua constructor). 2 Component không phụ thuộc lẫn nhau, chỉ gặp nhau ở tầng gọi bên ngoài (Application/Module tương lai).

**Quan hệ với `database-design.md`**: bảng `themes` (nếu có migration sau này) chỉ là bản ĐỒNG BỘ từ filesystem, không phải nguồn gốc — `ThemeManager` không dùng Database, kể cả trong tương lai (khác `Auth`/`TenantManager`, vốn chỉ tạm thời thiếu Database do chưa có migration).

### 3.24. Middleware Parameterization (`core/Middleware/MiddlewarePipeline.php`, mở rộng) — v0.0.27

**Responsibility**: giải quyết Technical Debt #16 — `MiddlewarePipeline` giờ nhận diện được 2 dạng middleware entry trong cùng 1 danh sách: class-string (resolve qua `Container`, hành vi gốc từ CMS-006) và `MiddlewareInterface` instance đã cấu hình sẵn (dùng trực tiếp, không qua `Container`). Không mang business/security logic — chỉ là cơ chế resolve.

**Internal Logic**: thêm `private resolve(mixed $middlewareEntry): MiddlewareInterface` bên trong `MiddlewarePipeline` — `is_string()` → `Container::get()` (như cũ); `instanceof MiddlewareInterface` → trả thẳng; còn lại → `throw new \InvalidArgumentException` kèm `get_debug_type($middlewareEntry)` trong message. `handle()` gọi `resolve()` thay vì `Container::get()` trực tiếp; tham số closure trong `array_reduce` đổi từ `string` sang `mixed` có chủ đích — để lỗi kiểu dữ liệu sai rơi vào `\InvalidArgumentException` rõ ràng của `resolve()`, không bị PHP tự ném `TypeError` mù mờ trước.

**Public API**: không đổi chữ ký (`handle(Request, array, Closure): Response` giữ nguyên) — chỉ đổi PHPDoc kiểu phần tử mảng `$middleware` thành `list<class-string<MiddlewareInterface>|MiddlewareInterface>`, áp dụng đồng bộ ở `MiddlewarePipeline`/`Route`/`Router`.

**Dependency Graph**: không đổi — `MiddlewarePipeline → ContainerInterface` (PSR-11), không thêm dependency mới, không tạo abstraction mới (`MiddlewareResolver`/`MiddlewareFactory`/`MiddlewareDefinition`/`ParameterBag` đều bị từ chối — `resolve()` chỉ là private method nội bộ).

**Backward Compatibility**: tuyệt đối — mọi middleware hiện có (`CsrfMiddleware`/`AuthMiddleware`/`AuthorizationMiddleware`/`RateLimitMiddleware` và toàn bộ fixture test) đăng ký bằng class-string, đi đúng nhánh `is_string()` cũ, không có lệnh gọi nào đổi kết quả. `Container.php` không sửa.

**Phạm vi bị loại trừ có chủ đích (Owner Decision)**: KHÔNG redesign `AuthorizationMiddleware`/`RateLimitMiddleware`, KHÔNG thêm permission parameter hay rate limit configuration, KHÔNG đổi security policy — đây thuần là hạ tầng cho `MiddlewarePipeline`, việc dùng khả năng "truyền instance" để tham số hoá 2 middleware đó là quyết định của 1 CMS riêng sau này (Architecture Analysis riêng, business/security context riêng).

### 3.25. `TenantResolverMiddleware` (`core/Middleware/TenantResolverMiddleware.php`) — v0.0.30

**Responsibility**: Host → domain lookup → tenant resolve → `TenantManager::setCurrent()`. Không Auth, không Permission, không site status policy, không Super Admin domain.

**Internal Logic**: `process()` gọi `Database::selectOne('SELECT sites.* FROM sites INNER JOIN site_domains ON site_domains.site_id = sites.id WHERE site_domains.domain = ?', [$request->getHost()])` — 1 câu SQL, không qua `QueryBuilder::join()` (giới hạn `table.column` đã ghi nhận ở Technical Debt #3). Không khớp → trả `Response::json(['success'=>false,'data'=>null,'message'=>'Not Found','errors'=>[]], 404)`, **không gọi `$next()`** (fail-closed, không fallback tenant mặc định). Khớp → `TenantManager::setCurrent((int) $site['id'], $site)` → `$next($request)`.

**Public API**: `process(Request, Closure): Response`, implement `MiddlewareInterface` (không đổi contract, đúng pattern `AuthMiddleware`/`CsrfMiddleware`).

**Dependency Graph**: `TenantResolverMiddleware → Database, TenantManager` (constructor injection, cả 2 đều resolve qua Container — `Database` đã singleton từ CMS-004, `TenantManager` singleton từ CMS-030, xem mục 3.22).

**Đăng ký (`Application::boot()`, CMS-030)**: **không** dùng Global Middleware (`Router::middleware()`) — đã xác nhận `Router::dispatch()` gộp Global Middleware vô điều kiện cho MỌI route, không có cơ chế exclude per-route. Thay vào đó, `boot()` bọc `ModuleManager::boot($router, ...)` trong `$router->group(['middleware' => [TenantResolverMiddleware::class]], function (Router $router) use ($moduleManager) { $moduleManager->boot($router, ...); })` — chỉ route do Module đăng ký mới đi qua Middleware này; `/health` (đăng ký trực tiếp trên `$router`, ngoài group) không bị ảnh hưởng. Dùng nguyên `Router::group()` đã có từ CMS-006/018 — không sửa `Router.php`/`Route.php`/`MiddlewarePipeline.php`.

**Vị trí resolve — Middleware, không phải `Application::boot()`**: `boot()` idempotent (chạy đúng 1 lần/instance, guard `$booted`) và không nhận `Request` — nhét domain resolution vào `boot()` sẽ phá tính idempotent nếu `handle()` được gọi nhiều lần trên cùng 1 `Application` instance (`testBootIsIdempotentAcrossMultipleHandleCalls`).

**Security**: `Request::getHost()` đọc `$_SERVER['HTTP_HOST']` trực tiếp — client-controllable, **chỉ dùng làm giá trị lookup qua prepared statement** (`WHERE site_domains.domain = ?`), không tin cậy cho mục đích khác. Fail-closed tuyệt đối — không domain nào được chấp nhận nếu không khớp chính xác 1 dòng `site_domains`, không có "tenant mặc định" nào trong bất kỳ trường hợp nào.

**Test**: `tests/Core/Middleware/TenantResolverMiddlewareTest.php` (4 test, `Database` SQLite in-memory thật + seed tay, không mock).

**Không xử lý (Owner Decision, để dành CMS riêng)**: `system_admin.domains` bypass (đã có sẵn trong `config/tenants.php`, chưa dùng), domain normalization (lowercase/strip port) — xem Technical Debt #22/#23.

**Site Status Policy (CMS-036, `v0.0.36`)** — giải quyết Technical Debt #21. Sau khi resolve domain→site thành công, thêm 1 khối kiểm tra: `if ($site['status'] !== 'active') { return $this->statusBlockedResponse(...); }` — dùng thẳng `$site['status']` đã có sẵn từ `SELECT sites.*`, **không thêm query SQL**. **Fail-closed tuyệt đối** — chỉ đúng chuỗi `'active'` mới được đi tiếp, không `in_array()`/whitelist, mọi giá trị khác (kể cả NULL/rỗng/giá trị lạ/tương lai) đều bị chặn. `private statusBlockedResponse(string $status): Response` dùng `match()` chọn mã HTTP/message: `'maintenance'` → 503 + `"Site is under maintenance."`; `'suspended'` → 403 + `"Site has been suspended."`; `default` (mọi giá trị khác) → 403 + `"Site is not available."`. Khi bị chặn: **không** gọi `TenantManager::setCurrent()`, **không** gọi `$next($request)` — response trả ngay (short-circuit, cùng pattern nhánh 404 domain-không-khớp). Response giữ nguyên envelope `{success, data, message, errors}`.

**Đánh đổi có chủ đích**: message tiết lộ lý do cụ thể (khác nhánh domain-không-khớp vẫn giữ 404 generic) — ưu tiên khả năng vận hành/chẩn đoán hơn che giấu sự tồn tại của domain, đây là quyết định tường minh của Owner (không phải sơ suất).

### 3.26. `AuthenticationService` (`core/AuthenticationService.php`) — v0.0.31

**Responsibility**: Verify email/password thật từ `users`, gọi `Auth::login()` (không đổi API), nạp `roles`/`permissions` thật vào Session theo site hiện tại. Không route, không Controller, không rate limit, không JWT, không Repository.

**Public API**: `attempt(string $email, string $password): bool` — method public duy nhất. Trả `bool` cho mọi lỗi xác thực (email không tồn tại/password sai/status không active — hội tụ về đúng 1 điểm `return false`, không phân biệt lý do), chỉ `throw new \LogicException` (built-in, không tạo class mới) khi `TenantManager::check() === false` (lỗi tiền điều kiện của caller, không phải lỗi user).

**Dependency Graph**: `AuthenticationService → Database, Auth, Session, TenantManager` (constructor injection, cả 4 đã resolve được qua Container hiện có — `Database`/`Session`/`TenantManager` singleton, `Auth` auto-wire qua `Session`). **Không đăng ký tường minh trong `Application::registerCoreServices()`** — service không giữ state, auto-wire đủ dùng; CMS-031 **không chạm `core/Application.php`** (khác 3 CMS liên tiếp trước CMS-028/029/030).

**Security Design**:
- **Chống user enumeration**: khi email không tồn tại, dùng `DUMMY_HASH` (hằng số bcrypt cố định, không tương ứng password thật nào) để `password_verify()` vẫn thực thi đúng 1 lần CPU work — tránh phản hồi nhanh bất thường tiết lộ "email này không tồn tại".
- **`status` check đặt SAU `password_verify()`, không phải trước** — nếu check trước, kẻ tấn công phân biệt được tài khoản `locked`/`pending` qua tốc độ phản hồi (bỏ qua bước bcrypt tốn thời gian). Đặt sau đảm bảo mọi request tốn đúng 1 lần bcrypt verify.
- Query login chỉ `SELECT id, password, status` (không `SELECT *`), `Auth::login()` chỉ nhận `id`/`email` (không `password`) — không log/lưu/trả password hash ở bất kỳ đâu.

**Database Query Design**: Login — 1 câu `SELECT id, password, status FROM users WHERE email = ?`. Permission loading — 2 câu raw SQL riêng (không qua `QueryBuilder::join()`, né Technical Debt #3): roles qua `user_site_roles JOIN roles`, permissions qua `user_site_roles JOIN roles JOIN role_permissions JOIN permissions` — cả 2 lọc theo `user_id` + `site_id` (`TenantManager::id()`).

**Session Data**: `auth.roles`/`auth.permissions` lưu `list<string>` (tên role/key permission, không lưu ID) — khớp đúng kiểu dữ liệu `Authorization::hasRole()/hasPermission()` đã có từ CMS-022, không cần sửa `Authorization.php`.

**Không xử lý (Owner Decision, để dành CMS riêng)**: `POST /login`/Controller/UI, JWT (`config('auth.jwt')` — dành cho `/api/v1/*` theo thiết kế Hybrid Auth gốc), register/forgot-password/user-management/permission-management UI, multi-site session (1 session = 1 site, đổi site cần login lại) — xem Technical Debt #25/#26.

**Rate Limiting (CMS-033, `v0.0.33`)** — mở rộng `AuthenticationService`, giải quyết Technical Debt #24. Constructor thêm `RateLimiter`+`Config` (6 dependency). `attempt()` gọi `tooManyAttempts(key, maxAttempts)` **ngay sau tenant-check, trước khi query DB** (chặn sớm, tránh tốn tài nguyên khi đã bị rate-limit) — key `login:{lowercase_email}` (`config('auth.login_throttle.max_attempts'/'decay_seconds')`, đã sẵn sàng từ CMS-023, lần đầu dùng thật). Sau `password_verify()`: **FAIL** → `hit(key, maxAttempts, decaySeconds)`; **SUCCESS** → `clear(key)` — gắn với kết quả `password_verify()`, không gắn với kết quả cuối `attempt()`.

**Authentication Flow (cập nhật đầy đủ sau CMS-033)**:
```
Tenant Check (TenantManager::check(), throw LogicException neu false)
    v
Rate Limit (tooManyAttempts() -> true: return false, khong query DB)
    v
User Lookup (SELECT id, password, status FROM users WHERE email = ?)
    v
Password Verify (that hoac DUMMY_HASH neu user khong ton tai)
    v
hit() [FAIL] hoac clear() [SUCCESS]
    v
Status Check (status !== 'active' -> return false)
    v
Auth::login() + load roles/permissions -> Session::set()
```

**Lưu ý bảo mật đã ghi nhận có chủ đích (không phải bug)**: tài khoản `inactive` dùng đúng password vẫn kích hoạt `clear()` (vì `clear()` gắn với `password_verify()` thành công, xảy ra **trước** `status` check) — rate limiter không bao giờ chặn được kịch bản "biết đúng password nhưng tài khoản bị khoá", dù không dẫn tới truy cập trái phép (status check vẫn chặn ở bước sau). Hành vi này được Owner xác nhận qua Final Verification (Phase 4) và khoá lại bằng `testRateLimitClearsEvenWhenInactiveAccountUsesCorrectPassword` — tránh refactor sau này vô tình đổi thứ tự `clear()`/status check.

### 3.27. Module đầu tiên — `Auth Module` (`modules/Auth/`) — v0.0.34

**Lưu ý**: đây là **Module** (business layer), không phải Core Component — đặt tiếp số thứ tự mục 3 để dễ tham chiếu, nhưng khác bản chất hoàn toàn với 3.1-3.26 (những mục đó đều là `core/*.php`).

**Module Layout**:
```
modules/
  Auth/
    module.json          {"key":"auth","name":"Auth Module","version":"1.0.0","dependencies":[]}
    routes.php            $router->post('/login', [Modules\Auth\LoginController::class, 'handle']);
    LoginController.php   namespace Modules\Auth; class LoginController
```
Namespace `Modules\Auth` khớp PSR-4 `"Modules\\": "modules/"` (`composer.json`, cấu hình từ CMS-001, **lần đầu dùng thật** ở CMS-034 — `modules/` trước đó hoàn toàn rỗng, chỉ `.gitkeep`).

**Module Bootstrap Flow**: `Application::boot()` (không sửa) → `$moduleManager->boot($router, \array_keys($moduleManager->discover()))` bên trong `$router->group(['middleware' => [TenantResolverMiddleware::class]], ...)` (đã có từ CMS-030) → `ModuleManager::discover()` glob `modules/*/module.json` → tự động tìm thấy `Auth` (không cần đăng ký thủ công, không có bảng `site_modules` để lọc — mọi module hợp lệ tự "bật") → `boot()` `require routes.php` qua closure cô lập scope (chỉ `$router` khả kiến).

**Controller Lifecycle**: `Router::dispatch()` → match route → `MiddlewarePipeline` (gồm `TenantResolverMiddleware` do route nằm trong group) → `ControllerResolver::resolve()` — `$controller = $container->get(Modules\Auth\LoginController::class)` (auto-wire qua Reflection, **không đăng ký gì trong `Application::registerCoreServices()`**) → `$controller->handle($request)`.

**HTTP Flow / Login Flow**:
```
POST /login {email, password}
    v
TenantResolverMiddleware (domain khong khop -> 404, dung lai truoc Controller)
    v
LoginController::handle(Request)
    v
Validator::validate(['email'=>'required|email','password'=>'required|string'])
    v
FAIL -> 422 {success:false, data:null, message:'Du lieu khong hop le.', errors:{field:[...]}}
    v
PASS -> AuthenticationService::attempt($email, $password)
    v
false -> 401 {success:false, data:null, message:'Email hoac mat khau khong dung.', errors:[]}
    v
true  -> 200 {success:true, data:{id, email, roles, permissions}, message:'', errors:[]}
```
Message 401 **thống nhất cho MỌI nhánh thất bại** của `attempt()` (sai password/email không tồn tại/rate-limited/status không active) — `LoginController` không tự suy luận lý do cụ thể, đúng nguyên tắc chống user enumeration đã có từ `AuthenticationService` (CMS-031).

**Response Format**: Đúng envelope `{success, data, message, errors}` đã dùng nhất quán ở mọi endpoint khác (`/health`, 404/405/500, CSRF 419, Auth 401, Authorization 403) — không có `apiSuccess()/apiError()` (đã từ chối ở CMS-016), `LoginController` tự build mảng. `data` thành công đọc qua `Auth::id()/user()['email']` + `Authorization::roles()/permissions()` — **không** API mới ở `Auth.php`/`Authorization.php`.

**Security Note**:
- **Không CSRF cho `/login`** (Owner Decision, có chủ đích, không phải thiếu sót) — chưa có endpoint nào phát hành token CSRF trước khi client submit (không GET login page, không SPA token endpoint). "Login CSRF" (kẻ tấn công ép nạn nhân đăng nhập vào tài khoản kẻ tấn công) là lớp rủi ro khác mà Synchronizer Token Pattern không trực tiếp nhắm tới — chấp nhận được ở giai đoạn Foundation này, ghi nhận Technical Debt để xem xét khi có Admin Panel UI thật.
- **Không GET `/login`** — chưa có theme/View Admin Panel, JSON API POST-only.
- Không log/trả password hoặc hash ở bất kỳ đâu trong response.
- Rate limiting (CMS-033) và chống user enumeration (CMS-031) tự động áp dụng — Module không cần biết/xử lý gì thêm.

**Testing**: `tests/Core/ModuleAuthIntegrationTest.php` (5 test) — dùng `ModuleManager` trỏ thẳng `modules/` **thật** (không fixture), `Router::dispatch()` thật, không qua `Application::bootstrap()` (tránh phụ thuộc config production/ghi log thật vào `storage/`).

**Không sửa**: `Application.php`, `ModuleManager.php`, `Router.php`, `Container.php`, `AuthenticationService.php`, `Auth.php`, `Authorization.php`, mọi Middleware, Migration, Database, Composer, PHPUnit config.

### Auth Logout Flow (CMS-035, `v0.0.35`)

`modules/Auth/LogoutController.php` (mới) — `POST /logout`, dùng nguyên `Auth::logout()` đã có từ CMS-021, không sửa `Auth.php`/`Session.php`.

```
POST /logout
    v
TenantResolverMiddleware (group, khong doi tu CMS-030)
    v
LogoutController::handle(Request)
    v
Auth::logout()  ->  Session::destroy()
    v
200 JSON {success, data, message, errors}
```

**Response format**: `{success: true, data: null, message: "Dang xuat thanh cong.", errors: []}` — luôn luôn, không có nhánh lỗi khác (idempotent).

**Security Notes**:
- **Idempotent** — trả 200 dù đã đăng nhập hay chưa, đúng bản chất `Auth::logout()`/`Session::destroy()` (không throw ở bất kỳ trạng thái nào, đã xác nhận qua đọc trực tiếp `Session.php`).
- **Không leak state** — response không phân biệt "đã đăng nhập" hay "chưa đăng nhập" trước khi gọi.
- **Không cần `AuthMiddleware`** — dù middleware này đã tồn tại sẵn (CMS-021), áp dụng vào `/logout` sẽ ép 401 khi chưa đăng nhập, mâu thuẫn với tính idempotent đã chọn.
- **Không cần CSRF** (Owner Decision, nhất quán `/login` CMS-034) — chưa có token-issuing flow; đồng thời `Session::destroy()` tự xoá luôn mọi `rate_limit.*`/`csrf.token` như hệ quả tự nhiên, không cần code dọn riêng.

**Test Failure Analysis (ghi nhận lịch sử)**: lần chạy đầu 2 test mới ném `SessionException` vì gọi `Auth::check()/user()` ngay sau `Session::destroy()` (session đã kết thúc) trong cùng 1 "request" mô phỏng — **lỗi test assumption**, không phải bug `LogoutController`/`Auth`/`Session`. Sửa bằng cách gọi lại `Session::start()` trước khi đọc trạng thái (mô phỏng đúng request kế tiếp), **đúng pattern đã có từ `AuthTest::testLogoutClearsAuthenticationState` (CMS-021)** — không phải lỗi mới, là sự lặp lại bài học cũ.

**Testing**: `tests/Core/ModuleAuthIntegrationTest.php` (+2 test, tổng 7 test trong file).

**Không sửa**: `LoginController.php`, `Auth.php`, `Session.php`, `AuthenticationService.php`, `Authorization.php`, `Router.php`, `Application.php`, `ModuleManager.php`, `Container.php`, mọi Middleware, Migration, Composer, PHPUnit config.

### 3.28. CMS Foundation Completion — Bootstrap + Role Module + Dashboard Module — v0.0.38

**Bootstrap Flow (`bin/bootstrap.php`)**: CLI độc lập (không HTTP route, không import từ `modules/`), cùng convention `bin/migrate.php`. **One-time only**: kiểm tra `SELECT COUNT(*) FROM users` — nếu `> 0`, từ chối chạy (không tạo Admin trùng). Toàn bộ 7 bước nằm trong **1 `Database::transaction()`** (đã có từ CMS-004, không sửa): `INSERT sites` → `INSERT site_domains` → `INSERT users` (`password_hash($password, PASSWORD_DEFAULT)`) → `INSERT roles` (`tenant_id = NULL`, tên "Admin" — System Role) → `INSERT permissions` × 11 → `INSERT role_permissions` × 11 (gán toàn bộ cho role Admin) → `INSERT user_site_roles`. Không migration mới — dùng nguyên 7 bảng CMS-028.

**11 permission bootstrap cố định**: `user.view/create/update/lock/assign_role`, `role.view/create/update/delete/assign_permission`, `dashboard.view`. Không tạo permission cho Content/Media (chưa tồn tại Module tương ứng).

**Role Model — ý nghĩa `roles.tenant_id`** (xác nhận qua đọc trực tiếp migration CMS-028 — **không có cột `is_system`**, khác đề xuất gốc `database-design.md`; ý nghĩa suy ra từ 2 nguồn khớp nhau: comment `database-design.md` gốc + cách `CreateUserController` CMS-037 đã dùng câu query `WHERE tenant_id IS NULL OR tenant_id = ?`):
- **`tenant_id IS NULL` = System Role** — dùng chung mọi tenant. Qua `Role` Module: **View allowed**, `Update`/`Delete`/`Permission modification` đều **403 Forbidden** (không phải 404 — role hệ thống được phép nhìn thấy công khai, khác nguyên tắc "ẩn dữ liệu tenant khác").
- **`tenant_id = site` = Tenant Role** — CRUD đầy đủ qua Module, scoped `TenantManager::id()`, cross-tenant → **404** (giữ nguyên nguyên tắc đã dùng ở `User` Module CMS-037).

**`modules/Role/`** (6 Controller, đúng Controller Contract 1 `handle()`, `Database` trực tiếp không Repository): `ListRolesController` (`GET /roles`, `can('role.view')`, trả cả System + Tenant role), `CreateRoleController` (`POST /roles`, luôn gán `tenant_id` hiện tại — không input nào tạo được System Role qua Module, 422 nếu trùng tên UNIQUE), `EditRoleController`/`DeleteRoleController`/`AssignPermissionController` (`PATCH`/`DELETE /roles/{id}`, `POST /roles/{id}/permissions` — cùng logic 3 nhánh: role không tồn tại → 404; System Role → 403; Tenant Role tenant khác → 404; Tenant Role đúng tenant → thực hiện), `ListPermissionsController` (`GET /permissions`, danh mục toàn hệ thống không tenant-scoped, dùng chung `can('role.view')`).

**`DeleteRoleController` — application-level check thay vì FK exception**: `SELECT COUNT(*) FROM user_site_roles WHERE role_id = ?` tường minh trước `DELETE` → 409 nếu `> 0`. Lý do kỹ thuật: SQLite trong `Database::connect()` **không bật `PRAGMA foreign_keys = ON`** mặc định (giới hạn đã ghi nhận từ CMS-030) — không thể dựa vào FK RESTRICT của DB để bắt lỗi qua `QueryException` như dự tính ban đầu, đã tự phát hiện qua PHPUnit thật và sửa đúng root cause.

**`modules/Dashboard/`** (`DashboardController`, `GET /dashboard`, `can('dashboard.view')`): trả `{user_count, role_count}` scoped `TenantManager::id()` — `user_count` JOIN `user_site_roles`, `role_count` đếm cả System + Tenant role visible. Chưa có UI, chỉ JSON foundation.

**Settings Foundation — chỉ Design, KHÔNG Implementation**: cần 1 migration mới `settings(id, tenant_id, key, value JSON, group)` (theo đúng `database-design.md`) khi triển khai thật — duy nhất phần trong CMS Foundation Completion cần migration, chưa thực hiện.

**Testing**: `tests/Core/ModuleRoleIntegrationTest.php` (12 test), `tests/Core/ModuleDashboardIntegrationTest.php` (2 test) — cùng pattern `ModuleAuthIntegrationTest`/`ModuleUserIntegrationTest` (`ModuleManager` trỏ `modules/` thật, không fixture, permission seed trực tiếp trong test).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, mọi migration, `composer.json`, `phpunit.xml`. `bin/bootstrap.php` không có PHPUnit test trực tiếp (nhất quán tiền lệ `bin/migrate.php`).

### 3.29. Content Foundation — `pages` + `modules/Page/` — v0.0.40

**`pages` — Content Schema thật đầu tiên của dự án** (`database/migrations/2026_08_02_000001_create_pages_table.php`): `tenant_id, parent_id (self, SET NULL), title, slug, content TEXT, template, status VARCHAR(20) DEFAULT 'draft', published_at, is_homepage, created_by (RESTRICT), created_at/updated_at/deleted_at`. UNIQUE `(tenant_id, slug)`, index `(tenant_id, status)`, index `(parent_id)`. Đúng convention driver-aware PK từ CMS-028. **`content` lưu `TEXT`, không cột `JSON`** — Application layer (`Modules\Page\*Controller`) tự `json_encode()`/`json_decode()`, tránh phụ thuộc khả năng JSON column khác nhau giữa SQLite/MySQL.

**Lý do chọn `pages` triển khai đầu tiên trong Content domain** (`database-design.md` mục 3): duy nhất bảng Content **không phụ thuộc `media`** (khác `posts.featured_image_id`/`seo_meta.og_image_id`), tách biệt hoàn toàn khỏi cụm `posts/categories/tags/post_tags/comments` (phụ thuộc lẫn nhau, chưa triển khai).

**`modules/Page/`** (6 Controller, đúng Controller Contract 1 `handle()`, `Database` trực tiếp không Repository/Service):
- `ListPagesController` (`GET /pages`, `page.view`) — scoped `TenantManager::id()`, loại `deleted_at IS NOT NULL`, không trả `content` (giống `ListUsersController` loại `password`).
- `CreatePageController` (`POST /pages`, `page.create`) — 1 câu INSERT (không cần transaction, khác `CreateUserController`), validate `parent_id` thuộc cùng tenant (422 nếu không), 422 nếu trùng `slug` (`QueryException`), `created_by = Auth::id()`.
- `EditPageController`/`DeletePageController` (`PATCH`/`DELETE /pages/{id}`) — 404 cho **cả** cross-tenant **lẫn** page đã xoá mềm (coi như "không tồn tại" với tenant hiện tại).
- `PublishPageController` (`POST /pages/{id}/publish`, `page.publish` — permission tách riêng) — `UPDATE status = ?, published_at = COALESCE(published_at, CURRENT_TIMESTAMP)`, áp dụng đúng pattern `PostService::publish()` (`database-design.md` mục 6.3) sang `pages`, chỉ set `published_at` lần đầu.
- `SetHomepageController` (`POST /pages/{id}/homepage`, dùng `page.update` — **không** `page.set_homepage` riêng) — **duy nhất Controller dùng `Database::transaction()`**: verify tồn tại TRƯỚC (404, ngoài transaction — khác `CreateUserController` vì đây chỉ là pre-condition đơn giản, không có rủi ro orphan-row), rồi 2 UPDATE trong transaction đúng `database-design.md` mục 6.1 (bỏ homepage cũ theo tenant, gán homepage mới).

**Soft delete** (lần đầu dự án dùng thật — khác `users`/`roles`/`sites` đều hard-delete/không-delete): `DELETE /pages/{id}` chỉ `UPDATE deleted_at = CURRENT_TIMESTAMP`, không xoá thật, **không restore/trash trong CMS-040** (Owner Decision, ghi nhận rủi ro chấp nhận được: xoá đúng page đang `is_homepage` không có xử lý tự động, để dành khi có bằng chứng cần).

**Permission Bootstrap mở rộng 11 → 16** (`bin/bootstrap.php`): thêm `page.view/create/update/delete/publish` vào mảng `$permissionKeys` — **xác lập tiền lệ chính thức**: Module tương lai cần permission mới đều mở rộng đúng mảng này trong `bin/bootstrap.php`, không tạo Permission Module/migration seed riêng (đã phân tích ở Architecture Analysis CMS-040: `Role` Module chỉ gán permission đã tồn tại, không có cơ chế tạo permission mới nào khác).

**Testing**: `tests/Core/ModulePageIntegrationTest.php` (17 test) — cùng pattern `Module{Auth,User,Role,Dashboard}IntegrationTest`, `Session::set('auth.user_id', ...)` thêm vào `actingAs()` helper (cần cho `created_by NOT NULL`).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, migration cũ, `composer.json`, `phpunit.xml`. **Có sửa** (khác các Module trước — lần đầu 1 CMS Module vừa tạo migration vừa tạo Module): `bin/bootstrap.php` (mở rộng permission), `tests/Core/RealMigrationsTest.php` (`EXPECTED_ORDER` cập nhật thêm `pages` — phát hiện và sửa sau PHPUnit thật, không phải lỗi migration).

### 3.30. Public Website Rendering — `modules/Public/` + `themes/default/` — v0.0.44

**Mục tiêu**: biến CMS từ Headless API thành website render HTML thật trên trình duyệt, dùng nguyên `View` (CMS-005) + `pages` (CMS-040) đã có — không sửa Core, không migration mới.

**Phát hiện Architecture Analysis quan trọng** (đọc trực tiếp `View::resolvePath()`, glob `themes/`): `themes/` thật trước CMS-044 **rỗng hoàn toàn** (chỉ `.gitkeep`) — nghĩa là `View::render()` production trước đó luôn throw `ViewNotFoundException` nếu được gọi; đường dẫn theme thật có thư mục `views/` bắt buộc ở giữa (`themes/{theme}/views/{dot.path}.php`), khác cấu trúc phẳng đề xuất ban đầu.

**`modules/Public/`** (Module thứ 5, đúng Controller Contract 1 `handle()`, `Database` trực tiếp, **không** `Authorization::can()` — public, không yêu cầu đăng nhập):
- `HomeController` (`GET /`) — `SELECT title, content, template FROM pages WHERE tenant_id = ? AND is_homepage = 1 AND status = 'published' AND deleted_at IS NULL`.
- `PublicPageController` (`GET /{slug}`) — cùng điều kiện, thêm `AND slug = ?`. Cross-tenant/draft/deleted đều trả 404 giống nhau (an danh sự tồn tại, nhất quán `User`/`Role`/`Page` Module).
- Cả 2 Controller có `private render()` giống hệt nhau (chọn template `pages.{template}` → fallback `pages.default` qua `View::exists()` nếu không tồn tại → `json_decode(content)` → `View::render()` → `Response::html()`) — **trùng lặp có chủ đích**, không tạo helper/abstraction chung (ngoài phạm vi Owner đã duyệt cho CMS-044).

**`module.json.dependencies: [auth, user, role, dashboard, page]`** — cơ chế **duy nhất** giải quyết rủi ro `Router::match()` (duyệt tuần tự, khớp đầu tiên thắng): buộc `ModuleManager::resolveLoadOrder()` (topological sort có sẵn từ CMS-010) xếp `Public` load **sau cùng**, đảm bảo route Admin GET 1-segment (`/users`, `/roles`, `/pages`, `/dashboard`...) đăng ký trước route wildcard `GET /{slug}` — không sửa `Router`/`Route`. **Lưu ý hành vi phát hiện khi viết test**: `dependencies` trong `ModuleManager` là ràng buộc **bắt buộc** (`ModuleNotFoundException` nếu dependency không nằm trong `$enabledKeys` lúc `boot()`), không chỉ gợi ý thứ tự — production không ảnh hưởng vì `Application::boot()` luôn enable toàn bộ module `discover()` được.

**`themes/default/`** — Default theme structure đầu tiên của dự án: `theme.json`, `views/layouts/main.php` (`extend`/`yield`), `views/pages/default.php` (`section('content')`, render `title` + `content` dạng text/JSON cơ bản — **không** block builder/component renderer, Owner Decision CMS-044).

**Reserved slug Technical Debt**: page có `slug` trùng route hệ thống (`login`, `users`, `roles`, `pages`, `dashboard`) sẽ không truy cập public được vì route Admin đăng ký trước luôn thắng — chấp nhận rủi ro, không blacklist slug, không sửa `CreatePageController` (Owner Decision).

**Testing**: `tests/Core/PublicPageRenderingTest.php` (8 test) — cùng pattern `Module{Auth,User,Role,Dashboard,Page}IntegrationTest` (`ModuleManager` trỏ `modules/` thật, `Router::dispatch()` thật), nhưng **`View` dùng fixture theme riêng** (`tests/Fixtures/themes/test-theme/`) thay vì `themes/default/` thật (Owner Decision — tránh test phụ thuộc nội dung theme sản phẩm dễ đổi).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Page/*`, `composer.json`, `phpunit.xml`, `database/migrations/*`.

### 3.31. Admin UI Foundation — `modules/Admin/` + `themes/default/views/admin/` — v0.0.45

**Mục tiêu**: CMS lần đầu có giao diện quản trị HTML (trước đó Admin chỉ là JSON API thuần — `Auth`/`User`/`Role`/`Dashboard`/`Page` Module). CMS-045 chỉ dừng ở **Login + Dashboard shell** — không CRUD UI cho Users/Roles/Pages (để dành task riêng sau).

**`modules/Admin/`** (Module thứ 6, `dependencies: [auth]`, đúng Controller Contract 1 `handle()`):
- `ShowLoginController` (`GET /admin/login`) — render form, không `Auth::check()` redirect (giữ đơn giản, ngoài phạm vi CMS-045).
- `LoginController` (`POST /admin/login`) — **tái sử dụng nguyên `AuthenticationService::attempt()`/`Auth::login()`** (không viết lại xác thực/rate-limit). Thành công → `Response::redirect('/admin/dashboard')`. Thất bại (validate fail hoặc `attempt()` false) → **render lại `admin.pages.login` ngay trong cùng response** (không PRG, không `Session::flash()`, không Form Helper — Owner Decision CMS-045, vì Foundation chỉ có 1 form).
- `LogoutController` (`POST /admin/logout`) — `Auth::logout()` rồi `Response::redirect('/admin/login')`. **Không có route logout dùng GET.**
- `DashboardController` (`GET /admin/dashboard`) — tự `Auth::check()`, `false` → `Response::redirect('/admin/login')` (**không dùng `AuthMiddleware`** — class đó trả JSON 401, sai ngữ nghĩa cho luồng HTML). Query `user_count`/`role_count` **copy nguyên SQL** từ `Modules\Dashboard\DashboardController` (JSON, CMS-038) — không gọi lại Controller đó (không có tiền lệ Controller-gọi-Controller trong dự án), không sửa `modules/Dashboard/*`.

**CSRF — lần đầu tiên được gắn vào route thật trong toàn bộ dự án**: `core/Csrf.php`/`core/Middleware/CsrfMiddleware.php` đã tồn tại từ trước (chưa từng dùng — không route nào trước CMS-045 áp dụng). `modules/Admin/routes.php` bọc `POST /admin/login` + `POST /admin/logout` bằng `Router::group(['middleware' => [CsrfMiddleware::class]], ...)` — dùng nguyên class có sẵn, **0 thay đổi Core**. `ShowLoginController`/`LoginController`/`DashboardController` đều truyền `Csrf::token()` vào `View::render()` để nhúng `<input name="_token">`.

**Admin theme — dùng chung `themes/default/`, không tạo theme riêng**: tận dụng cơ chế fallback 2 cấp có sẵn của `View::resolvePath()` (`activeTheme → defaultTheme`, CMS-005) — Admin luôn dùng `activeTheme = defaultTheme = 'default'` (không phụ thuộc `TenantManager`/theme site đang chọn, đúng bản chất "giao diện quản trị CMS phải cố định"). Template: `themes/default/views/admin/layouts/main.php`, `themes/default/views/admin/pages/{login,dashboard}.php` — dot-path `admin.layouts.main`, `admin.pages.login`, `admin.pages.dashboard`.

**Routing**: `/admin/login`, `/admin/dashboard` (≥2 segment) — **miễn nhiễm hoàn toàn** với wildcard `GET /{slug}` (`modules/Public/`, CMS-044) vì `Route::compile()` không khớp dấu `/`. Không đăng ký bare `GET /admin` (Owner Decision) — tránh hoàn toàn rủi ro collision, không cần khai báo `dependencies` chéo với `modules/Public/module.json`.

**Testing**: `tests/Core/AdminUiFoundationTest.php` (7 test) — cùng pattern `Module{Auth,Public}IntegrationTest` (`ModuleManager` trỏ `modules/` thật), nhưng **`View` dùng `themes/default/` thật** (khác CMS-044 — Admin theme là nội dung sản phẩm thật, không phải theme tuỳ biến theo tenant nên không cần fixture riêng).

**Fix sau PHPUnit thật**: `testLogoutClearsSessionAndDashboardRedirectsAgain` ban đầu FAIL (`SessionException`) vì đọc `Session::get()` ngay sau `Auth::logout()` (kết thúc phiên) trong cùng "request" mô phỏng — sửa thêm `Session::start()` trước khi đọc lại, đúng pattern `ModuleAuthIntegrationTest::testLogoutClearsAuthenticatedUser` (CMS-034). Chỉ sửa test, không đụng `core/*`/`modules/*`.

**Technical Debt ghi nhận** (Owner Decision, chưa xử lý): (1) login chưa PRG — refresh sau lỗi resubmit form; (2) `ShowLoginController` không redirect nếu user đã đăng nhập truy cập lại `/admin/login`; (3) `DashboardController` (Admin) trùng lặp logic SQL với `Modules\Dashboard\DashboardController` — chấp nhận, đúng tiền lệ CMS-044.

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Dashboard/*`, `modules/Page/*`, `modules/Public/*`, `composer.json`, `phpunit.xml`, `database/migrations/*`.

### 3.32. Admin User Management UI — mở rộng `modules/Admin/` — v0.0.46

**Architecture**: `modules/Admin/` (đã có từ CMS-045) mở rộng thêm 8 Controller HTML quản lý User — **không thay đổi `modules/User/*` (API JSON)**, không tạo Module `Admin\User` riêng, không tạo Service/Repository Layer. HTML flow dùng Controller riêng biệt hoàn toàn với API JSON (khác Controller, khác response type), theo đúng tiền lệ tách biệt Admin/Public/API đã thiết lập từ CMS-044/045.

**Routes** (mở rộng `modules/Admin/routes.php`):
```
GET  /admin/users
GET  /admin/users/create
POST /admin/users
GET  /admin/users/{id}/edit
POST /admin/users/{id}
POST /admin/users/{id}/lock
POST /admin/users/{id}/unlock
POST /admin/users/{id}/role
```
**Không có `GET /admin/users/{id}`** (trang chi tiết riêng) và **không Delete** — tránh route cùng "shape" (3-segment) với `GET /admin/users/create` (rủi ro collision kiểu CMS-044 nếu thêm sau này). **Không `PATCH`** cho Edit — `core/Http/Request.php` không hỗ trợ Method Spoofing (`_method`), form HTML chỉ gửi được `GET`/`POST` thật.

**Security**:
- `CsrfMiddleware` (đã kích hoạt từ CMS-045) mở rộng bảo vệ toàn bộ route `POST` mới trong cùng `Router::group()`.
- `Authorization::can()` kiểm tra đúng permission `user.view/create/update/lock/assign_role` đã có sẵn (không permission mới) — gọi trực tiếp trong Controller, không `AuthorizationMiddleware`.
- Không có quyền → `Response::html('403 Forbidden', 403)` (không JSON, không Forbidden View riêng — tối giản đúng mức Foundation).

**Trùng lặp có chủ đích (Owner Decision — Phương án A)**: `UserCreateController` (Admin) copy nguyên `Database::transaction()` từ `Modules\User\CreateUserController` (role validate trong transaction, `password_hash()`, bắt riêng `\InvalidArgumentException`/`QueryException`) — chấp nhận trùng lặp lớn hơn hẳn các trường hợp trước (Dashboard/Home/PublicPage chỉ trùng vài dòng SELECT), vì dự án chưa có Service Layer và không tạo abstraction chỉ để tránh 1 chỗ trùng. **Technical Debt ghi nhận**: nếu tương lai có thêm UI/API consumer thứ 3 cho cùng logic, cân nhắc trích xuất Service.

**Fix sau PHPUnit thật**: `testCreateUserDuplicateEmailRendersFormAgainWithoutCreating` FAIL ban đầu (302 thay vì 200) — Root Cause Analysis xác nhận lỗi nằm ở `tests/Core/AdminUserManagementUiTest.php::migrate()` thiếu `CREATE UNIQUE INDEX uq_users_email` (có ở `ModuleUserIntegrationTest.php` nhưng bị bỏ sót khi viết test mới), khiến `INSERT` trùng email không ném `QueryException` → không kích hoạt nhánh lỗi. **Không phải lỗi `UserCreateController`** — Controller không đổi, chỉ sửa 1 dòng trong test.

**Testing**: `tests/Core/AdminUserManagementUiTest.php` (12 test) — cùng pattern `AdminUiFoundationTest` (`ModuleManager` trỏ `modules/` thật, `View` dùng `themes/default/` thật), `actingAs()` ghi thẳng Session (không qua `AuthenticationService::attempt()` thật, giống cách `ModuleUserIntegrationTest` test API JSON).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `database/*`, `composer.json`, `phpunit.xml`.

### 3.33. Admin Role Management UI — mở rộng `modules/Admin/` — v0.0.47

**Controllers** (`modules/Admin/Role*Controller.php`, mỗi Controller đúng 1 `handle()`, `Database` trực tiếp, copy logic từ `Modules\Role\*Controller` tương ứng — không sửa file gốc): `RoleListController`, `RoleShowCreateController`, `RoleCreateController`, `RoleShowEditController`, `RoleUpdateController`, `RoleDeleteController`, `RoleShowPermissionsController`, `RoleAssignPermissionsController`.

**Routes** (mở rộng `modules/Admin/routes.php`, bọc trong `CsrfMiddleware` group đã có từ CMS-045 cho toàn bộ route ghi):
```
GET  /admin/roles
GET  /admin/roles/create
POST /admin/roles
GET  /admin/roles/{id}/edit
POST /admin/roles/{id}
POST /admin/roles/{id}/delete
GET  /admin/roles/{id}/permissions
POST /admin/roles/{id}/permissions
```
`POST` thay `PATCH`/`DELETE` cho Edit/Delete — `core/Http/Request.php` không hỗ trợ Method Spoofing (đúng tiền lệ CMS-046).

**Views**: `themes/default/views/admin/pages/roles/{list,create,edit,permissions}.php`, đều `extend('admin.layouts.main')`.

**Authorization**: tái dùng đúng 5 permission có sẵn từ `bin/bootstrap.php` (CMS-040), không permission mới — `role.view`, `role.create`, `role.update`, `role.delete`, `role.assign_permission`. Không có quyền → `Response::html('403 Forbidden', 403)` (đúng pattern CMS-046).

**System Role handling** (Owner Decision #3, CMS-047): xác định System Role bằng `tenant_id IS NULL` — **nhất quán tuyệt đối với `modules/Role/*`**, không dùng cột `roles.is_system` (tồn tại trong migration thật nhưng không Controller nào trong `modules/Role/*` đọc/ghi — cột chết). `list.php` ẩn nút Sửa/Xoá cho System Role (UX), Controller vẫn tự chặn 403 độc lập (defense in depth, không phụ thuộc UI ẩn nút). Trang Permissions **vẫn xem được cho System Role** (200 — "View allowed", Owner Decision 3 gốc CMS-038), chỉ hành động `POST` assign mới bị chặn 403.

**Permission Assignment limitation** (Owner Decision #1, CMS-047): `Modules\Role\AssignPermissionController` (JSON) chỉ hỗ trợ **ADD** (INSERT idempotent) — **không có endpoint REMOVE nào trong toàn bộ dự án**. Admin UI khớp đúng capability này: `permissions.php` chia "Đã gán" (chỉ hiển thị) / "Chưa gán" (nút "Gán" từng permission) — **không có nút "Gỡ"**. Không tạo `DELETE FROM role_permissions` mới trong Admin Controller (tránh business logic mới ngoài phạm vi copy). Ghi nhận Technical Debt: hệ thống hiện tại không có cách nào gỡ permission khỏi role (cả JSON lẫn HTML).

**Delete Role error handling** (Owner Decision #2, CMS-047): khác các action khác (vốn im lặng redirect khi lỗi, ví dụ `UserAssignRoleController` CMS-046), Delete là hành động phá huỷ dữ liệu nên trả HTML rõ lý do: `Response::html('403 Forbidden', 403)` (System Role) / `Response::html('409 Role dang duoc su dung', 409)` (đang gán cho user, kiểm tra qua `COUNT(*) FROM user_site_roles`, copy nguyên từ `DeleteRoleController`).

**CSRF flow**: tái sử dụng nguyên `CsrfMiddleware` group từ CMS-045, không sửa `core/Csrf.php`/`core/Middleware/*`.

**Root Cause Analysis đáng chú ý (2 vòng sau PHPUnit thật, tổng 7 lần FAIL `419`)**: toàn bộ đều là lỗi chiến lược lấy CSRF token trong `tests/Core/AdminRoleManagementUiTest.php` — **không phải bug `CsrfMiddleware`/Controller**. Vòng 1: `actingAs()` thiếu quyền `role.view` khi lấy token từ trang List (trang trả 403, không có form); 1 test lấy token lần 2 từ trang không còn form (permission vừa gán đã rời khỏi danh sách "chưa gán"). Vòng 2: test System Role chỉ seed 1 role duy nhất khiến trang List không có form Delete nào (`list.php` chỉ render form Delete cho role không phải system) — seed thêm 1 Tenant Role "vô hại" để có token. Toàn bộ fix nằm trong file test.

**Testing**: `tests/Core/AdminRoleManagementUiTest.php` (14 test) — cùng pattern `AdminUserManagementUiTest`/`AdminUiFoundationTest` (`ModuleManager` trỏ `modules/` thật, `View` dùng `themes/default/` thật, CSRF qua `CsrfMiddleware` thật).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `database/*`, `composer.json`, `phpunit.xml`.

### 3.34. Media Module — `modules/Media/` — v0.0.48

**Database**: `database/migrations/2026_08_03_000001_create_media_table.php` — bảng `media` (Content Module thứ 2 sau `pages`, MVP tối giản): `tenant_id, file_name, path, mime_type, size (BIGINT), alt_text NULL, title NULL, caption NULL, uploaded_by, created_at`. FK `tenant_id → sites CASCADE`, `uploaded_by → users RESTRICT`, index `(tenant_id)`. Không `deleted_at` (hard delete). Đúng convention driver-aware PK (CMS-028/040).

**Routes** (`modules/Media/routes.php`, JSON API phẳng, không `/api/v1` prefix):
```
GET    /media          media.view
POST   /media          media.upload
PATCH  /media/{id}      media.update
DELETE /media/{id}      media.delete
```

**Controllers** (`Database` trực tiếp, không Service/Repository, đúng Controller Contract 1 `handle()`):
- `ListMediaController` — `SELECT ... WHERE tenant_id = ?`, không pagination (đúng tiền lệ `ListPagesController`).
- `UploadMediaController` — validate thủ công trong Controller (không rule Validator mới): file bắt buộc + `UPLOAD_ERR_OK`, size ≤ 5MB, mime whitelist `image/jpeg,image/png,image/gif,application/pdf`.
- `UpdateMediaController` — partial update `alt_text`/`title`/`caption` (không `file_name`/`path`/`mime_type`/`size`), đúng pattern `EditPageController`.
- `DeleteMediaController` — 404 cho media không thuộc tenant hiện tại.

**Upload flow**:
```
1. Validate (file/size/mime) - ngoai transaction (khong can DB)
2. Move file vao storage/app/media/{tenant_id}/{ten_file_duy_nhat} - ngoai transaction (I/O)
3. Database::transaction(): INSERT media + UPDATE sites.storage_used_bytes +=
4. Transaction loi -> unlink() file vua move (don rac thu cong, khong tu rollback duoc filesystem)
```

**Delete flow**:
```
1. SELECT media WHERE id=? AND tenant_id=? - xac nhan ton tai, lay path+size TRUOC
2. Database::transaction(): DELETE media + UPDATE sites.storage_used_bytes -=
3. Transaction commit thanh cong -> unlink(path) SAU CUNG (file khong ton tai thi khong throw)
```
Cả 2 luồng dùng đúng 1 `Database::transaction()` bọc 2 câu SQL liên quan — đúng nguyên tắc "multi-step writes" (`database-design.md` mục 6.3), đúng yêu cầu Owner (Upload/Delete phải cùng transaction).

**Tenant isolation**: mọi Controller scoped `TenantManager::id()`; `Update`/`Delete` trả `404` (không `403`) cho media thuộc tenant khác — nhất quán nguyên tắc cross-tenant xuyên suốt dự án từ CMS-037.

**Permission model**: `media.view/upload/update/delete` — mở rộng `bin/bootstrap.php` `$permissionKeys` (16 → 20), đúng tiền lệ chính thức từ CMS-040 (không seed permission riêng, không sửa Role Module).

**Storage**: Local filesystem thuần (`storage/app/media/{tenant_id}/{tên_file_duy_nhất}`) — **không** interface `StorageDriver` (0 abstraction khi chỉ có 1 implementation, nhất quán `Database`/`View`). `sites.storage_used_bytes` (tồn tại từ CMS-028, chưa từng dùng) **lần đầu được kích hoạt** — cộng khi upload, trừ khi xoá.

**Validation**: hoàn toàn thủ công trong Controller (size cứng `5 * 1024 * 1024`, mime whitelist hardcode 4 loại) — **không** rule Validator mới, **không** sửa `core/Validator.php` (`core/Validator.php` không có rule cho file).

**Phạm vi cắt bỏ khỏi spec gốc `07-module-media.md`** (Owner Decision CMS-041, đều là MVP tối giản có chủ đích):
- **Không** `media_folders` — chưa có Admin UI Media để tổ chức thư mục.
- **Không** `media_variants` (resize/thumbnail/WebP) — `composer.json` không có thư viện xử lý ảnh (GD/Imagick), không thêm dependency mới.
- **Không** `media_usages` (theo dõi nơi sử dụng) — chưa có consumer thật nào tham chiếu `media` (`pages` không có `featured_image_id`).
- **Không** `StorageDriver`/S3 — chỉ Local filesystem.
- **Không** Queue xử lý bất đồng bộ — nhất quán Owner Decision CMS-025 (Queue để sau khi Foundation hoàn tất).
- **Không** Admin UI HTML cho Media trong CMS-041 — đúng tiền lệ Page (CMS-040 JSON trước, Admin UI là task riêng sau).

**Xung đột kỹ thuật phát hiện khi implement**: `move_uploaded_file()` chỉ hoạt động với upload HTTP thật (`is_uploaded_file()` PHP tự kiểm tra nội bộ) — luôn trả `false` trong môi trường CLI/PHPUnit, không thể test "Upload success". Owner Decision (qua `AskUserQuestion`): thêm 1 nhánh điều kiện `is_uploaded_file($tmp) ? move_uploaded_file() : rename()` trong `UploadMediaController` — `move_uploaded_file()` vẫn là đường đi chính cho request thật (giữ bảo mật chống path traversal/file inclusion), `rename()` chỉ kích hoạt ngoài HTTP thật (CLI/test) — không abstraction/dependency mới, đúng pattern Laravel/Symfony đã giải quyết cùng vấn đề.

**Technical Debt ghi nhận** (Owner Decision, chưa xử lý): (1) `rename()` fallback chỉ phục vụ CLI/testing, không phải hành vi production thật; (2) chưa có image processing (resize/variant/WebP) — cần thêm dependency mới nếu triển khai; (3) chưa có `media_usages` — cần làm khi Page/Post thật sự tham chiếu `media`.

**Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu `2026_08_03_000001_create_media_table` (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật — migration mới đã chạy/rollback đúng thứ tự thật theo output PHPUnit, không phải bug migration) — sửa đúng 1 dòng, đúng root cause đã gặp ở CMS-040.

**Testing**: `tests/Core/ModuleMediaIntegrationTest.php` (13 test) — cùng pattern `ModulePageIntegrationTest` (`ModuleManager` trỏ `modules/` thật). `UploadMediaController`/`DeleteMediaController` override qua `Container::singleton()` với thư mục storage TEMP riêng (không ghi file thật vào `storage/app/media` của repo, đúng pattern `View` CMS-044/045).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Page/*`, `modules/Public/*`, `modules/Admin/*`, `composer.json`, `phpunit.xml`, migration cũ.

### 3.35. Menu Module — `modules/Menu/` — v0.0.49

**Database**: `database/migrations/2026_08_04_000001_create_menus_table.php` (`menus`: `tenant_id, name, location_key`, UNIQUE `(tenant_id, location_key)`, FK `tenant_id → sites CASCADE`) + `2026_08_04_000002_create_menu_items_table.php` (`menu_items`: `menu_id, parent_id self NULL, label, type VARCHAR(20), reference_id NULL, url NULL, target, sort_order`, FK `menu_id → menus CASCADE`, `parent_id → menu_items self CASCADE`, index `(menu_id, sort_order)`). `reference_id` không FK cứng (polymorphic, chỉ có ý nghĩa khi `type=page`, đúng lý do `database-design.md` đã tự nêu cho bảng polymorphic).

**Routes** (JSON API phẳng, `modules/Menu/routes.php`):
```
GET    /menus              menu.view
POST   /menus                menu.create
GET    /menus/{id}            menu.view
PATCH  /menus/{id}             menu.update
DELETE /menus/{id}              menu.delete
POST   /menus/{id}/items      menu.update
PATCH  /menu-items/{id}         menu.update
DELETE /menu-items/{id}          menu.update
```
**Tiền tố `/menu-items` tách biệt hoàn toàn `/menus`** — chủ động né rủi ro collision phát hiện ở Architecture Analysis: `GET /menus/{id}` (admin, numeric) và khả năng tương lai `GET /menus/{location}` (public, string) sẽ compile ra **cùng 1 regex** dù khác tên tham số, không phát hiện được lúc đăng ký (`Route::signature()` so sánh chuỗi literal, không so regex) — route public cho Menu **chưa tồn tại trong CMS-042**, để lại thiết kế cho task tương lai.

**Controllers** (`Database` trực tiếp, không Service/Repository/Interface/Helper/Trait, đúng Controller Contract 1 `handle()`): `ListMenusController`, `CreateMenuController`, `ShowMenuController`, `UpdateMenuController`, `DeleteMenuController`, `CreateMenuItemController`, `UpdateMenuItemController`, `DeleteMenuItemController`.

**Dựng cây (tree)** — `ShowMenuController`: 1 câu `SELECT ... WHERE menu_id = ?` duy nhất, gom kết quả theo `parent_id` bằng PHP thuần rồi đệ quy gắn `children` — **không recursive SQL, không N+1**. Lần đầu dự án cần logic dựng cây phân cấp (trước đó `pages.parent_id` tồn tại nhưng chưa Controller nào dựng cây từ đó).

**Transaction**:
- `DeleteMenuController`: `Database::transaction()` bọc 2 câu `DELETE` liên quan (`menu_items` rồi `menus`) — không dựa FK CASCADE thật (SQLite test không enforce mặc định, đã xác nhận nhiều lần từ CMS-030).
- `DeleteMenuItemController`: BFS gom id con cháu **chỉ dùng `SELECT`** (không tính là write), kết quả cuối cùng chỉ 1 câu `DELETE ... WHERE id IN (...)` duy nhất — **không transaction** (đúng nguyên tắc "transaction khi có ≥2 câu SQL ghi liên quan", ở đây chỉ có 1 câu ghi dù xoá nhiều dòng).

**Validation nghiệp vụ trong Controller** (không rule Validator mới, không sửa `core/Validator.php`): `type=page` bắt buộc `reference_id` trỏ tới `page` tồn tại cùng tenant; `type=custom` bắt buộc `url`; `parent_id` phải thuộc cùng `menu_id`; **chặn self-parent** (`parent_id === chính id item đang sửa` → `422`, `UpdateMenuItemController`).

**Permission**: `menu.view/create/update/delete` (mở rộng `bin/bootstrap.php` 20 → 24) — hạt mịn theo convention `resource.action`, **không** dùng `menu.manage` như spec gốc `08-module-menu.md`. Thao tác trên `menu_items` dùng chung `menu.update` (không tách `menu_item.*` — Item luôn phụ thuộc Menu, không phải resource độc lập).

**Phạm vi cắt bỏ khỏi spec gốc** (Owner Decision CMS-042): `menu_items.type` chỉ `page`/`custom` (bỏ `post_category`/`product_category` — Module Post/Product chưa tồn tại); **không** kéo-thả, **không** endpoint thay toàn bộ cấu trúc (`PUT` bulk-replace) — CRUD từng bản ghi; **không** Hook (`menu.updated`...) — `core/Hook.php` tồn tại nhưng chưa Module nghiệp vụ nào từng bắn hook thật; **không** Cache invalidation — chưa có consumer thật (chưa Public rendering); **không** Admin UI HTML, **không** Public rendering, **không** sửa `modules/Public/*`/theme layout — đúng tiền lệ Page (CMS-040)/Media (CMS-041): Module JSON trước, UI/tích hợp là task riêng sau; **không** cơ chế Theme khai báo `location_key` hợp lệ (`ThemeManager`/`theme.json` không có cơ chế này) — `location_key` là chuỗi tự do (`required|string|max:50`).

**Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu 2 migration mới (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật, không phải lỗi migration) — đúng root cause đã gặp ở CMS-040/041, sửa đúng 2 dòng.

**Testing**: `tests/Core/ModuleMenuIntegrationTest.php` (20 test) — cùng pattern `ModulePageIntegrationTest`/`ModuleMediaIntegrationTest` (`ModuleManager` trỏ `modules/` thật).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Page/*`, `modules/Public/*`, `modules/Admin/*`, `modules/Media/*`, `composer.json`, `phpunit.xml`, `themes/*`, migration cũ.

### 3.36. SEO Module — `modules/Seo/` — v0.0.50

**Database**: `database/migrations/2026_08_05_000001_create_seo_meta_table.php` — bảng `seo_meta` (`tenant_id, entity_type VARCHAR(20), entity_id, title, description, canonical, og_image_id NULL, schema_type NULL, schema_data TEXT NULL`), UNIQUE `(tenant_id, entity_type, entity_id)` (mỗi entity chỉ đúng 1 bản ghi SEO), FK `tenant_id → sites CASCADE`, FK `og_image_id → media ON DELETE SET NULL` (khả thi thật vì `media` đã tồn tại từ CMS-041 — khác `menu_items.reference_id` không FK được vì Post/Product chưa tồn tại; `SET NULL` vì xoá ảnh không nên xoá theo `seo_meta`, cũng không nên chặn xoá ảnh chỉ vì đang làm OG image). `schema_data` lưu `TEXT` (JSON string, Application layer tự `json_encode`/`json_decode`) — đúng Owner Decision CMS-040 đã áp dụng cho `pages.content`.

**Routes** (JSON API phẳng, `modules/Seo/routes.php`):
```
GET    /seo/{entity_type}/{entity_id}    seo.view
PATCH  /seo/{entity_type}/{entity_id}     seo.update
```
3-segment sau `/seo` → không va chạm `GET /{slug}` (Public, 1-segment). **Không** `/sitemap.xml`/`/robots.txt`/route public nào khác — xem "Deferred Features".

**Controllers** (`Database` trực tiếp, không Service/Repository/Interface/Trait/Helper): `ShowSeoMetaController`, `UpdateSeoMetaController`. Route param `entity_type`/`entity_id` được gộp vào `$data` trước khi gọi `Validator::validate()` (tận dụng `Validator` sẵn có thay vì so sánh thủ công riêng lẻ). `entity_type` chỉ hỗ trợ thật `page` (validate `in:page`) — sai hoặc `entity_id` không trỏ tới `page` tồn tại cùng tenant → `404` (coi như entity không tồn tại, không phải lỗi input).

**Upsert Strategy** (lần đầu dự án dùng pattern này — trước đó Page/Menu/Media đều Create/Update tách biệt hoàn toàn):
```
SELECT id FROM seo_meta WHERE tenant_id=? AND entity_type=? AND entity_id=?
  Khong co dong nao -> INSERT (field khong gui -> NULL)
  Da co             -> UPDATE (chi field co trong request - partial, giu nguyen field khac)
```
**Không `Database::transaction()`** (mỗi lần gọi chỉ đúng 1 câu SQL ghi — `INSERT` hoặc `UPDATE`, không bao giờ cả 2), **không retry, không lock**. Race condition lý thuyết giữa `SELECT` và `INSERT` (2 request PATCH đồng thời cùng entity chưa có `seo_meta`) được chấp nhận cho MVP — thao tác Admin tần suất thấp, không phải public concurrent write — ghi nhận ở "Deferred Features"/Technical Debt.

**Validation**: `entity_type` (`required|in:page`), `entity_id` (`required|integer`), `title`/`description`/`canonical`/`schema_type` (`nullable|string|max:N`), `og_image_id` (`nullable|integer`, xác nhận tồn tại trong `media` cùng tenant ở Controller — không FK-only), `schema_data` (`nullable|array`). Toàn bộ dùng rule có sẵn trong `core/Validator.php` (`required/in/integer/nullable/string/max/array`, đã xác nhận `max` hoạt động qua `mb_strlen()`/`sizeOf()`) — không rule mới, không sửa `core/Validator.php`.

**Permission**: chỉ `seo.view`/`seo.update` (mở rộng `bin/bootstrap.php` 24 → 26) — **không** `seo.manage` (spec gốc `10-module-seo.md`), **không** `seo.create`/`seo.delete`: `seo_meta` là upsert-theo-entity-đã-tồn-tại (page), không có hành động "tạo"/"xoá" độc lập nào trong route table.

**Tenant Isolation**: `TenantManager::id()` mọi truy vấn; `entity` (page) không tồn tại/khác tenant → `404` — nhất quán nguyên tắc cross-tenant xuyên suốt dự án từ CMS-037.

**Architectural Decisions**: MVP tối giản cắt bỏ khỏi spec gốc — chỉ `seo_meta`, `entity_type` chỉ `page`.

**Deferred Features** (Owner Decision CMS-043, hoãn hoàn toàn — không thuộc phạm vi CRUD đơn giản):
- **`redirects`**: đòi hỏi can thiệp toàn cục vào luồng dispatch (kiểm tra `from_path` trước khi trả 404) — không phải CRUD, cần Middleware toàn cục mới hoặc sửa `modules/Public/PublicPageController.php` (đã khoá).
- **`sitemap_cache`, `/sitemap.xml`, `/robots.txt`**: route tĩnh 1-segment — nếu Module `Seo` load sau `Public` (mặc định alphabet: "Public" < "Seo"), `GET /{slug}` (Public, CMS-044) sẽ **nuốt mất** các route này trước khi Router chạm tới (không phải "route thắng", mà "route không bao giờ chạy được"). Cách khắc phục duy nhất — thêm `"seo"` vào `modules/Public/module.json.dependencies` — đòi hỏi chạm Module đã hoàn thành, ngoài phạm vi CMS-043.
- **Hook** (`seo.meta_updated`, lắng nghe `page.published`...) — dự án chưa có tiền lệ Module nghiệp vụ nào bắn/lắng nghe Hook thật.
- **Admin UI, Public rendering** (`<head>` inject title/OG/schema, Breadcrumb, SEO Score) — đúng tiền lệ Page/Media/Menu: Module JSON trước, UI/tích hợp là task riêng sau.
- **Race condition upsert** (xem Upsert Strategy) — Technical Debt chấp nhận được, chưa xử lý.

**Testing**: `tests/Core/ModuleSeoIntegrationTest.php` (17 test) — cùng pattern `ModuleMenuIntegrationTest`/`ModuleMediaIntegrationTest`.

**Fix sau PHPUnit thật**: `tests/Core/RealMigrationsTest.php::EXPECTED_ORDER` thiếu `2026_08_05_000001_create_seo_meta_table` (gây 3 failure toàn suite, thuần test-expectation chưa cập nhật) — đúng root cause đã gặp ở CMS-040/041/042, sửa đúng 1 dòng.

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Page/*`, `modules/Menu/*`, `modules/Media/*`, `modules/Public/*`, `modules/Admin/*`, `composer.json`, `phpunit.xml`, `themes/*`, migration cũ.

### 3.37. Public Website Polish — `modules/Public/*` + `themes/default/*` — v0.0.51

**Bối cảnh**: từ CMS-044 tới CMS-043, `modules/Public/*` chỉ render `pages` thuần (title + content JSON dump) — không Menu, không SEO, không 404 theme, không Header/Footer. Đây là task "polish" đầu tiên **mở khoá** 2 Controller Public đã hoàn thành để tích hợp dữ liệu từ 3 Module JSON đã có sẵn (`Menu`, `Seo`, và gián tiếp `Page`), không tạo Module mới, không migration mới.

**Public Controllers** (`modules/Public/HomeController.php`, `modules/Public/PublicPageController.php`) — mỗi Controller bổ sung 3 method: `render404()`, `fetchSeoMeta()`, `fetchNavigation()` (+ `buildTree()`/`attachChildren()`) — **trùng lặp y hệt giữa 2 Controller có chủ đích** (không Service/Trait/Helper dùng chung, đúng tiền lệ CMS-044/045/046).

**Theme Layout** (`themes/default/views/layouts/main.php`): `<head>` thêm khối `<meta description>`/`<link canonical>`/`<meta og:title|og:description>`/JSON-LD (chỉ render khi có `$seo`); `<body>` thêm `<header><nav>...</nav></header>` (chỉ render khi `$menu` không rỗng) và `<footer>` khung tĩnh rỗng.

**Navigation Rendering**: `fetchNavigation()` — 1 query `menus` (`tenant_id + location_key='header'`, cố định, không cơ chế Theme khai báo location), nếu có thì 1 query `menu_items` (toàn bộ, `ORDER BY sort_order`) + 1 query `pages` `IN (...)` để resolve slug cho item `type=page` (không N+1). Dựng cây bằng PHP thuần (`buildTree()`/`attachChildren()`) — **copy logic từ `Modules\Menu\ShowMenuController::buildTree()`**, không tái sử dụng chéo Module (khác Module, khác namespace, không có Service dùng chung). Layout chỉ render tối đa **2 cấp** (cha + con) — không đệ quy vô hạn trong template (khác Controller, nơi `buildTree()` PHP đệ quy không giới hạn cấp, nhưng markup chỉ hiển thị 2 cấp theo Owner Decision).

**SEO Meta Injection**: `fetchSeoMeta()` — `SELECT` `seo_meta` theo `tenant_id + entity_type='page' + entity_id`. **Fallback Option A**: không có bản ghi → `null`, `<title>` giữ nguyên `pages.title` (hành vi gốc), không render description/canonical/OG/JSON-LD. Có bản ghi → `<title>` ưu tiên `seo_meta.title`.

**JSON-LD**: `schema_data` (đã là JSON hợp lệ từ lúc ghi ở CMS-043) được `json_encode(..., JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)` rồi `$this->raw()` — **không dùng `$this->e()`** (sai ngữ cảnh, `e()` chỉ escape cho HTML text, không chặn được `</script>` breakout trong JSON) — các flag `JSON_HEX_*` là biện pháp chống XSS bắt buộc cho ngữ cảnh này.

**Active Menu**: `fetchNavigation()` nhận `$activePageId` (id của page/homepage đang render) — item `type=page` có `reference_id === $activePageId` được gắn `active=true`, layout render `class="active"` tương ứng. Không query thêm (tính toán thuần PHP khi dựng danh sách item).

**404 Theme Rendering**: `render404()` — nếu `View::exists('pages.404')` (theme có `themes/default/views/pages/404.php`, mới tạo) → render theo theme, status `404`; nếu không (ví dụ fixture theme test không có view này) → fallback nguyên hành vi cũ `Response::html('404 Not Found', 404)`. Đảm bảo test dùng fixture theme khác (`tests/Fixtures/themes/test-theme/`) không bị ảnh hưởng.

**Architectural Decisions** (Owner Decision, PHASE 2): không `og:image` (chưa có route phục vụ file Media qua HTTP); ẩn hẳn `<nav>` khi tenant chưa có Menu location `header` (không hiển thị khung rỗng); không Middleware/Event/Hook/Cache mới; không sửa Core.

**Deferred Features** (Owner Decision, hoãn hoàn toàn khỏi phạm vi "polish"): Breadcrumb (logic dựng "ancestor chain" từ `pages.parent_id` hoàn toàn mới, không tái dùng được gì có sẵn); Media URL (chưa có route công khai phục vụ file `storage/app/media/*`); Search (feature mới hoàn toàn); `/sitemap.xml`, `/robots.txt` (route tĩnh 1-segment, rủi ro bị `GET /{slug}` nuốt — đã phân tích từ CMS-043); Redirect support (đòi hỏi can thiệp toàn cục vào luồng dispatch); Asset optimization (chưa có asset nào tồn tại trong `themes/default/` để tối ưu).

**Testing**: `tests/Core/PublicPageRenderingTest.php` (8 test, không đổi số lượng) — `migrate()` bổ sung bảng `menus`/`menu_items`/`seo_meta` (bắt buộc vì Controller giờ luôn query 3 bảng này ở mọi request thành công).

**Không sửa**: `core/*`, `modules/Auth/*`, `modules/User/*`, `modules/Role/*`, `modules/Page/*`, `modules/Menu/*`, `modules/Media/*`, `modules/Seo/*`, `modules/Admin/*`, `composer.json`, `phpunit.xml`, `database/*`.

## 4. Nguyên tắc áp dụng xuyên suốt (đã enforce qua Code Review từng task)

- **Không static/global mutable state** ở bất kỳ đâu — nguyên tắc bị vi phạm 1 lần duy nhất (bản đầu `Config`) và đã sửa ngay từ CMS-002, không tái diễn.
- **`final class`** cho mọi class core (trừ khi có lý do rõ ràng cần kế thừa) — composition thay vì inheritance.
- **`readonly` property** cho dữ liệu bất biến; các đối tượng Immutable (`Request`) dùng `new self(...)` thay vì `clone` (PHP 8.1 không cho gán lại `readonly` trong `__clone()`).
- **Exception theo từng mối lo cụ thể**, luôn có class base + subclass rõ nghĩa (không dùng `Exception`/`RuntimeException` trần).
- **Hàm built-in PHP có tiền tố `\`** khi gọi trong namespace (tối ưu resolve, tránh IDE hint).
- **Global function/superglobal truy cập bị cô lập vào đúng 1 điểm**: `getenv()` chỉ trong `config/*.php`; `$_SERVER/$_GET/$_POST` chỉ trong `Request::fromGlobals()`; `$_SESSION/session_*()` chỉ trong `Session`.
- **Quy ước không thể enforce bằng type system** (Service Provider `register()` không business logic, escape tường minh trong View, Controller không `echo`/`exit`) được ghi rõ trong docblock — bắt buộc kiểm tra ở Code Review khi module viết Controller/Plugin thật.

## 5. Testing Summary

**536 test, 1025 assertion — PASS** (PHP 8.3.30), Verified PASS thật tính đến Public Website Polish (số lượng không đổi so với CMS-043 — `PublicPageRenderingTest` vẫn 8 test/14 assertion, chỉ bổ sung fixture bảng, không thêm/bớt test method). Chạy trên SQLite in-memory (Database/View/Router/Migration integration) — không phụ thuộc MySQL thật. 4 test skip có điều kiện (Redis) khi môi trường không có `ext-redis`. **Lưu ý**: bảng này chưa có dòng cho `modules/User/`/`ModuleUserIntegrationTest` (CMS-037, 7 test) — cùng khoảng trống tài liệu đã ghi ở đầu file, không phải thiếu sót của các lượt cập nhật sau đó.

| Component | Số test | Chiến lược |
|---|---|---|
| Container | 12 | Unit thuần (fixture class nhỏ) |
| Database + QueryBuilder | 31 | Integration (SQLite in-memory) |
| View | 15 + 1 regression | Integration (fixture 2 theme) |
| Router + HTTP (Request/Response/Middleware) | 24 + 15 (CMS-015) + 17 (CMS-016) + 6 (CMS-018) | Integration (fixture Controller/Middleware) + 1 regression toàn chuỗi Container+Database+View |
| Session | 13 | Integration (session thật, mô phỏng nhiều "request" qua `session_write_close()`) |
| Cache | 20 + 1 regression | Integration (filesystem thật, temp dir) + Redis có điều kiện (skip nếu không có `ext-redis`) |
| Hook | 17 + 2 regression | Unit thuần (không I/O) + Regression qua Container |
| ModuleManager | 9 + 1 regression | Integration (fixture module thật) + Regression qua Container+Router |
| Application | 11 (10 + 1 mới CMS-019) | Integration toàn chuỗi (2 fixture app: debug bật/tắt, module thật, filesystem thật) |
| PluginManager | 16 + 2 regression | Integration (fixture plugin thật, quan sát qua `Hook::apply()` filter chain) + Regression qua Container+Hook |
| MigrationManager | 16 (gồm 1 regression) | Integration (SQLite in-memory thật, fixture migration thật, temp dir cho kịch bản batch/rollback nhiều lần chạy) |
| Validator | 31 | Unit thuần (không I/O, 0 dependency vào Core Component khác) |
| ExceptionHandler | 8 | Unit thuần (không I/O, 0 dependency vào Core Component khác) |
| Csrf + CsrfMiddleware | 6 + 11 | Integration (Session thật, không mock) |
| Auth + AuthMiddleware | 13 + 3 | Integration (Session thật, không mock) |
| Authorization + AuthorizationMiddleware | 17 + 4 | Integration (Session thật, không mock) |
| RateLimiter + RateLimitMiddleware | 14 + 4 | Integration (Session thật, không mock) |
| Logger | 8 | Integration (filesystem thật, temp dir) |
| TenantManager | 9 | Unit thuần (0 dependency, không I/O) |
| ThemeManager | 7 | Integration (filesystem thật, fixture theme.json) |
| MiddlewarePipeline (parameterization) | 5 | Unit/Integration (Container thật, fixture middleware class-string + instance) |
| Database Migration Phase 2 (Tenant/Auth/Role) | 11 | Integration (SQLite in-memory thật, `MigrationManager` chạy migration thật trong `database/migrations/`) |
| Logger Integration (Application) | 2 (+ regression trong `ApplicationTest` cũ) | Integration (filesystem thật, `Hook`/`Container` thật qua `Application::bootstrap()`) |
| TenantResolverMiddleware | 8 (4 CMS-030 domain resolution + 4 CMS-036 site status policy) | Integration (`Database` SQLite in-memory thật, seed tay) |
| TenantManager Integration (Application/View) | 3 (+ 5 test cũ được thêm seed) | Integration (`Database` SQLite in-memory thật qua `Application::bootstrap()`) |
| AuthenticationService | 15 (10 CMS-031 + 5 CMS-033 rate limiting) | Integration (`Database` SQLite in-memory thật, seed tay, `Session`/`Auth`/`TenantManager`/`RateLimiter` thật) |
| Auth Module (`modules/Auth/`) | 7 (5 CMS-034 login + 2 CMS-035 logout) | Integration (`ModuleManager` trỏ `modules/` thật, `Router::dispatch()` thật, không fixture) |
| Role Module (`modules/Role/`) | 12 | Integration (`ModuleManager` trỏ `modules/` thật) |
| Dashboard Module (`modules/Dashboard/`) | 2 | Integration (`ModuleManager` trỏ `modules/` thật) |
| Page Module (`modules/Page/`) | 17 | Integration (`ModuleManager` trỏ `modules/` thật) |
| Real Migrations (`RealMigrationsTest`) | 11 (bao gồm `pages` từ CMS-040) | Integration (`MigrationManager` chạy migration thật trong `database/migrations/`) |
| Public Module (`modules/Public/`) | 8 | Integration (`ModuleManager` trỏ `modules/` thật, `View` dùng fixture theme riêng `tests/Fixtures/themes/test-theme/`) |
| Admin Module (`modules/Admin/`) | 7 | Integration (`ModuleManager` trỏ `modules/` thật, `View` dùng `themes/default/` thật, CSRF qua `CsrfMiddleware` thật) |
| Admin User Management UI (`modules/Admin/User*Controller`) | 12 | Integration (`ModuleManager` trỏ `modules/` thật, `View` dùng `themes/default/` thật, CSRF qua `CsrfMiddleware` thật) |
| Admin Role Management UI (`modules/Admin/Role*Controller`) | 14 | Integration (`ModuleManager` trỏ `modules/` thật, `View` dùng `themes/default/` thật, CSRF qua `CsrfMiddleware` thật) |
| Media Module (`modules/Media/`) | 13 | Integration (`ModuleManager` trỏ `modules/` thật, `Upload`/`DeleteMediaController` override storage TEMP qua `Container::singleton()`) |
| Menu Module (`modules/Menu/`) | 20 | Integration (`ModuleManager` trỏ `modules/` thật) |
| SEO Module (`modules/Seo/`) | 17 | Integration (`ModuleManager` trỏ `modules/` thật) |

## 6. Quyết định còn mở (chưa chặn, cần chốt trước Phase 3)

| # | Vấn đề | Trạng thái |
|---|---|---|
| 1 | `.env` loader chưa quyết định | Theo dõi từ CMS-002 |
| ~~2~~ | ~~`Database`/`View`/`Session` chưa có interface~~ | **✅ Đã chốt chính thức** — nguyên tắc dự án: "Không tạo interface nếu chỉ có một implementation". Giữ nguyên `final class`, không thêm interface. |
| 3 | `join()` chưa hỗ trợ `table.column` (chỉ tên cột trần) | Chưa cần, module nào cần join thật sẽ mở rộng |
| 4 | `Response::json()` chưa tự bọc `{success, data, message, errors}` | Cân nhắc `Response::apiSuccess()/apiError()` khi module API đầu tiên viết — **không tạo trước khi có ≥2 nơi thực sự cần** (đúng "không tạo abstraction chỉ để DRY") |
| ~~5~~ | ~~`Router::dispatch()` ném exception cho 404/405~~ | **✅ Đã giải quyết ở CMS-011** — `Application::handle()` bắt và map thành `Response` |
| 6 | `Config::get()`/`Session::get()` trùng logic duyệt dot-notation | **Giữ nguyên tách biệt** — đúng nguyên tắc "không tạo abstraction chỉ để DRY", không đụng 2 component đã tag version |
| 7 | `FileCacheDriver` không dọn định kỳ entry hết hạn chưa được đọc lại (lazy expiry, không sweep nền); registry tag không có TTL riêng | Chấp nhận được ở giai đoạn này, cần cron dọn định kỳ khi lên production thật |
| 8 | `PluginManager::visit()`/`ModuleManager::visit()` trùng lặp logic topological sort + circular detection | **Đã chấp nhận có chủ đích ở CMS-012** — không tạo abstraction dùng chung, đúng nguyên tắc "không tạo abstraction chỉ để DRY", tránh đụng vào `ModuleManager` đã ổn định |
| 9 | Chưa có cơ chế bật/tắt Module/Plugin theo site (bảng `site_modules`/`site_plugins` thật) — cả `Application::boot()` hiện coi mọi module/plugin đã `discover()` là "enabled" | Theo dõi, cần xử lý ở Phase 2 khi có bảng thật (`database-design.md`) |
| 10 | `MigrationManager`: `Database::transaction()` quanh DDL chỉ thực sự transactional trên SQLite — MySQL implicit-commit DDL, không rollback được nếu lỗi xảy ra sau câu DDL trong cùng 1 migration | Chấp nhận được (giới hạn kỹ thuật của MySQL, không phải lỗi thiết kế), phải ghi rõ trong docblock, không được hiểu ngầm là an toàn tuyệt đối |
| 11 | `MigrationManager`: không hỗ trợ concurrent migration — không có locking chống 2 tiến trình `migrate()`/`rollback()` chạy đồng thời | Chấp nhận cho giai đoạn 1-operator/CLI thủ công hiện tại, cần revisit trước khi có multi-node deploy tự động |
| 12 | `MigrationManager::rollback()` phụ thuộc migration file gốc còn tồn tại trên disk — xoá file khiến rollback bất khả thi (`MigrationNotFoundException`) | Chấp nhận được, đúng đánh đổi của mọi migration system dựa trên file |
| 13 | `Validator`: message lỗi chỉ 1 ngôn ngữ, không i18n đầy đủ; rule set tối thiểu (~16 rule); không rule DB-aware built-in (`unique`/`exists`) | Theo dõi, mở rộng dần theo nhu cầu module thật (Phase 3+) qua `extend()` |
| 14 | `Request::ip()` không hỗ trợ Trusted Proxy — chỉ đáng tin khi PHP nhận kết nối trực tiếp từ client, không qua reverse proxy | Cần bổ sung khi có Nginx/Load Balancer thật phía trước |
| 15 | `Request` không hỗ trợ Method Spoofing (`_method`) — form SSR thuần HTML chỉ dùng được GET/POST | Để dành phase sau khi Module cần verb PUT/DELETE qua `<form>` |
| ~~16~~ | ~~**Giới hạn kiến trúc thật của Middleware**: cơ chế `list<class-string>` + `Container::get(string $id)` (từ CMS-006/018) không hỗ trợ tham số hoá per-route~~ | **✅ Hạ tầng đã giải quyết ở CMS-027** (`v0.0.27`) — `MiddlewarePipeline` giờ chấp nhận cả class-string lẫn `MiddlewareInterface` instance đã cấu hình sẵn. Việc THỰC SỰ dùng khả năng này để tham số hoá `AuthorizationMiddleware`/`RateLimitMiddleware` (roles/permissions/key/maxAttempts riêng từng route) vẫn để dành 1 CMS riêng — CMS-027 chỉ là infrastructure, không redesign 2 middleware đó (Owner Decision) |
| ~~17~~ | ~~`Logger` (CMS-024) chưa được nối vào `Application::logException()`, `Database::onQueryExecuted()` (CMS-004), `Hook::onError()` (CMS-009)~~ | **✅ Một phần đã giải quyết ở CMS-029** (`v0.0.29`) — `logException()` và `Hook::onError()` đã nối `Logger` thật. **Còn deferred có chủ đích**: `Database::onQueryExecuted()` (query logging — fire cho mọi query, chưa có requirement debug/performance thật) và log rotation — để dành CMS riêng khi có nhu cầu thật |
| ~~18~~ | ~~`TenantManager` (CMS-025) chưa được nối vào `View`, chưa có cơ chế resolve domain→tenant thật~~ | **✅ Đã giải quyết ở CMS-030** (`v0.0.30`) — `TenantResolverMiddleware` resolve domain→tenant thật qua `sites`/`site_domains` (CMS-028), `View` đọc `theme_active` từ `TenantManager`. Còn deferred: `Cache`/`QueryBuilder::forTenant()` vẫn chưa nối dây |
| 19 | `ThemeManager` (CMS-026) chưa được nối vào `View`/`Application` — chưa có `ThemeService` kích hoạt theme, chưa có database synchronization service đồng bộ filesystem→bảng `themes` | Quyết định phạm vi có chủ đích (Foundation trước), không phải bug — cần 1 CMS riêng khi có nhu cầu thật (Module Theme/Admin Dashboard) |
| 20 | `roles` (CMS-028): UNIQUE `(tenant_id, name)` không ngăn được 2 role hệ thống (`tenant_id IS NULL`) trùng tên — đúng ANSI SQL semantics (`NULL ≠ NULL` trong composite UNIQUE ở cả SQLite lẫn MySQL), không phải bug migration, phát hiện qua PHPUnit thật | Xử lý ở Service layer khi có CMS Role/Auth Service thật (tự kiểm tra trùng tên trước khi insert khi `tenant_id IS NULL`) — không Trigger, nhất quán ràng buộc homepage `database-design.md` mục 6.1 |
| ~~21~~ | ~~`TenantResolverMiddleware` (CMS-030) chưa kiểm tra `sites.status`~~ | **✅ RESOLVED ở CMS-036** (`v0.0.36`) — fail-closed tuyệt đối, chỉ `status === 'active'` được đi tiếp; `maintenance` → 503, `suspended` → 403, giá trị khác → 403 |
| 22 | `TenantResolverMiddleware` (CMS-030) không normalize domain (không lowercase, không strip `:port` khỏi `Request::getHost()`) — domain có hoa/thường khác nhau hoặc kèm port sẽ không khớp `site_domains` dù về mặt logic là cùng 1 host | Chưa có bằng chứng nhu cầu thật (chưa có site nào cấu hình domain có port khác 80/443) — cần 1 CMS riêng nếu phát sinh |
| 23 | `system_admin.domains` (đã có sẵn trong `config/tenants.php` từ trước CMS-030) chưa được `TenantResolverMiddleware` xử lý — domain Super Admin thật (nếu có) sẽ bị 404 thay vì bypass | Quyết định phạm vi có chủ đích (Owner Decision CMS-030, Q4) — hiện chưa có route `/system-admin/*` nào tồn tại nên chưa phải regression thật; cần CMS Super Admin/Authorization riêng |
| ~~24~~ | ~~`AuthenticationService` (CMS-031) chưa có rate limiting brute-force~~ | **✅ RESOLVED ở CMS-033** (`v0.0.33`) — `attempt()` gọi `tooManyAttempts()`/`hit()`/`clear()` đúng `config('auth.login_throttle')`, key theo email. Lưu ý phụ phát sinh: `clear()` gắn với `password_verify()` thành công, không gắn kết quả cuối `attempt()` — tài khoản inactive dùng đúng password không bao giờ bị rate-limit (có chủ đích, đã khoá bằng test riêng) |
| ~~25~~ | ~~`AuthenticationService` (CMS-031) chưa có `POST /login`/Controller~~ | **✅ RESOLVED ở CMS-034** (`v0.0.34`) — `modules/Auth/LoginController.php` (Module thật đầu tiên của dự án) gọi `AuthenticationService::attempt()` qua `POST /login`. Phát sinh Technical Debt mới: không CSRF cho `/login` (có chủ đích, xem mục 3.27), không GET login page (chưa có UI Admin Panel) |
| 26 | `AuthenticationService` (CMS-031) giả định 1 session = 1 site (`TenantManager::id()` tại thời điểm login) — user thuộc nhiều site (`user_site_roles`) phải đăng nhập lại khi đổi site, không có cơ chế switch-site giữ nguyên session | Quyết định phạm vi có chủ đích (Owner Decision CMS-031, Q8) — cần CMS riêng nếu có nhu cầu "1 user quản lý nhiều site trong cùng phiên" |

Từ CMS-012, dự án áp dụng quy trình chuẩn hoá 9 bước (Architecture Analysis → Design → Chờ duyệt → Implementation → Self Code Review → Self Architecture Review → Regression Review → Unit Test → Báo cáo) và nguyên tắc kiến trúc: **không tạo interface cho 1 implementation, không tạo abstraction chỉ để DRY, không tối ưu sớm, không sửa code đã ổn định chỉ vì "đẹp hơn"**.

Tài liệu này sẽ cập nhật khi có thay đổi kiến trúc core (yêu cầu xin phép trước theo quy ước dự án).
