# MODULE: FORM (Form Builder)

## 1. Mục đích

Cho phép Admin tự dựng form (liên hệ, đăng ký, yêu cầu báo giá...) không cần code, nhúng được vào Page/Post/Product.

## 2. Danh sách chức năng

- Tạo Form, kéo-thả thêm Field (text, email, phone, textarea, select, checkbox, file upload).
- Cấu hình validation cho từng Field (required, định dạng email/phone).
- Cấu hình hành động sau khi Submit: gửi email thông báo, lưu vào Database, gọi Webhook (tích hợp CRM ngoài).
- Chống spam (honeypot field + rate-limit theo IP; cân nhắc captcha nếu cần sau).
- Xem danh sách dữ liệu đã submit (dạng bảng, xuất CSV).
- Nhúng Form vào nội dung qua shortcode/block (`[form id="5"]` hoặc block "Form" trong trình soạn Page/Post).

## 3. Bảng dữ liệu liên quan

- `forms`: id, tenant_id, name, fields (json schema), success_message, notify_email, webhook_url.
- `form_submissions`: id, form_id, tenant_id, data (json), ip, created_at, status (new/read/spam).

## 4. Quan hệ với module khác

| Module liên quan | Kiểu quan hệ | Mô tả |
|---|---|---|
| Page/Post/Product | N - N | Form được nhúng vào nội dung các module này |
| Media | 1 - N | Field kiểu file upload lưu vào Media Library |
| Settings | 1 - 1 | Cấu hình email gửi đi (SMTP) dùng chung Settings của site |

## 5. Data Flow

```
Khách Submit Form
  → FormController: nhận POST data
  → FormService: validate theo schema field đã cấu hình
  → Check honeypot + rate-limit IP
  → Repository lưu form_submissions
  → Hook "form.submitted"
  → Gửi email thông báo (nếu cấu hình notify_email)
  → Gọi Webhook (nếu cấu hình, bất đồng bộ để không chặn response)
  → Trả success_message cho khách
```

## 6. User Flow

1. Khách điền Form trên trang (liên hệ/đăng ký/báo giá).
2. Bấm Gửi → validate phía client (JS) + server.
3. Nhận thông báo thành công (success_message tuỳ Admin cấu hình).
4. (Nếu cấu hình) nhận email xác nhận tự động.

## 7. Admin Flow

1. Vào "Form Builder" → tạo Form mới, kéo-thả field cần thiết.
2. Cấu hình nơi nhận thông báo (email), có thể thêm Webhook.
3. Lấy shortcode/chèn block Form vào Page/Post/Product tương ứng.
4. Xem danh sách submission → đánh dấu đã đọc/spam, xuất CSV để chăm sóc khách hàng.

## 8. API Flow

| Endpoint | Method | Quyền | Mô tả |
|---|---|---|---|
| `/api/v1/forms/{id}` | GET | Public | Lấy schema field (phục vụ render form phía FE nếu headless) |
| `/api/v1/forms/{id}/submit` | POST | Public (có rate-limit) | Gửi dữ liệu form |
| `/api/v1/forms/{id}/submissions` | GET | `form.view_submission` | Xem danh sách đã gửi (Admin) |
| `/api/v1/forms` | POST | `form.create` | Tạo form mới |

## 9. Hook/Event bắn ra

- `form.submitted`
- `form.submission_marked_spam`
- `form.created`
