# MODULE: BLOG / POST

## 1. Mục đích

Quản lý bài viết dạng blog/tin tức, có phân loại Category/Tag, phục vụ SEO nội dung (content marketing) cho site.

## 2. Danh sách chức năng

- CRUD Post.
- Quản lý Category (đa cấp: cha - con).
- Quản lý Tag (gắn N-N với Post).
- Soạn nội dung Rich Text (WYSIWYG) hoặc Block-based (đồng bộ cách tiếp cận với module Page).
- Ảnh đại diện (featured image) từ Media Library.
- Xuất bản / Lưu nháp / Lên lịch.
- Bài viết liên quan (related posts — tự động theo Category/Tag hoặc chọn tay).
- Bình luận (tuỳ chọn bật/tắt theo Settings của site).
- Đếm lượt xem (view count) — phục vụ thống kê.

## 3. Bảng dữ liệu liên quan

- `posts`: id, tenant_id, title, slug, excerpt, content, featured_image_id, status, published_at, category_id, created_by, view_count.
- `categories`: id, tenant_id, parent_id, name, slug.
- `tags`: id, tenant_id, name, slug.
- `post_tags`: post_id, tag_id.
- `comments` (tuỳ chọn): id, post_id, name, email, content, status (pending/approved/spam).

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Media | N - 1 (featured) + N - N (nội dung) | Ảnh đại diện + ảnh chèn trong nội dung |
| SEO | 1 - 1 | Meta riêng từng Post |
| Menu | 1 - N | Category có thể lên Menu |
| User/Role | N - 1 | Quyền `post.create/edit/publish/delete` |
| Form | N - N | Post có thể nhúng Form (ví dụ form đăng ký nhận bản tin cuối bài) |

## 5. Data Flow

```
Admin tạo/sửa Post
  → PostService validate (slug unique, category tồn tại)
  → Repository lưu posts + đồng bộ post_tags
  → Hook "post.before_save" / "post.after_save"
  → Nếu status chuyển sang "published" → Hook "post.published"
     → SEO Service cập nhật sitemap
     → Cache invalidate danh sách bài viết theo category liên quan
```

```
Khách xem danh sách Post (trang Blog)
  → PostController → PostService: lấy danh sách theo filter (category/tag/từ khoá) + phân trang
  → Cache theo key: posts:{tenant_id}:{category}:{page}
  → Trả về Theme render danh sách + pagination
```

## 6. User Flow

1. Khách vào trang Blog → xem danh sách bài viết (lọc theo Category/Tag nếu có).
2. Bấm vào 1 bài → xem chi tiết, view_count +1.
3. (Nếu bật) gửi bình luận → chờ duyệt (status pending) trước khi hiển thị công khai.
4. Xem "Bài viết liên quan" ở cuối bài.

## 7. Admin Flow

1. Quản lý Category/Tag trước (hoặc tạo nhanh ngay khi soạn Post).
2. Soạn Post mới → chọn Category, thêm Tag, chọn ảnh đại diện từ Media.
3. Xuất bản/lưu nháp/đặt lịch.
4. Duyệt bình luận (nếu bật tính năng comment): Approve/Spam/Xoá.
5. Xem thống kê lượt xem theo bài viết.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/posts` | GET | Public | Danh sách bài viết đã publish (filter category/tag) |
| `/api/v1/posts/{slug}` | GET | Public | Chi tiết bài viết |
| `/api/v1/posts` | POST | `post.create` | Tạo bài viết |
| `/api/v1/posts/{id}` | PUT | `post.update` | Cập nhật |
| `/api/v1/posts/{id}` | DELETE | `post.delete` | Xoá |
| `/api/v1/categories` | GET/POST | Public (GET) / `post.manage_category` (POST) | Danh mục |
| `/api/v1/posts/{id}/comments` | GET/POST | Public | Xem/gửi bình luận |

## 9. Hook/Event bắn ra

- `post.before_save` / `post.after_save`
- `post.published`
- `post.deleted`
- `comment.submitted`
- `comment.approved`
