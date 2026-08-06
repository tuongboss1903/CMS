# MODULE: SEO ENGINE

## 1. Mục đích

Đảm bảo mọi trang xuất ra đều đạt chuẩn SEO theo `seo-guide.md` (Title, Description, Canonical, OpenGraph, Schema, Sitemap, Robots...). Đây là module "side-car" — không có nội dung riêng, chỉ gắn kèm Page/Post/Product.

## 2. Danh sách chức năng

- Cấu hình Meta riêng cho từng Page/Post/Product (override mặc định).
- Cấu hình Meta mặc định theo site (fallback khi nội dung không tự đặt).
- Sinh Schema/JSON-LD tự động theo loại nội dung (Article cho Post, Product cho Product, LocalBusiness cho trang liên hệ...).
- Sinh Sitemap.xml tự động, cập nhật khi nội dung publish/unpublish.
- Quản lý Redirect (301/302) — tránh lỗi 404 khi đổi slug.
- Sinh Robots.txt động theo trạng thái site (site đang staging → tự chặn index).
- Breadcrumb tự động theo cấu trúc URL/category.
- Kiểm tra nhanh (SEO Score) cho từng nội dung: độ dài title/description, có Alt ảnh chưa, có internal link chưa.

## 3. Bảng dữ liệu liên quan

- `seo_meta`: id, tenant_id, entity_type (page/post/product), entity_id, title, description, canonical, og_image_id, schema_type, schema_data (json).
- `redirects`: id, tenant_id, from_path, to_path, status_code (301/302).
- `sitemap_cache`: id, tenant_id, xml_content, generated_at.

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Page/Post/Product | 1 - 1 | Mỗi nội dung có 1 bản ghi `seo_meta` riêng |
| Media | N - 1 | `og_image_id` lấy từ Media Library |
| Settings | 1 - 1 | Meta mặc định toàn site (site name, default OG image) |

## 5. Data Flow

```
Khi Page/Post/Product được publish (lắng nghe Hook "{module}.published")
  → SEOService: kiểm tra đã có seo_meta chưa, nếu chưa → tự sinh mặc định (title = tên nội dung, description = excerpt)
  → SitemapService: thêm/cập nhật URL vào sitemap_cache
  → Redirect check: nếu nội dung đổi slug → tự động tạo bản ghi redirects từ slug cũ sang slug mới (tránh 404)
```

```
Request công khai vào 1 trang bất kỳ
  → SEOService: lấy seo_meta theo entity_type + entity_id (hoặc fallback default site)
  → Inject vào <head>: title, meta description, canonical, OG tags, schema JSON-LD
  → BreadcrumbService: sinh breadcrumb theo cấu trúc URL
```

## 6. User Flow

Không có thao tác trực tiếp — SEO là lớp "vô hình" cải thiện trải nghiệm tìm kiếm (Google) và chia sẻ mạng xã hội (OG tags) cho khách truy cập từ bên ngoài.

## 7. Admin Flow

1. Khi soạn Page/Post/Product, có tab "SEO" ngay trong màn hình soạn → nhập Title/Description riêng (hoặc để trống dùng mặc định).
2. Xem "SEO Score" gợi ý cải thiện (cảnh báo nếu thiếu Alt ảnh, description quá ngắn/dài).
3. Vào "Quản lý Redirect" → thêm/sửa/xoá redirect thủ công nếu cần.
4. Cấu hình Meta mặc định toàn site + bật/tắt index (staging mode) trong Settings SEO.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/sitemap.xml` | GET | Public | Sitemap của site hiện tại (theo domain) |
| `/robots.txt` | GET | Public | Robots động theo site |
| `/api/v1/seo/{entity_type}/{entity_id}` | GET/PUT | `seo.manage` | Lấy/Cập nhật meta cho 1 nội dung |
| `/api/v1/redirects` | GET/POST | `seo.manage_redirect` | Quản lý redirect |

## 9. Hook/Event bắn ra

- `seo.meta_updated`
- `seo.sitemap_regenerated`
- `seo.redirect_created`

## 10. Hook/Event lắng nghe từ module khác

- `page.published`, `post.published`, `product.after_save` → trigger cập nhật sitemap + tạo meta mặc định.
- `page.deleted`, `post.deleted` → gỡ khỏi sitemap.
