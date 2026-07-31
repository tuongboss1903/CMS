# MODULE: PLUGIN

## 1. Mục đích

Cho phép mở rộng chức năng CMS mà không sửa core, theo mô hình Hook/Filter + Service Provider đã chốt trong kiến trúc chính thức.

## 2. Danh sách chức năng

- Liệt kê Plugin đã cài (`/plugins`).
- Kích hoạt/Vô hiệu hoá Plugin theo từng site (1 plugin có thể bật ở site A, tắt ở site B).
- Cài đặt Plugin mới (upload file zip hoặc từ kho plugin nội bộ nếu có sau này).
- Xem thông tin Plugin: version, tác giả, mô tả, danh sách hook mà plugin đăng ký.
- Cấu hình riêng cho từng Plugin (mỗi Plugin có thể có màn hình Settings riêng — do Plugin tự khai báo route/view).
- Gỡ cài đặt Plugin (chạy hàm `uninstall()` do Plugin định nghĩa để dọn dữ liệu riêng nếu cần).
- Cách ly lỗi: nếu 1 Plugin lỗi khi load, hệ thống tự vô hiệu hoá Plugin đó và ghi log, không làm crash toàn site (đúng nguyên tắc "Never remove existing functionality" — các module/plugin khác vẫn hoạt động bình thường).

## 3. Bảng dữ liệu liên quan

- `plugins`: id, key (folder name), name, version, description, author.
- `site_plugins`: id, tenant_id, plugin_key, is_active, config (json — cấu hình riêng plugin theo site).
- `plugin_logs`: id, plugin_key, tenant_id, error_message, occurred_at (phục vụ debug khi plugin lỗi).

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Tất cả module khác | N - N (qua Hook) | Plugin không có quan hệ dữ liệu cố định — chỉ can thiệp qua Hook/Filter đã đăng ký |
| Tenant/Site | N - N (qua site_plugins) | 1 plugin bật/tắt độc lập theo từng site |
| Theme | N - N (qua Hook) | Plugin thường chèn nội dung vào vị trí Theme khai báo |

## 5. Data Flow

```
Hệ thống khởi động (bootstrap request)
  → PluginManager: đọc danh sách site_plugins.is_active = true của tenant hiện tại
  → Với mỗi plugin active: load plugin.json → include Hooks.php → đăng ký hook/filter vào Hook Registry
  → Nếu include lỗi (exception) → PluginManager bắt lỗi, ghi plugin_logs, tự set is_active=false, KHÔNG chặn luồng request tiếp theo
```

```
Trong quá trình xử lý (bất kỳ module nào bắn Hook, ví dụ post.after_save)
  → Hook Registry: gọi lần lượt các callback đã đăng ký từ các Plugin đang active, theo thứ tự priority
  → Mỗi callback chạy trong try/catch riêng — 1 plugin lỗi không ảnh hưởng plugin khác
```

## 6. User Flow

Không áp dụng trực tiếp — khách chỉ thấy hiệu ứng gián tiếp (ví dụ Plugin chat live-chat nổi ở góc màn hình, Plugin tính phí ship...).

## 7. Admin Flow

1. Vào "Quản lý Plugin" → xem danh sách đã cài, trạng thái bật/tắt theo site hiện tại.
2. Bật Plugin cho site → PluginManager load ngay (không cần restart server nhờ kiến trúc load theo request).
3. Vào trang cấu hình riêng của Plugin (nếu có) → nhập config → lưu vào `site_plugins.config`.
4. Nếu Plugin bị tự động vô hiệu hoá do lỗi → Admin thấy thông báo kèm log lỗi, có thể xem `plugin_logs` để báo cho nhà phát triển plugin.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/plugins` | GET | `plugin.view` | Danh sách plugin đã cài |
| `/api/v1/plugins/{key}/activate` | POST | `plugin.manage` | Bật plugin cho site hiện tại |
| `/api/v1/plugins/{key}/deactivate` | POST | `plugin.manage` | Tắt plugin |
| `/api/v1/plugins/{key}/config` | GET/PUT | `plugin.manage` | Cấu hình riêng plugin |
| `/api/v1/plugins/{key}/logs` | GET | `plugin.manage` | Xem log lỗi |

> Lưu ý: Plugin **tự có thể khai báo thêm route API riêng** của nó (qua Service Provider), các route này sẽ được `PluginManager` gom vào router chung khi plugin active — không liệt kê hết ở đây vì phụ thuộc từng plugin cụ thể.

## 9. Hook/Event bắn ra

- `plugin.activated`
- `plugin.deactivated`
- `plugin.error` (khi 1 plugin bị tự động vô hiệu hoá do lỗi)

## 10. Quy ước cho người viết Plugin (tham chiếu nhanh)

- Mỗi plugin bắt buộc có `plugin.json` (metadata) + `Hooks.php` (đăng ký hook) + thư mục `src/` (logic riêng, namespace theo `key` plugin).
- Không được truy cập trực tiếp bảng dữ liệu của module lõi — phải qua Service/Repository công khai của module đó (đúng nguyên tắc Repository Pattern trong `coding-standard.md`), tránh phá vỡ tính đóng gói khi core module thay đổi cấu trúc bảng nội bộ.
