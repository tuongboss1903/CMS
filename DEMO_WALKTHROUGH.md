# DEMO WALKTHROUGH — Kịch bản Trình diễn Khách hàng Doanh nghiệp

> Dành cho Sales/Founder khi demo trực tiếp (hoặc qua video call) cho khách hàng/nhà đầu tư. Toàn bộ URL/thao tác trong tài liệu này đã được xác nhận hoạt động thật qua PHPUnit + môi trường thật (không phải mô tả tính năng dự kiến).
>
> **Chuẩn bị chung trước mọi buổi demo**: đã chạy đủ chuỗi lệnh ở `STAGING_CHECKLIST.md` mục "Data Initialization" (2 tenant `cms.test`/`restaurant.test` đã có dữ liệu), có tài khoản Admin đăng nhập được, trình duyệt đã đăng xuất sẵn (để demo luồng đăng nhập tự nhiên), tắt extension chặn quảng cáo/script (tránh che UI khi chiếu màn hình).

---

## Bước 1 — Public Enterprise Landing Page Showcase

**Mục tiêu trình diễn**: Chứng minh sản phẩm có giao diện Public chuyên nghiệp, đúng chuẩn B2B, không phải "trang demo kỹ thuật".

**Thao tác**:
1. Mở `http://cms.test/` (Tenant "SaaS CMS Technology Co.").
2. Cuộn qua Hero Section → 2 nút CTA ("Vào trang quản trị", "Khám phá tính năng") → Feature Grid (6 khối) → Showcase Block → CTA Footer.
3. Mở DevTools (F12) → bật chế độ Responsive → chuyển qua 3 kích thước: Desktop (mặc định) → Tablet (≤992px, Feature Grid co 2 cột) → Mobile (≤768px, Feature Grid co 1 cột, menu thu gọn dạng hamburger).

**Key Selling Points**:
- "Đây không phải theme dựng sẵn mua ngoài — toàn bộ giao diện là CSS3 thuần tự viết, không phụ thuộc Bootstrap/Tailwind, tối ưu tốc độ tải."
- "Responsive đầy đủ 3 cấp độ màn hình, khách hàng của quý vị dùng điện thoại vẫn trải nghiệm mượt."
- "Nội dung trên trang này — Hero, Feature, Showcase — hoàn toàn có thể tự chỉnh sửa qua Admin, không cần đụng code."

**Điều kiện chuẩn bị trước**: domain `cms.test` đã trỏ hosts local (hoặc DNS thật nếu demo trên Staging), đã seed content pack `tech`.

---

## Bước 2 — Multi-Tenant Real-time Switch

**Mục tiêu trình diễn**: Đây là **điểm bán hàng cốt lõi** — chứng minh 1 hạ tầng duy nhất phục vụ nhiều khách hàng/thương hiệu, cách ly dữ liệu tuyệt đối.

**Thao tác**:
1. Mở 2 tab trình duyệt song song: Tab A = `http://cms.test/`, Tab B = `http://restaurant.test/`.
2. Đặt 2 tab cạnh nhau (chia đôi màn hình) — chỉ ra: cùng lúc 2 thương hiệu hoàn toàn khác nhau (SaaS Công nghệ vs. Nhà hàng F&B), khác tên miền, khác nội dung, khác Menu — nhưng chạy chung **1 server, 1 database, 1 lần deploy code duy nhất**.
3. Vào `http://restaurant.test/admin/login`, đăng nhập **bằng đúng 1 tài khoản Admin** đã dùng cho Tenant 1 — chứng minh 1 tài khoản quản trị được nhiều Site nếu được cấp quyền, nhưng dữ liệu nội dung (Page/Media/Menu/SEO) của 2 Site không lẫn vào nhau.

**Key Selling Points**:
- "Quý vị có thể vận hành không giới hạn website con dưới cùng 1 hạ tầng — tiết kiệm chi phí server so với mô hình 1 website/1 server riêng."
- "Dữ liệu cách ly tuyệt đối ở tầng Database — không có rủi ro rò rỉ nội dung giữa các thương hiệu, dù dùng chung hạ tầng vật lý."
- "Thêm 1 thương hiệu mới chỉ mất vài phút (1 lệnh CLI), không cần cài đặt lại hệ thống."

**Điều kiện chuẩn bị trước**: `restaurant.test` đã được tạo qua `bin/add_site.php` + seed content pack `restaurant`, hosts local có cả 2 dòng.

