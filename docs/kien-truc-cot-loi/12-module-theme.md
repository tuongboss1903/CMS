# MODULE: THEME

## 1. Mục đích

Quản lý giao diện hiển thị của site — cho phép 1 site đổi giao diện mà không ảnh hưởng dữ liệu, và cho phép "kinh doanh nhiều website" bằng cách nhân bản/dùng lại theme có sẵn cho khách hàng mới.

## 2. Danh sách chức năng

- Liệt kê Theme đã cài vào hệ thống (thư mục `/themes`).
- Kích hoạt (activate) 1 Theme cho 1 site.
- Xem trước (preview) Theme trước khi kích hoạt chính thức.
- Tuỳ biến nhẹ (Theme Customizer): đổi màu chủ đạo, font, logo — không cần sửa code, lưu vào Settings riêng theo site (không sửa file theme gốc, tránh ảnh hưởng site khác dùng chung theme).
- Khai báo vùng widget/location cho Menu (mục 8) và khu vực có thể chèn Block tuỳ biến.
- Cập nhật Theme (khi có version mới, không mất tuỳ biến đã lưu vì tuỳ biến tách riêng khỏi file theme).

## 3. Bảng dữ liệu liên quan

- `themes`: id, key (folder name), name, version, screenshot, is_active_default.
- `site_theme_customizations`: id, tenant_id, theme_key, settings (json — màu, font, logo override).

(Bảng `sites.theme_active` đã khai báo ở module Tenant tham chiếu `themes.key`.)

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Tenant/Site | 1 - 1 (tại 1 thời điểm) | Site chỉ active 1 theme |
| Menu | 1 - N | Theme khai báo `location_key` để Menu module gán vào |
| Page/Post/Product | N - 1 (đọc) | Theme chỉ đọc dữ liệu để render, không sở hữu |
| Media | N - 1 | Logo, favicon, ảnh minh hoạ trong Theme Customizer lấy từ Media Library |
| Plugin | N - N (qua Hook) | Plugin có thể chèn thêm nội dung vào vị trí Theme khai báo (`theme.before_render`, hook riêng theo section) |

## 5. Data Flow

```
Site nhận request công khai
  → ThemeManager: đọc sites.theme_active → xác định theme_key
  → Load theme.json (layout mặc định, location khai báo)
  → Load site_theme_customizations (màu/font/logo riêng của site này)
  → View Engine: merge dữ liệu nội dung (Page/Post/Product) + customization → render HTML
  → Hook "theme.before_render" / "theme.after_render" cho Plugin chèn thêm
```

```
Admin đổi Theme
  → ThemeService: kiểm tra theme mới có tương thích (đủ template cần thiết theo cấu hình site đang dùng, ví dụ có Product thì theme phải hỗ trợ template product)
  → Cập nhật sites.theme_active
  → Hook "site.theme_changed"
  → Cache invalidate toàn bộ page cache của site (vì giao diện đổi hoàn toàn)
```

## 6. User Flow

Khách trải nghiệm giao diện theo Theme đang active — không có tương tác trực tiếp với module này, nhưng mọi trang họ xem đều đi qua ThemeManager để render.

## 7. Admin Flow

1. Vào "Giao diện" → xem danh sách Theme đã cài, xem preview.
2. Kích hoạt Theme mới cho site → hệ thống cảnh báo nếu thiếu template cần thiết (vd site có bật module Product nhưng theme không có `product-list.php`).
3. Vào "Tuỳ biến giao diện" (Customizer) → đổi màu/font/logo → xem preview realtime → Lưu.
4. Gán Menu vào từng location theo Theme khai báo (liên kết sang module Menu).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/themes` | GET | `theme.view` | Danh sách theme đã cài |
| `/api/v1/themes/{key}/activate` | POST | `theme.manage` | Kích hoạt theme cho site |
| `/api/v1/themes/customization` | GET/PUT | `theme.manage` | Lấy/Lưu tuỳ biến (màu/font/logo) |

## 9. Hook/Event bắn ra

- `site.theme_changed`
- `theme.customization_updated`

## 10. Hook/Event lắng nghe (dành cho Theme khai thác)

- Theme lắng nghe các hook do Page/Post/Product bắn ra (`post.published`...) nếu cần hiển thị badge "Mới" trên giao diện mà không cần sửa code core.
