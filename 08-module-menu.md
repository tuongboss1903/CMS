# MODULE: MENU

## 1. Mục đích

Quản lý cấu trúc điều hướng (navigation) của site, cho phép Admin tự dựng menu bằng thao tác kéo-thả, không cần sửa code Theme.

## 2. Danh sách chức năng

- Tạo nhiều Menu (Menu chính, Menu footer, Menu mobile...).
- Thêm mục vào Menu từ nhiều nguồn: Page, Post/Category, Product/Category, hoặc Link tuỳ ý (custom URL).
- Sắp xếp thứ tự, phân cấp (menu cha - con, dropdown).
- Gán Menu vào từng vị trí (location) mà Theme khai báo (header, footer, sidebar).
- Mở tab mới (target `_blank`) cho từng mục.

## 3. Bảng dữ liệu liên quan

- `menus`: id, tenant_id, name, location_key.
- `menu_items`: id, menu_id, parent_id, label, type (page/post_category/product_category/custom), reference_id (nullable), url (nullable nếu custom), target, sort_order.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Page | N - 1 | Mục Menu có thể trỏ tới 1 Page |
| Post (Category) | N - 1 | Mục Menu có thể trỏ tới 1 Category bài viết |
| Product (Category) | N - 1 | Tương tự, trỏ tới category sản phẩm |
| Theme | N - 1 | Theme khai báo các `location_key` hợp lệ (header/footer...) |

## 5. Data Flow

```
Admin sắp xếp Menu (kéo-thả)
  → MenuService: nhận danh sách item mới kèm sort_order + parent_id
  → Repository update hàng loạt (transaction)
  → Hook "menu.updated"
  → Cache invalidate: menu:{tenant_id}:{location_key}
```

```
Theme render Menu tại vị trí "header"
  → MenuService: lấy menu theo location_key = header (cache hit nếu có)
  → Với mỗi item, resolve URL thực tế (nếu type=page → lấy slug hiện tại của Page, tránh URL chết khi Page đổi slug)
  → Trả cấu trúc cây (tree) cho View render
```

## 6. User Flow

1. Khách nhìn thấy Menu điều hướng ở header/footer site.
2. Bấm vào mục Menu → điều hướng tới Page/danh sách Category tương ứng hoặc URL ngoài.
3. Menu responsive (mobile: dạng hamburger) — xử lý ở tầng Theme/JS, dữ liệu vẫn lấy từ module này.

## 7. Admin Flow

1. Vào "Quản lý Menu" → chọn vị trí (Header/Footer...).
2. Kéo-thả thêm mục: chọn nguồn (Page có sẵn / Category / Link tuỳ ý).
3. Sắp xếp thứ tự, tạo dropdown bằng cách kéo mục vào làm con của mục khác.
4. Lưu → hệ thống invalidate cache menu ngay lập tức.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/menus/{location}` | GET | Public | Lấy cấu trúc menu theo vị trí (phục vụ headless) |
| `/api/v1/menus/{id}/items` | PUT | `menu.manage` | Cập nhật toàn bộ cấu trúc (thứ tự, cha-con) |

## 9. Hook/Event bắn ra

- `menu.updated`
- `menu.item_added`
- `menu.item_removed`