---

## Bước 3 — Admin Dashboard & Content Management

**Mục tiêu trình diễn**: Chứng minh trải nghiệm quản trị trực quan, không cần biết kỹ thuật vẫn thao tác được.

**Thao tác**:
1. Đăng nhập `http://cms.test/admin/login`.
2. Tại `/admin/dashboard`: chỉ vào 4 Metric Card (Trang đã xuất bản/Media/User/Role) + bảng "Hoạt động gần đây" (Activity Stream) — nhấn mạnh dữ liệu này cập nhật real-time theo thao tác thật.
3. Bấm "Tạo trang mới" (Quick Action) → nhập tiêu đề/slug → dùng Rich Text Editor (Quill.js) gõ + định dạng 1 đoạn nội dung (bold, heading, bullet list) → Lưu → xuất bản.
4. Vào `/admin/media` → kéo-thả 1 file ảnh vào khung Upload Modal → xác nhận ảnh xuất hiện ngay trong Grid.

**Key Selling Points**:
- "Không cần thuê lập trình viên để đăng bài — quy trình giống hệt các CMS phổ biến (WordPress-style) nhưng dữ liệu và hạ tầng hoàn toàn do quý vị/đội ngũ kỹ thuật kiểm soát."
- "Dashboard cho biết ngay hoạt động gần nhất của toàn hệ thống — hữu ích khi có nhiều người cùng quản trị nội dung."
- "Upload ảnh kéo-thả trực tiếp, không cần FTP hay công cụ ngoài."

**Điều kiện chuẩn bị trước**: chuẩn bị sẵn 1 file ảnh mẫu (định dạng jpg/png, dưới 5MB) trên máy demo để upload trực tiếp.

---

## Bước 4 — Dynamic Menu Builder & SEO Meta Automation

**Mục tiêu trình diễn**: Chứng minh năng lực kỹ thuật nâng cao — kéo-thả thời gian thực và tự động hóa SEO chuẩn Google, thường là điểm khách hàng doanh nghiệp/nhà đầu tư đánh giá cao.

**Thao tác**:
1. Vào `/admin/menus/{id}` (Menu chính) — kéo 1 mục Menu sang vị trí/cấp khác bằng chuột, thả ra → chỉ ra thay đổi được lưu **ngay lập tức, không cần bấm nút Lưu, không reload trang** (AJAX thật).
2. Vào `/admin/seo/pages/{id}` (trang bất kỳ) — điền Meta Title/Description, chọn ảnh OG Image, điền Schema Type — Lưu.
3. Mở trang Public tương ứng, bấm chuột phải → "View Page Source" (hoặc `view-source:http://cms.test/...`) — chỉ vào `<meta property="og:title">`, `<meta property="og:description">`, `<meta property="og:image">`, `<script type="application/ld+json">` đã render thật trong HTML.
4. Mở `http://cms.test/sitemap.xml` và `http://cms.test/robots.txt` — chỉ ra 2 file này **tự sinh động**, không cần cấu hình tay, luôn khớp danh sách trang đã xuất bản.

**Key Selling Points**:
- "Kéo-thả Menu thay đổi ngay lập tức — không có độ trễ, không mất dữ liệu nếu quên bấm Lưu."
- "SEO không phải việc riêng của lập trình viên — đội Marketing tự cấu hình Open Graph/Schema.org ngay trong Admin, không cần sửa code."
- "Sitemap và Robots.txt tự động cập nhật theo nội dung thật — không bao giờ bị lỗi thời hay quên cập nhật thủ công."

**Điều kiện chuẩn bị trước**: Menu chính đã có ít nhất 2-3 cấp mục để thao tác kéo-thả rõ ràng; đã chọn sẵn 1 trang có ảnh Media để demo OG Image.

---

## Ghi chú cho người trình diễn

- Toàn bộ 4 bước có thể demo trong **10-15 phút**, phù hợp buổi giới thiệu ngắn.
- Nếu khách hàng hỏi sâu về hạ tầng: tham chiếu `DEPLOYMENT.md`/`STAGING_CHECKLIST.md` (không phô ra trực tiếp trong buổi demo — tài liệu kỹ thuật nội bộ).
- Nếu gặp lỗi bất ngờ khi demo trực tiếp: không debug trước mặt khách hàng — chuyển sang bước tiếp theo, xử lý riêng sau.
