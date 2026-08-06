# MODULE: USER & ROLE (RBAC)

## 1. Mục đích

Quản lý người dùng hệ thống và phân quyền theo mô hình RBAC, hỗ trợ 1 user có role khác nhau trên nhiều site (tenant-scoped role).

## 2. Danh sách chức năng

- CRUD User (Admin quản trị site tạo user cho site mình).
- CRUD Role (ví dụ: Admin, Editor, Author, Contributor — có thể tạo Role tuỳ biến).
- Gán Permission cho Role (`post.create`, `post.publish`, `media.upload`, `site.manage_settings`...).
- Gán Role cho User theo từng site (`user_site_roles`).
- Khoá/mở khoá tài khoản user.
- Xem lịch sử hoạt động của user (audit log cơ bản).

## 3. Bảng dữ liệu liên quan

- `users`: id, name, email, password, status, created_at.
- `roles`: id, tenant_id (null nếu là role mặc định hệ thống), name, is_system.
- `permissions`: id, key (`post.create`...), description.
- `role_permissions`: role_id, permission_id.
- `user_site_roles`: user_id, site_id, role_id — **bảng trung tâm cho tenant-scoped role**.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Auth | 1 - 1 | Cung cấp identity đã xác thực để tra quyền |
| Tenant/Site | N - N (qua user_site_roles) | 1 user nhiều site, mỗi site 1 role riêng |
| Tất cả module có Admin Flow | N - N | Mọi hành động ghi/sửa/xoá đều check permission của module đó |

## 5. Data Flow

```
Request Admin (đã qua Session Auth)
  → Middleware Authorize
  → Lấy user_id + site_id hiện tại từ Session
  → Query user_site_roles → xác định role
  → Query role_permissions → danh sách permission
  → So khớp permission yêu cầu của route (khai báo trong routes.php: 'permission' => 'post.create')
  → Cho phép / trả 403
```

## 6. User Flow

Không áp dụng trực tiếp (module thuần Admin/hệ thống). Trường hợp site có thành viên công khai, phần "User tự quản lý" (đổi mật khẩu, thông tin cá nhân) thuộc phạm vi module Auth + 1 phần nhỏ của module này (đọc thông tin `users`).

## 7. Admin Flow

1. Admin site vào "Quản lý người dùng" → thấy danh sách user thuộc site mình (lọc theo `user_site_roles.site_id`).
2. Tạo user mới → nhập email, chọn Role có sẵn (hoặc tạo Role mới trước).
3. Tạo Role mới → đặt tên → tick chọn các Permission từ danh sách có sẵn theo từng module.
4. Gán Role cho user → lưu vào `user_site_roles`.
5. Khoá user → set `status = locked`, user không đăng nhập được (module Auth kiểm tra status này).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/users` | GET | `user.view` | Danh sách user của site hiện tại |
| `/api/v1/users` | POST | `user.create` | Tạo user |
| `/api/v1/users/{id}` | PUT | `user.update` | Cập nhật user |
| `/api/v1/users/{id}` | DELETE | `user.delete` | Xoá (soft-delete) user |
| `/api/v1/roles` | GET/POST | `role.manage` | Quản lý role |
| `/api/v1/roles/{id}/permissions` | PUT | `role.manage` | Gán permission cho role |

## 9. Hook/Event bắn ra

- `user.created`
- `user.updated`
- `user.locked`
- `role.permission_changed`
