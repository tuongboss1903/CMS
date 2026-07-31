# ĐỀ XUẤT KIẾN TRÚC CMS ĐA WEBSITE (MULTI-SITE → SAAS READY)

> Trạng thái: **Bản đề xuất kiến trúc — chưa code**
> Mục tiêu dự án: 1 mã nguồn → sinh nhiều website, hỗ trợ Theme/Module/Plugin, tối ưu SEO, hiệu năng cao, dễ mở rộng thành SaaS.

---

## 1. KIẾN TRÚC TỔNG THỂ

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Microservices** | Scale độc lập từng service, công nghệ linh hoạt | Quá phức tạp cho giai đoạn đầu, overhead vận hành (network, service discovery), team nhỏ khó maintain |
| **Headless CMS (API-only, FE tách rời)** | FE/BE tách biệt hoàn toàn, dễ tái sử dụng API cho app/mobile | SEO SSR khó hơn (phải thêm SSR layer), tăng độ phức tạp deploy, không tận dụng được sức mạnh WordPress-style theme rendering |
| **Modular Monolith (API-First, render server-side)** | Đơn giản vận hành, dễ maintain, vẫn tách lớp rõ ràng (Controller/Service/Repository), SEO tốt vì SSR trực tiếp, có thể tách dần thành service sau này khi cần | Nếu thiết kế module không chặt, dễ phình to (cần kỷ luật kiến trúc) |

### Khuyến nghị

**Modular Monolith + API-First nội bộ**, lý do:

- Giai đoạn kinh doanh website (bán site cho khách) không cần độ phức tạp của microservices.
- Mỗi module (Blog, Product, Form...) vẫn expose ra REST API riêng (`/api/posts`, `/api/products`...) → sẵn sàng làm headless cho khách hàng cần app mobile sau này, hoặc tách thành service riêng khi 1 module quá tải (ví dụ Media Library tách thành service lưu trữ riêng).
- Multi-tenant ở tầng ứng dụng (1 codebase, nhiều site) — phù hợp mô hình "kinh doanh nhiều website" và dễ nâng cấp thành SaaS (mỗi site = 1 tenant).

### Sơ đồ luồng (đối chiếu `architecture.md` anh đã có)

```
Request
  → Router
  → Middleware (Tenant Resolver, Auth, Locale)
  → Controller
  → Service (business logic)
  → Repository (data access)
  → Database / Cache
  → Response (View render hoặc JSON API)
```

Bổ sung so với bản gốc: thêm **Tenant Resolver** ở tầng Middleware — đây là chìa khóa để 1 mã nguồn chạy nhiều site.

---

## 2. CẤU TRÚC THƯ MỤC

Kế thừa và mở rộng từ `project-structure.md` anh đã định nghĩa, bổ sung phần multi-tenant, module, plugin:

```
/app
  /Controllers
  /Services
  /Repositories
  /Models
  /Helpers
  /Middleware

/core
  Router.php
  Database.php
  View.php
  Cache.php
  Session.php
  Auth.php
  Hook.php            → Event & Hook system
  TenantManager.php    → xác định site hiện tại đang chạy
  ModuleManager.php     → load/enable/disable module
  PluginManager.php     → load/enable/disable plugin
  ThemeManager.php      → load theme + layout

/modules
  Blog/
    Controllers/
    Services/
    Repositories/
    routes.php
    module.json        → khai báo metadata module (tên, version, dependency)
  Page/
  Product/
  Media/
  Menu/
  Form/
  SEO/
  User/
  Settings/

/plugins
  {plugin-name}/
    plugin.json
    Hooks.php
    src/

/themes
  theme-default/
    layouts/
    templates/
    assets/
      scss/
      js/
    theme.json
  theme-business/
  theme-landing/

/public
  index.php            → single entry point
  /assets
  /uploads/{tenant_id}/  → tách theo tenant

/storage
  /cache/{tenant_id}/
  /logs
  /framework

/resources
  scss/
  js/
  images/

/database
  /migrations
  /seeds

/config
  app.php
  database.php
  cache.php
  auth.php
  tenants.php

/docs
  guideline
```

