## Context

Hệ thống notification hiện có pipeline đầy đủ (outbox → HandleOutboxEventAction → PersistIntentAction → SendNotificationDeliveryJob). Event `finance.dng_payment_pushed` đã tồn tại trong `NotificationTypeRegistry` và `DngPaymentService` đã gọi `PublishDomainEventAction` sau khi push thành công. Tuy nhiên, `EmailChannelAdapter` hiện tại dùng `NotificationMessage.title/body` — là plain text dành cho in-app — để gửi email. User đã cung cấp mẫu email HTML bilingual (VI/EN) với bảng chi tiết học phí và hướng dẫn thanh toán.

## Goals / Non-Goals

**Goals:**
- Email gửi đến sinh viên theo đúng mẫu HTML đã duyệt.
- In-app (realtime) notification vẫn hiển thị title/body ngắn, độc lập.
- Retry email gửi lại đúng nội dung đã render lần đầu.
- Backward compatible: các `type_key` chưa có EmailContentProvider vẫn đi qua `EmailChannelAdapter` cũ.
- Quản lý wording email tập trung trong `EmailContent/Types/`, không rải trong service.

**Non-Goals:**
- Không implement DB template (WYSIWYG editor cho email).
- Không thay đổi in-app notification shape.
- Không cover các loại email khác ngoài `dng_payment_pushed` trong change này.

## Decisions

### D1: Render tại HandleOutboxEventAction, lưu vào notification_deliveries

**Quyết định**: Render email content (`rendered_subject`, `rendered_html`, `rendered_text`) tại `HandleOutboxEventAction` trước khi tạo `NotificationDelivery`, lưu vào delivery record.

**Lý do**: Job chỉ nhận `delivery_id`, đọc rendered string từ DB. Retry không gọi lại content provider — đảm bảo nội dung email nhất quán dù data gốc thay đổi sau đó (đặc biệt quan trọng với finance).

**Thay thế đã xem xét**: Render trong Job → bị loại vì retry sẽ re-render, không đảm bảo idempotency nội dung.

### D2: RenderedEmailChannelAdapter mới, giữ nguyên EmailChannelAdapter cũ

**Quyết định**: Thêm `RenderedEmailChannelAdapter` implement cùng `ChannelAdapter` contract. Routing trong `SendNotificationDeliveryJob` dựa vào `delivery->rendered_subject !== null`.

**Lý do**: Không sửa `EmailChannelAdapter` cũ tránh rủi ro regression. Signal routing (`rendered_subject !== null`) đơn giản, không cần registry lookup tại job time.

### D3: DataBuilder query DB tại HandleOutboxEventAction, EmailContentProvider không query DB

**Quyết định**: `HandleOutboxEventAction` chịu trách nhiệm build `$data` array đầy đủ (eager load `DngPaymentRequest::with(['semester', 'student.program'])`). `EmailContentProvider` chỉ nhận `$data` và render string.

**Lý do**: Tách rõ concern — business data ở một chỗ, wording ở một chỗ. Content class có thể unit test mà không cần DB.

**Data cần cho template**:
- `student_name`, `student_code` — từ event payload
- `semester_code` — từ `DngPaymentRequest.semester` (eager load)
- `program_name` — từ `Student.program.name` (eager load qua student_id)
- `invoice_code` — `DngPaymentRequest.item_id`
- `amount` — formatted VND
- `due_date` — `DngPaymentRequest.due_date` (nullable, nếu null không hiển thị)

### D4: Subject format cố định

```
[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ {semester_code}
```

Nếu `semester_code` null (hiếm) → bỏ phần semester.

## Risks / Trade-offs

- **Migration nullable columns**: `rendered_subject/html/text` là nullable để backward compat. Deliveries cũ không có rendered content vẫn đi qua `EmailChannelAdapter`. → Không cần backfill.
- **Data builder thêm N+1 query**: Mỗi delivery cần query `DngPaymentRequest` + relations. Với email loại này (single student), chấp nhận được. → Eager load `with(['semester', 'student.program'])`.
- **semester_id nullable**: `DngPaymentRequest.semester_id` có thể null nếu admin không chọn. → Subject và body hiển thị mà không có semester code; `due_date` null → bỏ dòng hạn thanh toán trong email.

## Migration Plan

1. Deploy migration thêm 3 columns vào `notification_deliveries` (nullable, không cần downtime).
2. Deploy code mới — routing theo `rendered_subject IS NOT NULL`.
3. Deliveries cũ (không có rendered content) tiếp tục đi qua path cũ.
4. Rollback: xóa routing condition, toàn bộ email đi qua `EmailChannelAdapter` cũ.

## Open Questions

- Phòng tài chính có muốn CC ai (admin/staff) khi email gửi cho sinh viên không? → Giữ ngoài scope lần này.
- `rendered_text` (plain text version) có cần không? → Để null lần này, SMTP client gửi HTML-only.
