# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/). Version nội bộ (chưa phát hành ra ngoài) đánh số theo Task hoàn thành: `CMS-00X` → `v0.0.X` — dùng làm mốc tiến độ Phase 1, không phải semver phát hành sản phẩm.

## [Unreleased]

Chưa có mục nào — chờ chỉ định task tiếp theo (CMS-016, HTTP Response Layer, theo roadmap).

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