**Điểm khác biệt quan trọng** so với bản gốc anh đưa: tách riêng `/modules` (core business — Blog, Product...) và `/plugins` (extension bên thứ 3 hoặc tùy chọn bật/tắt) — vì 2 khái niệm này có vòng đời và quyền hạn khác nhau (module là lõi hệ thống, plugin là mở rộng có thể gỡ bất kỳ lúc nào).

---

## 3. CÁC MODULE LÕI

| Module | Trách nhiệm |
|---|---|
| **Auth** | Đăng nhập/đăng ký, JWT/Session, quên mật khẩu |
| **User & Role** | Quản lý user, role, permission (RBAC) |
| **Page** | Trang tĩnh (Landing, About, Contact...) |
| **Blog/Post** | Bài viết, category, tag |
| **Product** | Nếu site dạng thương mại/dịch vụ |
| **Media** | Thư viện ảnh/file dùng chung |
| **Menu** | Trình quản lý menu kéo-thả |
| **Form** | Form builder (liên hệ, đăng ký...) |
| **SEO** | Meta, sitemap, schema, redirect |
| **Settings** | Cấu hình chung theo từng site |
| **Theme** | Quản lý theme đang active |
| **Plugin** | Quản lý plugin cài đặt |
| **Tenant/Site** | Quản lý danh sách site, domain, cấu hình riêng từng site (nền tảng cho SaaS) |

Mỗi module tuân thủ chuẩn `coding-standard.md`: đều có Controller → Service → Repository → Validation, tối đa 300 dòng/class, 50 dòng/function.

---

## 4. THEME ENGINE

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Twig (template engine bên thứ 3)** | Cú pháp an toàn, sandbox tốt, cộng đồng lớn, tách bạch logic/view triệt để | Thêm dependency, học cú pháp riêng, chậm hơn PHP thuần một chút |
| **PHP thuần (native View class tự viết)** | Không phụ thuộc thư viện ngoài, linh hoạt tối đa, đúng tinh thần "tự xây CMS" | Dễ bị lẫn logic vào view nếu không kỷ luật, phải tự viết cơ chế escape/security |
| **Blade-like (tự viết engine giả lập cú pháp Blade)** | Cân bằng: cú pháp gọn như Twig/Blade nhưng không phụ thuộc framework | Tốn công xây dựng ban đầu (compiler đơn giản), phải tự maintain |

### Khuyến nghị

**PHP thuần + View class có helper an toàn** (escape mặc định, layout inheritance qua `extends()`/`section()`), vì:

- Đúng định hướng "tự xây CMS", không phụ thuộc Twig.
- `coding-standard.md` đã cấm "Echo HTML in PHP" ở Controller → View class sẽ là nơi duy nhất render HTML, đảm bảo tách bạch.

**Cơ chế Theme Engine đề xuất:**

- Mỗi theme có `theme.json` khai báo: tên, version, layout mặc định, khu vực widget (sidebar, footer).
- Theme kế thừa layout cha (`layouts/master.php`) → template con override từng section (`hero`, `content`, `sidebar`).
- Theme có thể **override từng phần của Module** (ví dụ Blog module render mặc định, nhưng theme có thể cung cấp `templates/blog/single.php` riêng) — cơ chế "template override" giống WordPress nhưng có convention rõ ràng hơn.
- Asset theme biên dịch riêng theo SCSS Guide (FLOCSS) anh đã định nghĩa: `Foundation → Layout → Component → Project → Utility`.

---

## 5. PLUGIN ENGINE

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Hook/Filter (kiểu WordPress: `add_action`, `add_filter`)** | Người ngoài dễ học (đã quen WordPress), linh hoạt cắm vào bất kỳ điểm nào | Dễ lạm dụng gây "hook hell" khó trace luồng chạy, hiệu năng giảm nếu quá nhiều hook |
| **Service Provider (kiểu Laravel: mỗi plugin đăng ký service riêng)** | Rõ ràng về dependency, dễ test, tận dụng DI Container | Cần định nghĩa Container chuẩn, cứng nhắc hơn với plugin muốn can thiệp sâu vào luồng render |
| **Kết hợp: Hook system + Service Provider** | Lấy độ linh hoạt của Hook cho việc can thiệp UI/content, dùng Service Provider cho việc đăng ký logic/service mới | Phải thiết kế rõ ranh giới khi nào dùng cái nào |

