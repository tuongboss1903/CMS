# MODULE: SETTINGS

## 1. Mục đích

Trung tâm cấu hình riêng cho từng site — không hard-code bất kỳ giá trị nào cụ thể theo site vào core, tất cả đọc qua Settings.

## 2. Danh sách chức năng

- Cấu hình chung: tên site, logo, favicon, ngôn ngữ mặc định, múi giờ.
- Cấu hình SMTP gửi email (dùng chung cho module Auth, Form).
- Cấu hình Storage (Local/S3) cho Media.
- Bật/tắt module theo site (Product, Comment trong Post, User tự đăng ký...).
- Cấu hình mạng xã hội (link Facebook, Zalo, hiển thị icon liên hệ nổi).
- Cấu hình Google Analytics/Search Console/Facebook Pixel (nhúng script tracking).
- Cấu hình bảo trì (maintenance mode) riêng theo site.

## 3. Bảng dữ liệu liên quan

- `settings`: id, tenant_id, key, value (json), group (general/mail/storage/modules/tracking).

Thiết kế dạng **key-value theo nhóm** thay vì nhiều cột cố định — dễ mở rộng thêm setting mới mà không cần migration liên tục.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Tenant/Site | 1 - 1 | Mỗi site có đúng 1 bộ settings |
| Auth, Form | N - 1 | Đọc cấu hình SMTP |
| Media | N - 1 | Đọc cấu hình Storage driver |
| Product | N - 1 | Đọc flag bật/tắt module |
| SEO | N - 1 | Đọc meta mặc định, tracking script |

## 5. Data Flow

```
Bất kỳ module nào cần đọc cấu hình
  → SettingsService::get('mail.smtp_host', $tenant_id)
  → Cache-first (settings ít thay đổi, cache lâu, key: settings:{tenant_id}:{group})
  → Cache miss → query bảng settings → cache lại
```

```
Admin cập nhật Settings
  → SettingsService validate theo group (vd SMTP phải test connection trước khi lưu)
  → Repository lưu/update theo key
  → Hook "settings.updated" (kèm group đã đổi)
  → Cache invalidate group tương ứng
```

## 6. User Flow

Không áp dụng (module thuần Admin). Tuy nhiên một số setting ảnh hưởng gián tiếp tới trải nghiệm khách (vd logo, favicon, script tracking hiển thị ở tầng Theme).

## 7. Admin Flow

1. Vào "Cài đặt" → chọn nhóm cần sửa (Chung/Email/Lưu trữ/Module/Tracking).
2. Sửa giá trị → với nhóm nhạy cảm (SMTP) có nút "Test gửi thử" trước khi lưu chính thức.
3. Bật/tắt module → hệ thống ẩn hẳn menu quản trị của module đó nếu tắt (không chỉ ẩn giao diện, còn chặn route ở Middleware).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/settings/{group}` | GET | `settings.view` | Lấy cấu hình theo nhóm |
| `/api/v1/settings/{group}` | PUT | `settings.manage` | Cập nhật cấu hình nhóm |
| `/api/v1/settings/test-mail` | POST | `settings.manage` | Gửi email test SMTP |

## 9. Hook/Event bắn ra

- `settings.updated`
- `settings.module_toggled`
