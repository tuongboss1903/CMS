# MODULE: PRODUCT

## 1. Mục đích

Quản lý sản phẩm/dịch vụ hiển thị trên site (phù hợp site dạng doanh nghiệp/dịch vụ/bất động sản — tương tự các dự án mẫu trong CV đã upload trước đó: bất động sản, nha khoa, nhà thầu xây dựng). Module này **tuỳ chọn kích hoạt theo site** (không phải site nào cũng cần).

## 2. Danh sách chức năng

- CRUD Product/Service.
- Phân loại theo Category sản phẩm (riêng, khác Category của Post).
- Thuộc tính tuỳ biến (custom fields) — ví dụ bất động sản cần "diện tích, giá, số phòng", nha khoa cần "thời gian điều trị"... → dùng cơ chế field linh hoạt (EAV nhẹ hoặc JSON schema field theo từng site).
- Gallery ảnh sản phẩm (nhiều ảnh/1 sản phẩm).
- Giá + đơn vị (có thể ẩn giá, hiển thị "Liên hệ").
- Trạng thái (đang bán/hết hàng/ngừng kinh doanh).
- Liên kết tới Form (form yêu cầu báo giá/đặt lịch riêng theo sản phẩm).

## 3. Bảng dữ liệu liên quan

- `products`: id, tenant_id, category_id, name, slug, description, price, status, created_by.
- `product_categories`: id, tenant_id, parent_id, name, slug.
- `product_images`: id, product_id, media_id, sort_order.
- `product_fields` (custom field theo site): id, tenant_id, key, label, type (text/number/select).
- `product_field_values`: product_id, field_id, value.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Media | N - N | Gallery ảnh sản phẩm |
| SEO | 1 - 1 | Meta riêng từng sản phẩm |
| Form | N - 1 | Form yêu cầu báo giá gắn với sản phẩm |
| Settings | 1 - 1 | Bật/tắt module Product theo site (site không cần thì tắt hẳn) |

## 5. Data Flow

```
Admin tạo Product
  → ProductService validate (custom field theo product_fields của site)
  → Repository lưu products + product_field_values + product_images
  → Hook "product.after_save"
  → Cache invalidate danh sách sản phẩm theo category
```

```
Khách xem danh sách sản phẩm
  → ProductController → filter theo category/custom field (vd. khoảng giá, diện tích)
  → Cache theo key sản phẩm + filter
  → Render Theme (template product-list / product-detail)
```

## 6. User Flow

1. Khách vào trang danh sách sản phẩm/dịch vụ → lọc theo category hoặc thuộc tính (giá, khu vực...).
2. Xem chi tiết sản phẩm → xem gallery ảnh.
3. Gửi Form yêu cầu báo giá/liên hệ ngay tại trang sản phẩm.

## 7. Admin Flow

1. Bật module Product cho site (nếu site cần — qua Settings/ModuleManager).
2. Định nghĩa Custom Field cần thiết cho loại hình kinh doanh của site (1 lần/site).
3. Tạo sản phẩm → điền field, upload gallery, gắn Form báo giá.
4. Quản lý trạng thái tồn kho/kinh doanh.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/products` | GET | Public | Danh sách (filter theo field) |
| `/api/v1/products/{slug}` | GET | Public | Chi tiết |
| `/api/v1/products` | POST | `product.create` | Tạo sản phẩm |
| `/api/v1/products/{id}` | PUT/DELETE | `product.update/delete` | Sửa/Xoá |
| `/api/v1/product-fields` | GET/POST | `product.manage_field` | Quản lý custom field |

## 9. Hook/Event bắn ra

- `product.after_save`
- `product.deleted`
- `product.stock_changed`