### Khuyến nghị

**Kết hợp cả hai**:

- **Hook/Filter system** (mục 14) dùng cho: chèn nội dung vào theme, thay đổi dữ liệu trước khi lưu, mở rộng field.
- **Service Provider** dùng cho: plugin đăng ký route riêng, đăng ký Repository/Service riêng, đăng ký migration riêng.

Mỗi plugin có `plugin.json` khai báo: tên, version, dependency, danh sách hook đăng ký, danh sách route (nếu có) — `PluginManager` sẽ scan và load khi khởi động, tôn trọng nguyên tắc "Never remove existing functionality" nếu 1 plugin bị lỗi (cơ chế try/catch cách ly, không crash toàn site).

---

## 6. ROUTING

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **File-based routing** (mỗi module có `routes.php`) | Rõ ràng, dễ tìm, module tự quản lý route của mình | Cần cơ chế load tổng hợp lúc boot |
| **Annotation-based** (`#[Route('/posts')]` trên method) | Route nằm ngay cạnh Controller, dễ maintain khi code lớn | Cần parser attribute PHP 8, khó grep nhanh toàn bộ route |
| **Config tập trung** (1 file khai báo tất cả route) | Nhìn toàn cảnh route dễ dàng | File phình to khi nhiều module, dễ conflict khi nhiều người sửa cùng lúc |

### Khuyến nghị

**File-based theo module** (`/modules/{Module}/routes.php`), vì mỗi module độc lập, dễ bật/tắt kèm route của nó. Router lõi (`core/Router.php`) sẽ:

- Hỗ trợ route theo domain/tenant (route có thể khác nhau giữa site A và site B nếu cần).
- Group route theo prefix `/api` cho API, còn lại là route render view (SSR).
- Middleware pipeline: `TenantResolver → Auth → Locale → CSRF (POST) → Controller`.

---

## 7. DATABASE (Multi-tenant)

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Database riêng cho mỗi site (DB-per-tenant)** | Cô lập dữ liệu tuyệt đối, dễ backup/restore riêng từng khách | Tốn tài nguyên khi có hàng trăm site, khó query tổng hợp (báo cáo toàn hệ thống), migration phải chạy N lần |
| **1 Database dùng chung, phân biệt bằng `tenant_id`/`site_id`** | Dễ vận hành, 1 lần migration cho tất cả, dễ làm dashboard tổng hợp (đúng tinh thần SaaS sau này) | Phải cẩn thận query luôn kèm điều kiện `tenant_id` (rủi ro lộ dữ liệu chéo site nếu code sai) |
| **Hybrid: DB chung cho hệ thống quản trị (users, billing), DB riêng cho content từng site lớn** | Cân bằng cô lập và vận hành, phù hợp khi có khách VIP cần cô lập riêng | Phức tạp nhất về kiến trúc, cần 2 tầng kết nối DB |

### Khuyến nghị

Giai đoạn đầu: **1 Database dùng chung + cột `tenant_id`** trên toàn bộ bảng nghiệp vụ (`posts`, `pages`, `media`...), bổ sung so với `database.md` anh đưa:

```
sites (tenant)        ← bảng mới, gốc của multi-tenant
users
roles
permissions
posts        (+ tenant_id)
pages        (+ tenant_id)
categories   (+ tenant_id)
tags         (+ tenant_id)
menus        (+ tenant_id)
settings     (+ tenant_id)
themes       (+ tenant_id, theme đang active của site nào)
media        (+ tenant_id)
redirects    (+ tenant_id)
seo          (+ tenant_id)
logs         (+ tenant_id, nullable nếu log hệ thống)
forms        (+ tenant_id)
```

- Bắt buộc mọi Repository kế thừa 1 base Repository có sẵn `scopeTenant()` tự động thêm `WHERE tenant_id = ?` — tránh lỗi quên điều kiện lúc viết code tay.
- Khi số site tăng lớn (traffic cao, cần cô lập), có đường lui: tách site đó sang DB riêng mà không đổi kiến trúc tầng trên (vì đã tách Repository layer).

---

## 8. API

Theo đúng `api-document.md` đã định nghĩa (REST, response chuẩn `{success, data, message, errors}`, JWT Bearer Token).

