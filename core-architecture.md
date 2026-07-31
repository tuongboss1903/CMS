# CORE ARCHITECTURE — CMS Đa Website

> Trạng thái: **CHÍNH THỨC** — mô tả kiến trúc Core Foundation đã hoàn thành (CMS-001 → CMS-015, tag `v0.0.1` → `v0.0.15`). Tài liệu này tổng hợp lại toàn bộ quyết định thiết kế đã chốt qua các vòng Design Review/Code Review/Architecture Review — dùng làm tài liệu tham chiếu khi viết Module (Phase 3+), không lặp lại chi tiết đã có trong `cms-architecture-proposal.md`/`database-design.md`.
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

### 3.6. `Session` (`core/Session.php` + 1 exception) — v0.0.7

Wrapper duy nhất quanh `$_SESSION`/`session_*()`. Chỉ Storage (không login/logout/authorization — thuộc `AuthService` Phase 3). Lazy start. Namespace dot-notation lồng nhau **giống `Config::get()`** (`auth.user_id`, `csrf.token`, `locale.current`, `tenant.current`...). Flash message hết hạn theo tuổi request (2-bucket `_flash_old`/`_flash_new`).

### 3.7. `Cache` (`core/Cache.php` + `core/Cache/*`) — v0.0.8

Facade duy nhất của Cache Layer, qua interface `CacheDriver` (`FileCacheDriver` cho dev/local, `RedisCacheDriver` cho production — dùng `ext-redis`, không composer package). `remember(key, ttl, Closure)` phục vụ Object cache. Tag support (`put(..., tags: [])`/`flushTags()`) đặt ở facade (registry key portable qua mọi driver, không viết riêng cho từng driver). `FileCacheDriver` ghi atomic (file tạm + `rename()`), tên file là `hash(key)`. Tenant key là quy ước đặt tên (`"tenant:{id}:..."`), không phải API riêng — nhất quán `QueryBuilder::forTenant()`.

### 3.8. `Hook` (`core/Hook.php`) — v0.0.9

Hook System kiểu WordPress (Action + Filter) trên 1 registry dùng chung — action/filter chỉ khác cách gọi (`do()` bỏ qua giá trị trả về, `apply()` truyền giá trị qua từng callback), không khác cách đăng ký, đúng cách WordPress triển khai bên trong. Priority mặc định 10 (số nhỏ chạy trước). Wildcard hook (`"post.*"`) trộn đúng thứ tự priority với hook chính xác. Mỗi callback chạy trong `try/catch` riêng (đúng `13-module-plugin.md`), `onError()` là điểm mở cho `PluginManager` (task sau). Không static, không hàm global — 1 instance dùng chung qua `Container` trong 1 request.

### 3.9. `ModuleManager` (`core/ModuleManager.php` + `core/Module/*`) — v0.0.10

Discover module qua `module.json` (glob), resolve thứ tự load bằng topological sort + phát hiện circular dependency (cùng mô hình `Container::resolve()` — stack `resolving`, chặn tại chỗ). `boot(Router, enabledKeys)` nạp `routes.php` của module đã bật vào `Router` qua closure cô lập scope, trả về danh sách key đã nạp (phục vụ log/debug). Không tự query Database để biết module nào "bật" cho tenant nào — nhận `enabledKeys` từ bên ngoài, giữ core trung lập (nhất quán `Database`/`View`/`Cache`).

### 3.10. `Application` (`core/Application.php`) — v0.0.11

Điểm khởi động **duy nhất** của framework — `public/index.php` chỉ còn 3 dòng (`Application::bootstrap(dirname(__DIR__))->run()`). `handle(Request): Response` thuần (test được, không cần superglobal) tách khỏi `run(): void` (I/O boundary duy nhất — `Request::fromGlobals()`/`Response::send()`), cùng triết lý `Router::dispatch()` vs `Response::send()`. `boot()` idempotent — nạp `ModuleManager` (mặc định bật tất cả module đã `discover()` cho tới khi có bảng `site_modules` thật, Phase 2+), đăng ký `/health`. Đăng ký toàn bộ Core Service vào `Container` qua Closure lazy. Bắt `RouteNotFoundException`/`MethodNotAllowedException`/`Throwable`, trả JSON chuẩn `{success,data,message,errors}`; lỗi 500 chỉ lộ message thật khi `config('app.debug')=true`, luôn log vào `storage/logs/app.log` (ghi trực tiếp qua `file_put_contents`, chưa xây `Core\Logger` đầy đủ tính năng — ngoài phạm vi CMS-011). Từ CMS-012, `boot()` cũng nạp `PluginManager` ngay sau `ModuleManager`.

