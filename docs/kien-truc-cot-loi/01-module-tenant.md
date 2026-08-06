# MODULE: TENANT / SITE

## 1. Mục đích

Module gốc của toàn hệ thống multi-tenant. Quản lý danh sách website (tenant) chạy trên cùng 1 mã nguồn, mỗi site có domain, theme, cấu hình riêng.

## 2. Danh sách chức năng

- Tạo/sửa/xoá (soft-delete) site.
- Gán domain/subdomain cho site (hỗ trợ nhiều domain trỏ về 1 site — ví dụ domain chính + domain redirect).
- Bật/tắt site (maintenance mode riêng từng site).
- Gán gói dịch vụ (plan) cho site — nền tảng chuẩn bị cho SaaS (giới hạn số Page/Post/dung lượng Media theo plan).
- Xem thống kê nhanh theo site (số Page, Post, Media đã dùng).
- Nhân bản site (clone site có sẵn làm mẫu — hữu ích khi "kinh doanh nhiều website" từ 1 theme demo).

## 3. Bảng dữ liệu liên quan

- `sites`: id, name, domain, status (active/maintenance/suspended), plan_id, theme_active, created_at.
- `site_domains`: id, site_id, domain, is_primary (hỗ trợ nhiều domain/site).
- `plans` (tương lai SaaS): id, name, limits (json).

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Tất cả module khác | 1 - N | Mọi bảng nghiệp vụ có cột `tenant_id` tham chiếu `sites.id` |
| Theme | 1 - 1 (tại 1 thời điểm) | `sites.theme_active` trỏ tới theme đang dùng |
| Settings | 1 - 1 | Mỗi site có 1 bộ settings riêng |
| User/Role | N - N | 1 user có thể thuộc nhiều site với role khác nhau (`user_site_roles`) |

## 5. Data Flow

```
Request vào → TenantResolver Middleware
  → Đọc domain từ request
  → Query bảng site_domains → xác định site_id
  → Nếu không tìm thấy domain → trả 404 (site not found)
  → Nếu site.status = maintenance → trả trang bảo trì
  → Set tenant hiện tại vào Context (dùng xuyên suốt request: Repository tự động lọc theo tenant_id này)
```

## 6. User Flow

Không có User Flow trực tiếp (khách truy cập không biết khái niệm "tenant" — họ chỉ thấy website bình thường). TenantResolver hoạt động ngầm ở tầng hạ tầng.

## 7. Admin Flow

Vai trò: **Super Admin hệ thống** (quản lý toàn bộ các site, khác với Admin của riêng 1 site).

1. Super Admin đăng nhập khu vực quản trị hệ thống (`/system-admin`, tách biệt Admin Panel của từng site).
2. Tạo site mới → nhập domain, chọn theme mẫu, chọn plan.
3. Hệ thống tự tạo bản ghi `sites` + user Admin đầu tiên cho site đó.
4. Có thể tạm ngưng (`suspended`) site nếu vi phạm/nợ phí (chuẩn bị cho mô hình SaaS thu phí).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/system/sites` | GET | Super Admin | Danh sách site |
| `/api/v1/system/sites` | POST | Super Admin | Tạo site mới |
| `/api/v1/system/sites/{id}` | PUT | Super Admin | Cập nhật site |
| `/api/v1/system/sites/{id}/suspend` | POST | Super Admin | Tạm ngưng site |
| `/api/v1/system/sites/{id}/clone` | POST | Super Admin | Nhân bản site |

> Lưu ý: nhóm endpoint `/system/*` **không** đi qua TenantResolver theo domain thông thường — xác thực bằng quyền Super Admin toàn cục, tách hẳn khỏi API `/api/v1/*` của từng site.

## 9. Hook/Event bắn ra

- `site.created`
- `site.suspended`
- `site.theme_changed`
- `site.domain_added`
