# CLAUDE.md

File này hướng dẫn Claude Code (claude.ai/code) khi làm việc trong repo này.

## Ngôn ngữ giao tiếp

- **Luôn luôn phản hồi, giải thích và trao đổi 100% bằng TIẾNG VIỆT.** Chỉ giữ nguyên tiếng Anh cho: tên biến/hàm/class/file, câu lệnh CLI, thuật ngữ kỹ thuật không có bản dịch chuẩn (middleware, hook, driver...), và bản thân đoạn code.
- Trả lời ngắn gọn, súc tích, đi thẳng vào vấn đề — không mở đầu/kết thúc rườm rà, không lặp lại những gì đã hiển thị trong code/diff.

## Dự án

CMS đa website (multi-tenant, SaaS-ready) — Core tự viết hoàn toàn bằng PHP, **không dùng framework nền** (không Laravel/Symfony). Yêu cầu PHP >=8.1, phát triển/CI trên 8.2/8.3.

Cấu trúc thư mục quan trọng:

- `core/` — Core Framework tự viết (namespace `Core\`): Router, Container (PSR-11), Database (PDO wrapper), View (theme engine PHP thuần), Session, Cache, Hook (Action/Filter kiểu WordPress), Auth/Authorization, TenantManager...
- `modules/{Ten}/` — Module nghiệp vụ cố định (namespace `Modules\Ten`), tự khai báo `module.json` + `routes.php`, luôn được `ModuleManager` boot cho mọi tenant.
- `plugins/{Ten}/` — Plugin mở rộng (namespace `Plugins\Ten`), tự khai báo `plugin.json` + `Hooks.php`, bật/tắt được theo từng tenant qua `PluginActivationService`/bảng `site_plugins`.
- `themes/{ten_theme}/views/` — View PHP thuần (`View::resolvePath()` chỉ chấp nhận tên file dạng `[a-zA-Z0-9_]` + dấu chấm, **không chấp nhận dấu gạch ngang**).
- `config/` — file cấu hình PHP thuần (không dùng `vlucas/phpdotenv`, biến môi trường nạp qua parser tối giản tự viết `bin/load_env.php`, xem `.env.example`).
- `database/migrations/` — migration dạng `return ['up' => Closure, 'down' => Closure]`, chạy qua `MigrationManager`/`bin/migrate.php`.
- `tests/` — PHPUnit, namespace `Tests\`, testsuite "Core" khai báo trong `phpunit.xml`.
- `core-architecture.md` — tài liệu kiến trúc kỹ thuật đầy đủ, **đọc trước khi sửa code Core**.

## Lệnh CLI quan trọng

- Cài dependency: `composer install`
- Chạy toàn bộ test: `vendor/bin/phpunit` (không có composer script bọc sẵn, gọi trực tiếp). Chạy 1 file: `vendor/bin/phpunit tests/Core/TenFile.php`.
- Regenerate autoload sau khi thêm/đổi tên Module/Plugin (bắt buộc, xem mục Gotchas): `composer dump-autoload -o`
- Kiểm tra PSR-12/style (có cấu hình `.php-cs-fixer.php`): xem trạng thái `vendor/bin/php-cs-fixer fix --dry-run --diff`, sửa thật `vendor/bin/php-cs-fixer fix`.
- Static analysis (PHPStan level 5, cấu hình `phpstan.neon`, bắt buộc trong CI): `vendor/bin/phpstan analyse --no-progress`.
- Kiểm tra lỗi cú pháp nhanh 1 file: `php -l duong/dan/file.php`
- Khởi tạo site/tenant đầu tiên (chỉ chạy được 1 lần, chặn khi bảng `users` đã có dữ liệu): `php bin/bootstrap.php <site_name> <domain> <admin_name> <admin_email> <admin_password>`
- Chạy migration: `php bin/migrate.php`

## Kiến trúc tóm tắt

- Điểm khởi động: `Application::bootstrap()->run()`; `public/index.php` chỉ có 3 dòng. `boot()` idempotent.
- Luồng request: `Request → Router → MiddlewarePipeline (Onion: Global→Group→Route→Controller) → ControllerResolver → Controller → Response`.
- Container: PSR-11, chỉ auto-wire qua Constructor Injection, 1 Container/request.
- Database: PDO wrapper (mysql/sqlite), prepared statement tuyệt đối, whitelist identifier, có `forTenant()` cho query theo tenant.
- View: theme engine PHP thuần (không compiler), resolve `themes/{active}/views` → `themes/{default}/views`, **không tự động escape** — phải tự gọi `$this->e()`/`$this->escape()` trong template.
- Hook: hệ thống Action/Filter kiểu WordPress, có priority/wildcard, cô lập lỗi từng callback (try/catch riêng).
- Controller theo mô hình 1-class-1-action (vd `PageCreateController`, `PageListController`), **không** dùng resource controller gộp nhiều action — giữ đúng quy ước này khi tạo Controller mới.
- Plugin đăng ký route qua hook `plugin.routes.register` (khác Module — nạp `routes.php` trực tiếp lúc boot); bật/tắt Plugin theo tenant được enforce ở dispatch-time qua middleware guard riêng của từng Plugin, **không** ở boot-time (lúc boot tenant chưa được resolve).
- Multi-tenancy: `TenantResolverMiddleware` resolve domain → tenant, fail-closed (404/403/503) nếu site không hợp lệ/bị khoá. Tenant hiện tại lưu trong `TenantManager`, cố tình không lưu trong Session.

## Lưu ý dễ mắc lỗi (Gotchas)

- **Tên thư mục Module/Plugin phải khớp CHÍNH XÁC case với namespace** (vd `plugins/Ecommerce/` cho `Plugins\Ecommerce`). Máy Windows không phân biệt hoa/thường nên lỗi này **không lộ ra khi dev local** — chỉ lộ trên Linux (production/CI) hoặc khi chạy `composer dump-autoload -o`. Luôn kiểm tra lại case khi thêm/đổi tên Module/Plugin.
- Route đăng ký qua Hook `plugin.routes.register` phải nằm trong đúng group Middleware (`StartSessionMiddleware`/`TenantResolverMiddleware`) như route Module — nếu không Session/Tenant sẽ không được resolve cho route đó (đã từng xảy ra thật, xem `core/Application.php`).

## Quy trình làm việc & Git

- **Trước khi sửa code trên diện rộng** (nhiều file, thay đổi kiến trúc, hoặc chạm Core Component): tóm tắt ngắn gọn giải pháp bằng tiếng Việt trước, chờ xác nhận rồi mới viết code.
- Sau khi tạo/sửa file `.php`: chạy `php -l` để bắt lỗi cú pháp; chạy `vendor/bin/php-cs-fixer fix --dry-run --diff` để rà PSR-12 (sửa bằng `fix` nếu lệch) và `vendor/bin/phpstan analyse --no-progress` để rà type — cả hai đều chặn CI.
- Nhánh (branch): `feature/CMS-XXX-<mo-ta-ngan>` (số ticket lấy theo tracker nội bộ, dùng gạch ngang, chữ thường).
- Commit/PR: tiền tố bằng mã ticket, dạng `CMS-XXX: <Mo ta> (PHASE N)` (vd `CMS-055: UI/UX Admin Dashboard Overhaul & Theme Engine Enhancement (PHASE 18)`). Một số commit rất sớm trong lịch sử dùng Conventional Commits (`feat:`, `docs:`...) — không theo mẫu đó nữa, dùng đúng mẫu `CMS-XXX: ...` ở trên cho code mới.
- Không tự ý `git commit`/`git push`/tạo tag/merge — chỉ thực hiện khi được yêu cầu rõ ràng.

Chi tiết kiến trúc sâu hơn xem `core-architecture.md`.