### 3.11. `PluginManager` (`core/PluginManager.php` + `core/Plugin/*`) — v0.0.12

Discover plugin qua `plugin.json` (glob), **memoize kết quả trong instance** (`discover()` chỉ glob + parse 1 lần cho cả vòng đời 1 `PluginManager` — khác chủ đích với `ModuleManager::discover()` không memoize). `resolveLoadOrder()` — topological sort + phát hiện circular dependency, **code độc lập hoàn toàn với `ModuleManager`** (không chia sẻ abstraction dù logic tương tự, chấp nhận trùng lặp có chủ đích để không đụng component đã ổn định, đúng nguyên tắc "không tạo abstraction chỉ để DRY"). `boot(Hook, enabledKeys)` **reset `failures` ở đầu mỗi lần gọi**, nạp `Hooks.php` của từng plugin đã bật theo đúng thứ tự dependency qua closure cô lập scope (chỉ `$hook` khả kiến, cùng kỹ thuật `ModuleManager` dùng cho `routes.php`/`$router`). **Cách ly lỗi tuyệt đối**: chỉ đoạn `require Hooks.php` được try/catch riêng — 1 plugin lỗi được ghi vào `failures[key]` (đọc qua `getFailures()`), không rethrow, không chặn các plugin còn lại nạp tiếp. Lỗi ở tầng `resolveLoadOrder()` (key không tồn tại/dependency chưa bật/circular) vẫn ném ra ngoài `boot()` — coi là lỗi cấu hình, khác bản chất lỗi runtime của 1 plugin. `discover()` phát hiện và ném lỗi tường minh nếu 2 plugin khai trùng `key` (không âm thầm ghi đè).

### 3.12. `MigrationManager` (`core/MigrationManager.php` + `core/Migration/*` + `bin/migrate.php`) — v0.0.13

Quản lý schema database, không chứa business logic, **hoàn toàn tách khỏi HTTP lifecycle** — không có Module/Plugin/Application nào biết tới `MigrationManager`, chạy qua CLI entry point riêng `bin/migrate.php` (tự bootstrap `Config`/`Database` trực tiếp, không qua Container). Migration file trả `['up' => Closure, 'down' => Closure]` (không interface, không class, không DSL). `discover()` glob + sort theo tên file, không memoize. DDL bằng raw SQL qua `Database::statement()` — không Schema Builder/Blueprint. `migrate()`/`rollback()` **fail-fast tuyệt đối** (khác `ModuleManager`/`PluginManager` — không có `getFailures()`, vì các bước thay đổi schema có tính tuần tự/phụ thuộc, cách ly lỗi có thể phá schema). `rollback()` hoàn tác theo batch (`MAX(batch)+1` khi migrate, `ORDER BY id DESC` khi rollback). `driver: string` truyền qua constructor (từ `Config`) — `MigrationManager` không bao giờ đọc PDO/`getAttribute()`, không có API mới nào được thêm vào `Database`.

### 3.13. `Validator` (`core/Validator.php` + `core/Validation/*`) — v0.0.14

Validate `array $data` theo rule string kiểu Laravel (`'required|email|max:255'`), trả `ValidationResult` (không throw cho input sai — chỉ throw `ValidationException` khi rule name không tồn tại, lỗi cấu hình). Registry rule dạng `Closure` là state riêng từng instance, 16 rule built-in đăng ký qua chính `extend()` — built-in và custom dùng chung 1 cơ chế, cho phép ghi đè. Chạy hết toàn bộ rule của 1 field (không bail). **0 dependency vào Core Component khác** — không Container/Application, module tự `new Validator()`.

### 3.14. `Request` — mở rộng ở CMS-015 (v0.0.15)

`core/Http/Request.php` (gốc từ CMS-006, v0.0.6) được mở rộng **additive**: thêm `files/cookies/server` (constructor, cuối cùng, default `[]`), `fromGlobals()` đọc thêm `$_FILES/$_COOKIE/$_SERVER`. Thêm method `method()/uri()/path()` (alias), `all()/has()/filled()`, `cookie()`, `file()` (raw, không abstraction), `ip()` (chỉ `REMOTE_ADDR`, không Trusted Proxy), `userAgent()`, `isMethod()`, `ajax()`, `json()`. Không Method Spoofing. Toàn bộ method cũ (`getMethod/getUri/getHost/query/input/header/routeParam/withRouteParams`) giữ nguyên — 100% backward compatible.