### Bổ sung đề xuất

| Hạng mục | Đề xuất |
|---|---|
| Versioning | `/api/v1/...` ngay từ đầu, tránh breaking change sau này |
| GraphQL? | Không cần ở giai đoạn đầu — REST đã đủ và đơn giản hơn để maintain; cân nhắc thêm GraphQL sau khi có nhu cầu thực tế từ nhiều client (mobile app, đối tác) |
| Rate limiting | Theo `tenant_id` + theo IP, tránh 1 site bị tấn công ảnh hưởng site khác |
| API cho Headless | Mỗi module tự expose Resource (DTO) chuẩn hóa, không trả thẳng Model ra ngoài (bảo mật + ổn định hợp đồng API) |

---

## 9. CACHE

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **File cache** | Không cần cài thêm service, đơn giản | Chậm hơn, khó scale khi nhiều server (không share cache giữa các instance) |
| **Redis** | Nhanh, hỗ trợ tag/expire linh hoạt, dùng chung được cho session, queue, rate-limit | Cần thêm service, thêm chi phí vận hành |
| **Kết hợp: Redis cho production, File cache cho local/dev** | Linh hoạt theo môi trường | Phải abstraction tốt (interface `CacheDriver`) để dễ đổi |

### Khuyến nghị

**Redis** cho production, thông qua interface `CacheDriver` (đúng tinh thần SOLID - Dependency Inversion) để dễ đổi driver.

**Các tầng cache cần có:**
- **Object cache**: cache kết quả Repository (theo key có `tenant_id`).
- **Page cache**: cache toàn bộ HTML output cho trang không đăng nhập (tăng tốc SEO/Core Web Vitals theo `seo-guide.md`).
- **Fragment cache**: cache từng phần (sidebar, menu) tái sử dụng giữa nhiều trang.
- Cache key luôn có prefix `tenant:{id}:...` để tránh lẫn dữ liệu giữa các site, và tự động invalidate theo tag khi nội dung thay đổi.

---

## 10. AUTHENTICATION

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Session-based (cookie)** | Đơn giản, phù hợp SSR truyền thống | Khó dùng cho API/mobile app thuần túy |
| **JWT thuần** | Stateless, phù hợp API/SaaS, dễ scale nhiều server | Khó revoke token tức thời (cần thêm blacklist), phải tự xử lý refresh token |
| **Hybrid: Session cho khu vực quản trị (SSR), JWT cho API** | Tận dụng ưu điểm cả hai, đúng với mô hình "site SSR + API mở rộng" | Phải maintain 2 luồng auth song song |

### Khuyến nghị

**Hybrid**: Session cho Admin Panel (trải nghiệm mượt, CSRF dễ chống), JWT Bearer Token cho `/api/*` (đúng `api-document.md`). Tương lai SaaS: JWT có thể mở rộng thêm OAuth2 (đăng nhập Google) mà không đổi core.

---

## 11. AUTHORIZATION

**RBAC (Role-Based Access Control)** dựa trên `roles` + `permissions` đã có trong `database.md`:

- `permissions` dạng `module.action` (ví dụ `post.create`, `post.publish`, `site.manage`).
- Middleware `Authorize` check permission trước khi vào Controller.
- Bổ sung khái niệm **Tenant-scoped role**: 1 user có thể là Admin ở site A nhưng chỉ Editor ở site B (quan trọng cho mô hình kinh doanh nhiều site/SaaS sau này) → cần bảng trung gian `user_site_roles` thay vì gán role cố định 1-1 với user.

---

## 12. MEDIA LIBRARY

| Hạng mục | Đề xuất |
|---|---|
| Lưu trữ | Interface `StorageDriver` — local disk cho giai đoạn đầu, dễ đổi sang S3/CDN khi scale |
| Tổ chức file | Theo `/{tenant_id}/{year}/{month}/` tránh 1 thư mục quá nhiều file |
| Tối ưu ảnh | Tự động sinh nhiều size (thumbnail, medium, large) + convert WebP, phục vụ responsive image (đúng `seo-guide.md` mục Lazy Load/Core Web Vitals) |
| Metadata | Alt text bắt buộc (SEO), title, caption |

---

## 13. SEO ENGINE

