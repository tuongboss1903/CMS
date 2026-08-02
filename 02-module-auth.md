# MODULE: AUTH

## 1. Mục đích

Xác thực danh tính cho cả Admin Panel (Session) và API (JWT), theo đúng quyết định kiến trúc chính thức (Hybrid Auth).

## 2. Danh sách chức năng

- Đăng nhập/Đăng xuất (Session cho Admin).
- Đăng nhập lấy Token (JWT cho API).
- Refresh Token (API).
- Quên mật khẩu / Đặt lại mật khẩu (gửi email).
- Xác thực email khi đăng ký (nếu site cho phép user tự đăng ký, ví dụ site có khu vực thành viên).
- Giới hạn số lần đăng nhập sai (rate-limit chống brute-force).
- Ghi log đăng nhập (thiết bị, IP, thời gian) phục vụ bảo mật.

## 3. Bảng dữ liệu liên quan

- `users` (đã có trong `database.md`) — dùng chung với module User/Role.
- `password_resets`: id, user_id, token, expires_at.
- `login_logs`: id, user_id, ip, user_agent, tenant_id, created_at.
- `personal_access_tokens` (JWT refresh tracking): id, user_id, token_hash, expires_at, revoked_at.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| User/Role | 1 - 1 | Auth xác thực identity, User/Role quyết định quyền sau khi xác thực |
| Tenant/Site | N - 1 | Đăng nhập Admin luôn gắn với 1 site cụ thể (qua `user_site_roles`) |
| Settings | 1 - 1 | Site có thể bật/tắt tính năng "cho phép user tự đăng ký" |

## 5. Data Flow

**Session (Admin):**
```
POST /admin/login (email, password)
  → AuthService: verify password (password_hash)
  → Tạo Session, lưu user_id + site_id hiện tại vào Session
  → Ghi login_logs
  → Redirect /admin/dashboard
```

**JWT (API):**
```
POST /api/v1/auth/login (email, password)
  → AuthService: verify password
  → Sinh JWT (payload: user_id, tenant_id, roles, exp)
  → Trả về { access_token, refresh_token }
  → Client gửi kèm "Authorization: Bearer {token}" cho các request sau
```

## 6. User Flow

(Áp dụng khi site có khu vực thành viên công khai)

1. Khách bấm "Đăng ký" → nhập thông tin → nhận email xác thực.
2. Xác thực email → tài khoản active.
3. Đăng nhập → truy cập khu vực thành viên.
4. Quên mật khẩu → nhập email → nhận link đặt lại → đặt mật khẩu mới.

## 7. Admin Flow

1. Truy cập `/admin/login`.
2. Nhập email/mật khẩu → hệ thống tạo Session.
3. Session gắn kèm `site_id` hiện tại đang quản trị (nếu user có quyền trên nhiều site, có màn hình chọn site sau khi đăng nhập).
4. Đăng xuất → huỷ Session.
5. Nếu sai quá N lần → khoá tạm thời tài khoản (thông báo qua email).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/auth/login` | POST | Public | Đăng nhập, trả JWT |
| `/api/v1/auth/refresh` | POST | Bearer (refresh token) | Làm mới access token |
| `/api/v1/auth/logout` | POST | Bearer | Thu hồi refresh token |
| `/api/v1/auth/forgot-password` | POST | Public | Gửi email đặt lại mật khẩu |
| `/api/v1/auth/reset-password` | POST | Public (kèm token) | Đặt mật khẩu mới |
| `/api/v1/auth/me` | GET | Bearer | Thông tin user hiện tại |

## 9. Hook/Event bắn ra

- `auth.login_success`
- `auth.login_failed`
- `auth.logout`
- `auth.password_reset_requested`
- `auth.password_reset_completed`
