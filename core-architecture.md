# CORE ARCHITECTURE — CMS Đa Website

> Trạng thái: **CHÍNH THỨC** — mô tả kiến trúc Core Foundation đã hoàn thành (CMS-001 → CMS-035, tag `v0.0.1` → `v0.0.35`; không có `v0.0.17` — CMS-017 chỉ là Architecture Decision, không phát sinh code; không có `v0.0.32` — nhãn "CMS-032" bị huỷ ngay khi phát hiện trùng lặp phạm vi, công việc dồn thẳng vào CMS-033). Từ CMS-034, `modules/` không còn rỗng — Module thật đầu tiên (`Auth`) đã tồn tại, xem mục 3.27. Tài liệu này tổng hợp lại toàn bộ quyết định thiết kế đã chốt qua các vòng Design Review/Code Review/Architecture Review — dùng làm tài liệu tham chiếu khi viết Module (Phase 3+), không lặp lại chi tiết đã có trong `cms-architecture-proposal.md`/`database-design.md`.
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

**Không xử lý (Owner Decision, để dành CMS riêng)**: `system_admin.domains` bypass (đã có sẵn trong `config/tenants.php`, chưa dùng), site `status` (`suspended`/`maintenance`), domain normalization (lowercase/strip port) — xem Technical Debt #21/#22/#23.

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

## 4. Nguyên tắc áp dụng xuyên suốt (đã enforce qua Code Review từng task)

- **Không static/global mutable state** ở bất kỳ đâu — nguyên tắc bị vi phạm 1 lần duy nhất (bản đầu `Config`) và đã sửa ngay từ CMS-002, không tái diễn.
- **`final class`** cho mọi class core (trừ khi có lý do rõ ràng cần kế thừa) — composition thay vì inheritance.
- **`readonly` property** cho dữ liệu bất biến; các đối tượng Immutable (`Request`) dùng `new self(...)` thay vì `clone` (PHP 8.1 không cho gán lại `readonly` trong `__clone()`).
- **Exception theo từng mối lo cụ thể**, luôn có class base + subclass rõ nghĩa (không dùng `Exception`/`RuntimeException` trần).
- **Hàm built-in PHP có tiền tố `\`** khi gọi trong namespace (tối ưu resolve, tránh IDE hint).
- **Global function/superglobal truy cập bị cô lập vào đúng 1 điểm**: `getenv()` chỉ trong `config/*.php`; `$_SERVER/$_GET/$_POST` chỉ trong `Request::fromGlobals()`; `$_SESSION/session_*()` chỉ trong `Session`.
- **Quy ước không thể enforce bằng type system** (Service Provider `register()` không business logic, escape tường minh trong View, Controller không `echo`/`exit`) được ghi rõ trong docblock — bắt buộc kiểm tra ở Code Review khi module viết Controller/Plugin thật.

## 5. Testing Summary

**403 test, 718 assertion — PASS** (PHP 8.3.30, PHPUnit 10.5.64), Verified PASS thật tính đến CMS-035. Chạy trên SQLite in-memory (Database/View/Router/Migration integration) — không phụ thuộc MySQL thật. 4 test skip có điều kiện (Redis) khi môi trường không có `ext-redis`.

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
| TenantResolverMiddleware | 4 | Integration (`Database` SQLite in-memory thật, seed tay) |
| TenantManager Integration (Application/View) | 3 (+ 5 test cũ được thêm seed) | Integration (`Database` SQLite in-memory thật qua `Application::bootstrap()`) |
| AuthenticationService | 15 (10 CMS-031 + 5 CMS-033 rate limiting) | Integration (`Database` SQLite in-memory thật, seed tay, `Session`/`Auth`/`TenantManager`/`RateLimiter` thật) |
| Auth Module (`modules/Auth/`) | 7 (5 CMS-034 login + 2 CMS-035 logout) | Integration (`ModuleManager` trỏ `modules/` thật, `Router::dispatch()` thật, không fixture) |

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
| 21 | `TenantResolverMiddleware` (CMS-030) chưa kiểm tra `sites.status` (`suspended`/`maintenance`) — site không active vẫn resolve tenant thành công, request vẫn đi tiếp bình thường | Quyết định phạm vi có chủ đích (Owner Decision CMS-030) — cần 1 CMS Authorization site-level riêng khi có route Admin/Super Admin thật |
| 22 | `TenantResolverMiddleware` (CMS-030) không normalize domain (không lowercase, không strip `:port` khỏi `Request::getHost()`) — domain có hoa/thường khác nhau hoặc kèm port sẽ không khớp `site_domains` dù về mặt logic là cùng 1 host | Chưa có bằng chứng nhu cầu thật (chưa có site nào cấu hình domain có port khác 80/443) — cần 1 CMS riêng nếu phát sinh |
| 23 | `system_admin.domains` (đã có sẵn trong `config/tenants.php` từ trước CMS-030) chưa được `TenantResolverMiddleware` xử lý — domain Super Admin thật (nếu có) sẽ bị 404 thay vì bypass | Quyết định phạm vi có chủ đích (Owner Decision CMS-030, Q4) — hiện chưa có route `/system-admin/*` nào tồn tại nên chưa phải regression thật; cần CMS Super Admin/Authorization riêng |
| ~~24~~ | ~~`AuthenticationService` (CMS-031) chưa có rate limiting brute-force~~ | **✅ RESOLVED ở CMS-033** (`v0.0.33`) — `attempt()` gọi `tooManyAttempts()`/`hit()`/`clear()` đúng `config('auth.login_throttle')`, key theo email. Lưu ý phụ phát sinh: `clear()` gắn với `password_verify()` thành công, không gắn kết quả cuối `attempt()` — tài khoản inactive dùng đúng password không bao giờ bị rate-limit (có chủ đích, đã khoá bằng test riêng) |
| ~~25~~ | ~~`AuthenticationService` (CMS-031) chưa có `POST /login`/Controller~~ | **✅ RESOLVED ở CMS-034** (`v0.0.34`) — `modules/Auth/LoginController.php` (Module thật đầu tiên của dự án) gọi `AuthenticationService::attempt()` qua `POST /login`. Phát sinh Technical Debt mới: không CSRF cho `/login` (có chủ đích, xem mục 3.27), không GET login page (chưa có UI Admin Panel) |
| 26 | `AuthenticationService` (CMS-031) giả định 1 session = 1 site (`TenantManager::id()` tại thời điểm login) — user thuộc nhiều site (`user_site_roles`) phải đăng nhập lại khi đổi site, không có cơ chế switch-site giữ nguyên session | Quyết định phạm vi có chủ đích (Owner Decision CMS-031, Q8) — cần CMS riêng nếu có nhu cầu "1 user quản lý nhiều site trong cùng phiên" |

Từ CMS-012, dự án áp dụng quy trình chuẩn hoá 9 bước (Architecture Analysis → Design → Chờ duyệt → Implementation → Self Code Review → Self Architecture Review → Regression Review → Unit Test → Báo cáo) và nguyên tắc kiến trúc: **không tạo interface cho 1 implementation, không tạo abstraction chỉ để DRY, không tối ưu sớm, không sửa code đã ổn định chỉ vì "đẹp hơn"**.

Tài liệu này sẽ cập nhật khi có thay đổi kiến trúc core (yêu cầu xin phép trước theo quy ước dự án).
