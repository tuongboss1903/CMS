# MASTER SPEC — CMS ĐA WEBSITE

> Tài liệu gốc tham chiếu: `cms-architecture-proposal.md` (đã chốt chính thức).
> Tài liệu này là **bản đồ tổng** liên kết tới spec chi tiết từng module.

---

## 1. DANH SÁCH MODULE

| # | Module | File spec | Vai trò |
|---|---|---|---|
| 1 | Tenant/Site | `01-module-tenant.md` | Nền tảng multi-tenant, quản lý danh sách site |
| 2 | Auth | `02-module-auth.md` | Đăng nhập/đăng ký, quản lý phiên |
| 3 | User & Role (RBAC) | `03-module-user-role.md` | Quản lý user, phân quyền |
| 4 | Page | `04-module-page.md` | Trang tĩnh |
| 5 | Blog/Post | `05-module-post.md` | Bài viết, category, tag |
| 6 | Product | `06-module-product.md` | Sản phẩm/dịch vụ |
| 7 | Media | `07-module-media.md` | Thư viện file/ảnh |
| 8 | Menu | `08-module-menu.md` | Trình quản lý menu |
| 9 | Form | `09-module-form.md` | Form builder |
| 10 | SEO | `10-module-seo.md` | Meta, sitemap, schema, redirect |
| 11 | Settings | `11-module-settings.md` | Cấu hình theo từng site |
| 12 | Theme | `12-module-theme.md` | Quản lý theme |
| 13 | Plugin | `13-module-plugin.md` | Quản lý plugin |

---

## 2. QUAN HỆ TỔNG THỂ GIỮA CÁC MODULE

```
                        ┌───────────────┐
                        │  Tenant/Site  │  (gốc — mọi module đều phụ thuộc tenant_id)
                        └───────┬───────┘
                                │
        ┌───────────┬──────────┼──────────┬───────────┬────────────┐
        ▼           ▼          ▼          ▼           ▼            ▼
     ┌──────┐   ┌────────┐ ┌───────┐ ┌────────┐  ┌─────────┐  ┌─────────┐
     │ Auth │◄──┤ User/  │ │ Theme │ │ Plugin │  │Settings │  │  Menu   │
     │      │   │ Role   │ │       │ │        │  │         │  │         │
     └──┬───┘   └───┬────┘ └───┬───┘ └───┬────┘  └────┬────┘  └────┬────┘
        │           │          │         │            │            │
        │           │          └────┬────┘            │            │
        │           │               ▼                 │            │
        │           │         (render output)          │            │
        │           ▼                                  │            │
        │      ┌──────────┐   ┌────────┐   ┌─────────┐ │       ┌────────┐
        └─────►│  Page    │   │  Post  │   │ Product │ │       │ Media  │
               └────┬─────┘   └───┬────┘   └────┬────┘ │       └───┬────┘
                    │             │             │       │           │
                    └──────┬──────┴──────┬──────┘       │           │
                           ▼             ▼               │           │
                        ┌─────┐      ┌──────┐            │           │
                        │ SEO │◄─────┤ Form │◄───────────┴───────────┘
                        └─────┘      └──────┘
```

**Nguyên tắc quan hệ:**

- **Tenant/Site** là module gốc — mọi bản ghi nghiệp vụ (Page, Post, Product, Media, Menu, Form, Settings, Theme active, Plugin active) đều gắn `tenant_id`.
- **Auth** và **User/Role** cung cấp danh tính + quyền cho toàn bộ Admin Flow của các module khác (mọi thao tác ghi/sửa/xoá đều đi qua Middleware `Authorize`).
- **Page / Post / Product** là các module "nội dung" (content-bearing), đều dùng chung: Media (đính kèm ảnh), SEO (meta riêng từng bản ghi), Menu (liên kết tới), Form (nhúng form vào nội dung).
- **Theme** không sở hữu dữ liệu nghiệp vụ — chỉ *đọc* dữ liệu từ Page/Post/Product/Menu để render ra HTML.
- **Plugin** có thể can thiệp (qua Hook) vào bất kỳ module nào ở trên, không có quan hệ dữ liệu cố định.
- **SEO** là module "đọc kèm" (side-car) — gắn với Page/Post/Product qua `entity_type` + `entity_id`, không đứng độc lập.

