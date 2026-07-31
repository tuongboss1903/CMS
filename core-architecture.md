# CORE ARCHITECTURE — CMS Đa Website

> Trạng thái: **CHÍNH THỨC** — mô tả kiến trúc Core Foundation đã hoàn thành (CMS-001 → CMS-009, tag `v0.0.1` → `v0.0.9`). Tài liệu này tổng hợp lại toàn bộ quyết định thiết kế đã chốt qua các vòng Design Review/Code Review/Architecture Review — dùng làm tài liệu tham chiếu khi viết Module (Phase 3+), không lặp lại chi tiết đã có trong `cms-architecture-proposal.md`/`database-design.md`.

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

## 4. Nguyên tắc áp dụng xuyên suốt (đã enforce qua Code Review từng task)

- **Không static/global mutable state** ở bất kỳ đâu — nguyên tắc bị vi phạm 1 lần duy nhất (bản đầu `Config`) và đã sửa ngay từ CMS-002, không tái diễn.
- **`final class`** cho mọi class core (trừ khi có lý do rõ ràng cần kế thừa) — composition thay vì inheritance.
- **`readonly` property** cho dữ liệu bất biến; các đối tượng Immutable (`Request`) dùng `new self(...)` thay vì `clone` (PHP 8.1 không cho gán lại `readonly` trong `__clone()`).
- **Exception theo từng mối lo cụ thể**, luôn có class base + subclass rõ nghĩa (không dùng `Exception`/`RuntimeException` trần).
- **Hàm built-in PHP có tiền tố `\`** khi gọi trong namespace (tối ưu resolve, tránh IDE hint).
- **Global function/superglobal truy cập bị cô lập vào đúng 1 điểm**: `getenv()` chỉ trong `config/*.php`; `$_SERVER/$_GET/$_POST` chỉ trong `Request::fromGlobals()`; `$_SESSION/session_*()` chỉ trong `Session`.
- **Quy ước không thể enforce bằng type system** (Service Provider `register()` không business logic, escape tường minh trong View, Controller không `echo`/`exit`) được ghi rõ trong docblock — bắt buộc kiểm tra ở Code Review khi module viết Controller/Plugin thật.

## 5. Testing Summary

**115 test, 170 assertion** đã Verified PASS thật (PHPUnit 10.5.64, PHP 8.3.30) tính đến CMS-008; **CMS-009 (Hook, 19 test) đã viết, đang chờ chạy PHPUnit thật xác nhận**. Chạy trên SQLite in-memory (Database/View/Router integration) — không phụ thuộc MySQL thật.

| Component | Số test | Chiến lược |
|---|---|---|
| Container | 12 | Unit thuần (fixture class nhỏ) |
| Database + QueryBuilder | 31 | Integration (SQLite in-memory) |
| View | 15 + 1 regression | Integration (fixture 2 theme) |
| Router + HTTP | 24 | Integration (fixture Controller/Middleware) + 1 regression toàn chuỗi Container+Database+View |
| Session | 13 | Integration (session thật, mô phỏng nhiều "request" qua `session_write_close()`) |
| Cache | 20 + 1 regression | Integration (filesystem thật, temp dir) + Redis có điều kiện (skip nếu không có `ext-redis`) |
| Hook | 17 + 2 regression | Unit thuần (không I/O) + Regression qua Container |

## 6. Quyết định còn mở (chưa chặn, cần chốt trước Phase 3)

| # | Vấn đề | Trạng thái |
|---|---|---|
| 1 | `.env` loader chưa quyết định | Theo dõi từ CMS-002 |
| 2 | `Database`/`View`/`Session` là `final`, chưa có interface | Cần chốt trước module Auth/User viết Repository — thêm sau sẽ tốn kém hơn |
| 3 | `join()` chưa hỗ trợ `table.column` (chỉ tên cột trần) | Chưa cần, module nào cần join thật sẽ mở rộng |
| 4 | `Response::json()` chưa tự bọc `{success, data, message, errors}` | Cân nhắc `Response::apiSuccess()/apiError()` khi module API đầu tiên viết |
| 5 | `Router::dispatch()` ném exception (không trả `Response`) cho 404/405 | CMS-011/012 phải bắt và map |
| 6 | `Config::get()`/`Session::get()` trùng logic duyệt dot-notation | Có thể tách `Core\Support\DotArray` dùng chung, chưa tự sửa vì chạm 2 component đã tag version |
| 7 | `FileCacheDriver` không dọn định kỳ entry hết hạn chưa được đọc lại (lazy expiry, không sweep nền); registry tag không có TTL riêng | Chấp nhận được ở giai đoạn này, cần cron dọn định kỳ khi lên production thật |

Tài liệu này sẽ cập nhật khi có thay đổi kiến trúc core (yêu cầu xin phép trước theo quy ước dự án).