## 4. Nguyên tắc áp dụng xuyên suốt (đã enforce qua Code Review từng task)

- **Không static/global mutable state** ở bất kỳ đâu — nguyên tắc bị vi phạm 1 lần duy nhất (bản đầu `Config`) và đã sửa ngay từ CMS-002, không tái diễn.
- **`final class`** cho mọi class core (trừ khi có lý do rõ ràng cần kế thừa) — composition thay vì inheritance.
- **`readonly` property** cho dữ liệu bất biến; các đối tượng Immutable (`Request`) dùng `new self(...)` thay vì `clone` (PHP 8.1 không cho gán lại `readonly` trong `__clone()`).
- **Exception theo từng mối lo cụ thể**, luôn có class base + subclass rõ nghĩa (không dùng `Exception`/`RuntimeException` trần).
- **Hàm built-in PHP có tiền tố `\`** khi gọi trong namespace (tối ưu resolve, tránh IDE hint).
- **Global function/superglobal truy cập bị cô lập vào đúng 1 điểm**: `getenv()` chỉ trong `config/*.php`; `$_SERVER/$_GET/$_POST` chỉ trong `Request::fromGlobals()`; `$_SESSION/session_*()` chỉ trong `Session`.
- **Quy ước không thể enforce bằng type system** (Service Provider `register()` không business logic, escape tường minh trong View, Controller không `echo`/`exit`) được ghi rõ trong docblock — bắt buộc kiểm tra ở Code Review khi module viết Controller/Plugin thật.

## 5. Testing Summary

**229 test, 382 assertion — 0 Errors/Failures/Warnings/Risky/Deprecations** (PHPUnit 10.5.64, PHP 8.3.30), Verified PASS thật tính đến CMS-015. Chạy trên SQLite in-memory (Database/View/Router integration) — không phụ thuộc MySQL thật. 4 test skip có điều kiện (Redis) khi môi trường không có `ext-redis`.

| Component | Số test | Chiến lược |
|---|---|---|
| Container | 12 | Unit thuần (fixture class nhỏ) |
| Database + QueryBuilder | 31 | Integration (SQLite in-memory) |
| View | 15 + 1 regression | Integration (fixture 2 theme) |
| Router + HTTP (Request/Response) | 24 + 15 (CMS-015) | Integration (fixture Controller/Middleware) + 1 regression toàn chuỗi Container+Database+View |
| Session | 13 | Integration (session thật, mô phỏng nhiều "request" qua `session_write_close()`) |
| Cache | 20 + 1 regression | Integration (filesystem thật, temp dir) + Redis có điều kiện (skip nếu không có `ext-redis`) |
| Hook | 17 + 2 regression | Unit thuần (không I/O) + Regression qua Container |
| ModuleManager | 9 + 1 regression | Integration (fixture module thật) + Regression qua Container+Router |
| Application | 11 | Integration toàn chuỗi (2 fixture app: debug bật/tắt, module thật, filesystem thật) |
| PluginManager | 16 + 2 regression | Integration (fixture plugin thật, quan sát qua `Hook::apply()` filter chain) + Regression qua Container+Hook |
| MigrationManager | 16 (gồm 1 regression) | Integration (SQLite in-memory thật, fixture migration thật, temp dir cho kịch bản batch/rollback nhiều lần chạy) |
| Validator | 31 | Unit thuần (không I/O, 0 dependency vào Core Component khác) |

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

Từ CMS-012, dự án áp dụng quy trình chuẩn hoá 9 bước (Architecture Analysis → Design → Chờ duyệt → Implementation → Self Code Review → Self Architecture Review → Regression Review → Unit Test → Báo cáo) và nguyên tắc kiến trúc: **không tạo interface cho 1 implementation, không tạo abstraction chỉ để DRY, không tối ưu sớm, không sửa code đã ổn định chỉ vì "đẹp hơn"**.

Tài liệu này sẽ cập nhật khi có thay đổi kiến trúc core (yêu cầu xin phép trước theo quy ước dự án).