Đối chiếu đúng `seo-guide.md` anh đã có, thiết kế thành 1 module riêng chịu trách nhiệm:

- **Meta Manager**: Title, Description, Canonical, OpenGraph, Twitter Card — mỗi Page/Post có thể override, nếu không có thì fallback theo rule mặc định của site.
- **Schema/JSON-LD Generator**: sinh tự động theo loại nội dung (Article, Product, LocalBusiness...).
- **Sitemap Generator**: sinh theo tenant, cache lại, tự update khi có nội dung mới (qua Hook `content.published`).
- **Redirect Manager**: bảng `redirects` đã có sẵn trong `database.md`, quản lý 301/302.
- **Breadcrumb**: sinh tự động theo cấu trúc URL/category.
- **Robots.txt** động theo từng site (site nào đang staging thì tự chặn index).

---

## 14. EVENT & HOOK SYSTEM

### Phương án so sánh

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **Action/Filter kiểu WordPress** | Quen thuộc, đơn giản triển khai, đúng định hướng "CMS học từ WordPress" trong `wordpress-guide.md` | Không có type-safety, dễ đặt tên hook trùng lặp nếu không quy ước |
| **Event Dispatcher + Listener (kiểu Symfony/Laravel Event)** | Type-safe (Event là object cụ thể), dễ test, IDE autocomplete tốt | Cú pháp dài dòng hơn 1 chút so với `add_action('name', fn)` |

### Khuyến nghị

Xây **2 lớp hòa hợp**:

1. **Action Hook** (`Hook::action('content.saved', callback)` / `Hook::do('content.saved', $data)`) — dùng cho các điểm mở rộng đơn giản, tần suất cao, mang tinh thần kế thừa WordPress (giữ lại đúng phần "Keep" trong `wordpress-guide.md`: Hook, Filter).
2. **Filter Hook** (`Hook::filter('post.title', callback)`) — cho phép plugin/theme chỉnh sửa dữ liệu trước khi output (ví dụ thêm badge vào title).
3. Phía dưới, cả 2 cơ chế trên đều build trên 1 **Event Dispatcher lõi** (nội bộ dùng Event object), để phần core (Service, Repository) bắn Event chuẩn, còn Hook chỉ là lớp "cú pháp thân thiện" bọc bên ngoài cho plugin/theme dùng — vừa dễ học (giữ đúng tinh thần WordPress), vừa có type-safety ở tầng lõi.

**Quy ước đặt tên hook**: `{module}.{event}` (ví dụ `post.before_save`, `post.after_publish`, `theme.before_render`, `site.created`) — tránh xung đột và dễ tra cứu.

---

## TỔNG KẾT — Bảng quyết định nhanh

| Hạng mục | Quyết định đề xuất |
|---|---|
| Kiến trúc tổng thể | Modular Monolith, API-First nội bộ |
| Multi-tenant | 1 DB chung + `tenant_id`, có đường lui tách DB riêng khi cần |
| Theme Engine | PHP thuần + View class, template override theo module |
| Plugin Engine | Hook/Filter (UI/content) + Service Provider (logic/route) |
| Routing | File-based theo module, hỗ trợ route theo tenant |
| Cache | Redis, đa tầng (Object/Page/Fragment), key theo tenant |
| Auth | Hybrid: Session (Admin) + JWT (API) |
| Authorization | RBAC, tenant-scoped role |
| Event/Hook | Action/Filter (bề mặt) trên nền Event Dispatcher (lõi) |

---

### QUYẾT ĐỊNH CHÍNH THỨC (đã được chủ đầu tư dự án xác nhận)

> Trạng thái tài liệu: **CHÍNH THỨC — mọi tính năng mới phải tuân theo. Thay đổi kiến trúc phải xin phép trước, không tự ý thay đổi.**

