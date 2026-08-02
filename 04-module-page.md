# MODULE: PAGE

## 1. Mục đích

Quản lý các trang tĩnh của site (Trang chủ, Giới thiệu, Liên hệ, Chính sách...), nội dung ít thay đổi theo thời gian, không phân loại theo category/tag như Post.

## 2. Danh sách chức năng

- CRUD Page.
- Soạn nội dung dạng Block/Section (không chỉ 1 ô textarea — hỗ trợ nhiều block: Text, Image, Gallery, CTA... để linh hoạt dựng landing page).
- Chọn Template hiển thị riêng (theme có thể cung cấp nhiều layout cho Page: full-width, sidebar, landing).
- Đặt Page làm Trang chủ.
- Xuất bản / Lưu nháp / Lên lịch xuất bản (schedule publish).
- Phân cấp Page (Page cha - Page con, phục vụ breadcrumb & URL dạng `/gioi-thieu/doi-ngu`).

## 3. Bảng dữ liệu liên quan

- `pages`: id, tenant_id, parent_id, title, slug, content (json - block-based), template, status (draft/published/scheduled), published_at, created_by.
- `page_blocks` (nếu tách block ra bảng riêng thay vì lưu json): id, page_id, type, data (json), sort_order.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Media | N - N | Page có thể chèn nhiều ảnh/file từ Media Library |
| SEO | 1 - 1 | Mỗi Page có 1 bản ghi SEO riêng (qua `entity_type=page, entity_id`) |
| Menu | 1 - N | Page có thể được thêm làm mục Menu |
| Theme | N - 1 | Page chọn Template do Theme đang active cung cấp |
| User/Role | N - 1 | `created_by` tham chiếu user, quyền `page.create/edit/publish` |

## 5. Data Flow

```
Admin lưu Page
  → PageService validate (title, slug unique theo tenant)
  → Repository lưu vào bảng pages (kèm tenant_id)
  → Hook "page.before_save" → "page.after_save"
  → SEO Service: tạo/cập nhật bản ghi SEO mặc định nếu chưa có
  → Cache invalidate: page:{tenant_id}:{slug}
```

```
Khách xem Page
  → Router match slug → PageController
  → Cache hit? → trả HTML cache
  → Cache miss → PageService lấy dữ liệu → ThemeManager render theo template → lưu cache → trả HTML
```

## 6. User Flow

1. Khách vào URL của site → nếu là Trang chủ, hệ thống tự load Page được đánh dấu `is_homepage`.
2. Khách điều hướng qua Menu tới các Page khác (Giới thiệu, Liên hệ...).
3. Xem nội dung Page (các block hiển thị theo thứ tự `sort_order`).

## 7. Admin Flow

1. Vào "Quản lý Page" → danh sách Page (lọc theo status).
2. Tạo Page mới → nhập title (tự sinh slug) → chọn Template → thêm các block nội dung.
3. Lưu nháp hoặc Xuất bản ngay hoặc đặt lịch (`scheduled_at`).
4. Đặt Page làm Trang chủ (chỉ 1 Page được đánh dấu tại 1 thời điểm).
5. Cấu hình SEO riêng cho Page (chuyển sang module SEO, cùng màn hình dạng tab).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/pages` | GET | Public | Danh sách Page đã publish |
| `/api/v1/pages/{slug}` | GET | Public | Chi tiết 1 Page |
| `/api/v1/pages` | POST | `page.create` | Tạo Page (Admin/API) |
| `/api/v1/pages/{id}` | PUT | `page.update` | Cập nhật |
| `/api/v1/pages/{id}` | DELETE | `page.delete` | Xoá |
| `/api/v1/pages/{id}/publish` | POST | `page.publish` | Xuất bản |

## 9. Hook/Event bắn ra

- `page.before_save` / `page.after_save`
- `page.published`
- `page.deleted`
- `page.homepage_changed`
