# MODULE: MEDIA LIBRARY

## 1. Mục đích

Kho lưu trữ file/ảnh dùng chung cho toàn bộ module nội dung (Page, Post, Product, Theme, Form), tối ưu cho hiệu năng và SEO ảnh.

## 2. Danh sách chức năng

- Upload file (ảnh, pdf, video ngắn).
- Tự động resize sinh nhiều size (thumbnail/medium/large) + convert WebP.
- Gắn Alt text, Title, Caption (bắt buộc Alt cho ảnh — theo `seo-guide.md`).
- Tổ chức theo thư mục ảo (folder) để dễ tìm.
- Tìm kiếm/lọc theo loại file, ngày upload.
- Xoá file (kiểm tra ràng buộc — cảnh báo nếu file đang được dùng ở Page/Post/Product khác trước khi xoá).
- Chọn nguồn lưu trữ (Local/S3) theo cấu hình site — thông qua interface `StorageDriver`.

## 3. Bảng dữ liệu liên quan

- `media`: id, tenant_id, folder_id, file_name, path, mime_type, size, alt_text, title, caption, uploaded_by, created_at.
- `media_folders`: id, tenant_id, parent_id, name.
- `media_variants`: id, media_id, size_type (thumbnail/medium/large/webp), path, width, height.
- `media_usages` (bảng theo dõi nơi sử dụng, phục vụ cảnh báo khi xoá): id, media_id, entity_type, entity_id.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Page/Post/Product | N - N | Nội dung tham chiếu media qua `media_usages` |
| Theme | N - 1 | Ảnh đại diện theme, logo site cũng lưu trong Media |
| Settings | 1 - 1 | Cấu hình `StorageDriver` (local/S3) theo site |

## 5. Data Flow

```
Admin upload file
  → MediaService: validate loại file, dung lượng (theo giới hạn plan của site)
  → StorageDriver: lưu file gốc → path theo /{tenant_id}/{year}/{month}/
  → ImageProcessor: sinh các variant (thumbnail/medium/large/webp) bất đồng bộ (queue nếu có, hoặc đồng bộ giai đoạn đầu)
  → Repository lưu bản ghi media + media_variants
  → Hook "media.uploaded"
```

```
Module khác chèn ảnh (vd Post chọn ảnh đại diện)
  → Ghi vào media_usages (entity_type=post, entity_id, media_id)
  → Khi xoá media → check media_usages trước, cảnh báo Admin nếu đang được dùng
```

## 6. User Flow

Không có User Flow trực tiếp — khách chỉ nhìn thấy ảnh đã qua xử lý (variant phù hợp kích thước hiển thị responsive), không truy cập Media Library.

## 7. Admin Flow

1. Vào "Thư viện Media" → xem lưới ảnh/file theo folder.
2. Upload file mới (kéo-thả hoặc chọn file) → nhập Alt/Title ngay sau upload.
3. Chèn ảnh vào Page/Post/Product qua trình chọn Media (modal picker) từ các module đó.
4. Xoá file không dùng nữa (hệ thống cảnh báo nếu vẫn còn tham chiếu).

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/media` | GET | `media.view` | Danh sách media |
| `/api/v1/media` | POST (multipart) | `media.upload` | Upload file |
| `/api/v1/media/{id}` | PUT | `media.update` | Cập nhật alt/title |
| `/api/v1/media/{id}` | DELETE | `media.delete` | Xoá (check usages trước) |

## 9. Hook/Event bắn ra

- `media.uploaded`
- `media.deleted`
- `media.variant_generated`