| Hạng mục | Quyết định |
|---|---|
| Database | **1 Database dùng chung + `tenant_id`** trên toàn bộ bảng nghiệp vụ. Không dùng DB-per-tenant ở giai đoạn hiện tại. |
| Admin Panel Auth | **Session-based (SSR)**. Không làm SPA/JWT cho Admin. |
| API | **REST** (không dùng GraphQL). Version từ đầu: `/api/v1/...`. |
| Nền tảng code | **Không dùng Laravel/Symfony hay bất kỳ framework nền nào** — CMS tự phát triển từ core riêng (Router, DI Container, ORM/Query Builder, View, Cache... tự viết). |
| Package | **Được phép dùng Composer package độc lập, đơn lẻ** (ví dụ: thư viện xử lý ảnh, thư viện gửi mail, thư viện slugify...) khi thực sự cần thiết — nhưng **không được kéo theo framework nền** (không cài package kiểu `laravel/*`, `symfony/*` full-stack). Mỗi package độc lập khi thêm vào phải báo rõ lý do và phạm vi sử dụng. |

Với nền tảng "tự viết core, không dùng framework nền", các thành phần sau trong `core/` sẽ **tự triển khai từ đầu** (không có sẵn từ framework):

- `Router.php` — tự viết route matching, route group, middleware pipeline.
- `Container.php` — DI Container đơn giản (bind/resolve), phục vụ SOLID/Dependency Injection theo `coding-standard.md`.
- `Database.php` — PDO wrapper + Query Builder tối giản (không dùng Eloquent/Doctrine).
- `View.php` — Template engine PHP thuần (layout inheritance, section, escape mặc định).
- `Session.php`, `Auth.php`, `Cache.php` (driver Redis qua interface `CacheDriver`), `Hook.php`, `TenantManager.php`, `ModuleManager.php`, `PluginManager.php`, `ThemeManager.php`.

Sau khi chốt toàn bộ kiến trúc, thứ tự triển khai code đề xuất: `core` (Container → Router → Database → View → Session/Auth → Cache → Hook) → `database migration` (bảng `sites`, `users`, `roles`, `permissions`...) → `module Auth/User` → `Theme Engine` → các module còn lại.

---

### QUYẾT ĐỊNH CHÍNH THỨC BỔ SUNG — TẦNG DATABASE / BUSINESS LOGIC

> Chi tiết đầy đủ (bảng, cột, FK, Index) nằm trong tài liệu `database-design.md` — mục này chỉ ghi nhận **nguyên tắc kiến trúc** để đồng bộ giữa 2 tài liệu.

| Hạng mục | Quyết định |
|---|---|
| Database Trigger | **Không dùng Trigger cho Business Logic**, ở bất kỳ hình thức nào (ràng buộc bất biến, số liệu tổng hợp, hay safety-net). Toàn bộ logic đặt ở Service Layer. Lý do: business logic phải nằm ở Service Layer; Trigger tăng độ phức tạp, khó debug; khó Unit Test; gây phụ thuộc vào 1 DB Engine cụ thể. |
| Database Transaction | **Bắt buộc** bọc Transaction cho mọi thao tác ghi từ 2 bước/2 bảng trở lên trong cùng 1 Service method (ví dụ: `PageService::setHomepage()`, `MediaService::upload/delete/replace()`, `PostService::publish()`). Sẽ bổ sung vào `coding-standard.md` (mục "Always") ở lần cập nhật kế tiếp. |
| Multi-tenant storage limit (SaaS) | Bổ sung `sites.storage_used_bytes` **ngay từ giai đoạn đầu** (không chờ đến khi làm SaaS mới thêm cột, tránh phải sửa Database sau này). Cập nhật qua `MediaService` trong Transaction, có Job đồng bộ định kỳ dự phòng (thuộc `core/Queue`, đặc tả khi triển khai Queue). |
| Xoá dữ liệu nghiệp vụ quan trọng | Ưu tiên **Soft Delete** hơn Hard Delete với dữ liệu không được phép mất (ví dụ `forms` khi còn `form_submissions`). Áp dụng nguyên tắc: nếu bản ghi cha còn dữ liệu con quan trọng tham chiếu tới, FK dùng `RESTRICT` + Service phải cảnh báo/yêu cầu xác nhận hoặc archive trước khi cho xoá. |

**Việc này ảnh hưởng tới `core/Database.php`**: cần cung cấp API transaction rõ ràng ở tầng Query Builder (`beginTransaction() / commit() / rollback()`, hoặc wrapper dạng `DB::transaction(callable $fn)`) để mọi Service dùng thống nhất — bổ sung vào phạm vi thiết kế `core` khi bước vào viết code.
