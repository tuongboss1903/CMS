# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Khởi tạo cấu trúc thư mục project theo `cms-architecture-proposal.md` mục 2: `app/` (Controllers, Services, Repositories, Models, Helpers, Middleware), `core/`, `modules/`, `plugins/`, `themes/`, `public/` (assets, uploads), `storage/` (cache, logs, framework), `resources/` (scss, js, images), `database/` (migrations, seeds), `config/`, `docs/guideline`.
- `composer.json` với PSR-4 autoload (`Core\` → `core/`, `App\` → `app/`, `Modules\` → `modules/`), yêu cầu PHP >=8.1, chưa thêm dependency nào.
- `.gitignore` (vendor, storage runtime, uploads, .env, node_modules...).
- `TODO.md`, `CHANGELOG.md` khởi tạo để theo dõi tiến độ Phase/Task.

### Known limitations

- Chưa thể chạy `git init` và `composer install` — môi trường thực hiện task hiện không có `git`/`composer`/`php` trên PATH. Cần tự chạy thủ công trước khi tiếp tục các task tiếp theo cần autoload hoạt động thật.