---

## 3. LUỒNG DỮ LIỆU TỔNG THỂ (DATA FLOW HỆ THỐNG)

### 3.1. Luồng Request công khai (Public/Frontend)

```
Browser
  → public/index.php (entry point)
  → Middleware: TenantResolver (xác định site theo domain)
  → Middleware: Locale
  → Router → Controller (module tương ứng: Page/Post/Product...)
  → Service → Repository → Database (WHERE tenant_id = X) / Cache
  → SEO Service (gắn meta/schema vào response)
  → ThemeManager → View render (theme đang active của site)
  → Hook: "theme.before_render" / "theme.after_render" (cho phép Plugin can thiệp)
  → HTML Response (SSR, đã tối ưu SEO)
```

### 3.2. Luồng Request quản trị (Admin)

```
Browser (Admin Panel)
  → public/index.php
  → Middleware: TenantResolver → Session Auth → Authorize (RBAC)
  → Router (prefix /admin) → Controller
  → Service (business logic + validate) → Repository → Database
  → Hook: "{module}.before_save" / "{module}.after_save"
  → Cache invalidate (theo tag tenant + module)
  → Redirect/Render kết quả (thông báo thành công/lỗi)
```

### 3.3. Luồng Request API (Headless/Đối tác)

```
Client (App/Đối tác)
  → public/index.php
  → Middleware: TenantResolver (theo header hoặc domain) → JWT Auth → Authorize
  → Router (prefix /api/v1) → Controller (API Resource riêng, không lộ Model)
  → Service → Repository → Database/Cache
  → Response JSON chuẩn { success, data, message, errors }
```

---

## 4. USER FLOW TỔNG QUÁT (Khách truy cập site)

1. Khách truy cập domain của site (site nào tuỳ tenant).
2. Hệ thống xác định tenant → load Theme + Settings tương ứng.
3. Khách xem Page/Post/Product → nếu có Form (liên hệ, đăng ký) thì gửi qua module Form.
4. SEO Engine đảm bảo mỗi trang có meta/schema đầy đủ trước khi trả về.
5. (Tuỳ site) Khách có thể cần đăng nhập (module Auth) nếu site có khu vực thành viên.

Chi tiết cụ thể theo từng loại nội dung được mô tả trong spec từng module (`04`–`09`).

---

## 5. ADMIN FLOW TỔNG QUÁT (Người quản trị site)

1. Đăng nhập Admin Panel (module Auth, Session-based).
2. Hệ thống xác định Role + Permission (module User/Role) — chỉ hiển thị menu quản trị tương ứng quyền.
3. Quản trị nội dung: Page/Post/Product, upload Media, sắp xếp Menu.
4. Cấu hình: Theme đang dùng, Plugin bật/tắt, Settings riêng của site, SEO mặc định.
5. Mọi thao tác ghi dữ liệu đều bắn Hook tương ứng để Plugin/Module khác có thể phản ứng (ví dụ: Post publish → SEO tự sinh sitemap lại).

---

## 6. API FLOW TỔNG QUÁT

1. Client lấy JWT token qua `POST /api/v1/auth/login`.
2. Gọi các endpoint theo module, luôn kèm `Authorization: Bearer {token}` (trừ endpoint public như `GET /api/v1/posts`).
3. Response luôn theo chuẩn:
```
{
  "success": true,
  "data": {},
  "message": "",
  "errors": []
}
```
4. Lỗi trả theo HTTP status chuẩn: 400/401/403/404/422/500 (đúng `api-document.md`).

Chi tiết endpoint từng module nằm trong spec riêng (`0X-module-*.md`, mục "API Flow").

---

## 7. QUY ƯỚC CHUNG CHO TOÀN BỘ SPEC MODULE

Mỗi tài liệu module con tuân theo cấu trúc thống nhất:

1. Mục đích module
2. Danh sách chức năng
3. Bảng dữ liệu liên quan (đối chiếu `database.md`)
4. Quan hệ với module khác
5. Data Flow
6. User Flow (nếu có phần công khai)
7. Admin Flow
8. API Flow (endpoint, method, quyền yêu cầu)
9. Hook/Event module này bắn ra (để Plugin khác lắng nghe)
