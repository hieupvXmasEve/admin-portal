# Email Content Layer

Last updated: 2026-04-18 (rev 5)
Status: Proposed target design

## Bài toán

- User đưa mẫu email cho dev.
- Dev sửa code.
- Cần quản lý tập trung một chỗ, không bị rối.

Cách đúng nhất: tạo một "email content layer" trong code, tách khỏi business logic.

---

## Nên làm thế nào

### 1. Mọi nội dung email phải nằm ở một chỗ duy nhất

```text
app/Modules/Notification/EmailContent/
├── Contracts/
│   └── EmailContentProvider.php
├── Types/
│   ├── PaymentReminderEmailContent.php
│   ├── InvoiceOverdueEmailContent.php
│   └── TuitionDueNoticeEmailContent.php
└── EmailContentRegistry.php
```

Mục tiêu:
- Dev muốn sửa wording chỉ vào đây.
- Không sửa ở controller / service / job / domain action.

### 2. Business code không được viết subject/body

Feature chỉ làm:
- Publish event.
- Build data.
- Gọi notification pipeline.

Không được làm kiểu này:

```php
// WRONG
$subject = "Nhắc nhở học phí tháng ...";
$html    = "<p>Dear ...</p>";
Mail::to($user)->send(...);
```

Không viết subject/body inline trong finance service, controller, hay job.

### 3. Mỗi loại email có một class riêng

```php
interface EmailContentProvider
{
    public function subject(array $data): string;
    public function htmlBody(array $data): string;
    public function textBody(array $data): ?string;
}
```

`textBody()` trả về `?string`:
- Trả về plain-text version nếu có.
- Trả về `null` nếu provider không cần plain-text.
- Pipeline **không tự strip HTML** làm fallback — HTML email thường có table/layout phức tạp, strip ra text vô nghĩa.
- SMTP client gửi multipart chỉ khi `rendered_text` có giá trị; nếu `null` thì gửi HTML-only.

`subject()` và `htmlBody()` bắt buộc non-null.

Khi user đổi wording:
- Dev sửa đúng 1 file.
- Diff rất sạch.
- Review rất dễ.

### 4. Có một registry duy nhất map `type_key` → content class

```php
class EmailContentRegistry
{
    private array $map = [
        'payment_reminder'   => PaymentReminderEmailContent::class,
        'invoice_overdue'    => InvoiceOverdueEmailContent::class,
        'tuition_due_notice' => TuitionDueNoticeEmailContent::class,
    ];

    public function resolve(string $typeKey): EmailContentProvider;
    public function has(string $typeKey): bool;
}
```

Đây là chỗ để quản lý chung. Nhìn vào đây biết ngay hệ thống đang có bao nhiêu loại email.

### 5. Data phải được build ở backend trước — content class không query DB

Tách rõ 2 phần:

| Phần | Trách nhiệm |
|------|-------------|
| Data builder | Lấy dữ liệu từ DB, chuẩn hóa `$data` |
| Content class | Nhận `$data`, render subject/body — không query DB |

Logic nghiệp vụ ở một chỗ, nội dung email ở một chỗ, không trộn lẫn.

### 6. Render tại OutboxProcessor — lưu vào notification_deliveries trước khi dispatch job

```text
OutboxProcessor
  -> resolve recipient + channel
  -> build $data  (data builder — query DB ở bước này)
  -> EmailContentRegistry::resolve($typeKey)->subject/html/text($data)
  -> INSERT notification_deliveries (rendered_subject, rendered_html, rendered_text, status=pending)
  -> dispatch SendNotificationDeliveryJob(delivery_id)

SendNotificationDeliveryJob
  -> load delivery
  -> nếu channel = email và rendered_subject IS NOT NULL → RenderedEmailChannelAdapter
  -> nếu channel = email và rendered_subject IS NULL     → EmailChannelAdapter (flow cũ)
  -> UPDATE status = sent / failed
```

Job chỉ nhận `delivery_id`, đọc rendered string từ DB. Không gọi lại provider.

Lý do: retry phải gửi lại đúng nội dung lúc ban đầu. Nếu data gốc thay đổi (ví dụ invoice amount cập nhật), nội dung email vẫn phải giống lần đầu — đặc biệt quan trọng với finance notifications.

### 7. Adapter mới — RenderedEmailChannelAdapter

`EmailChannelAdapter` giữ nguyên, không sửa. Flow cũ chạy nguyên trạng.

Thêm `RenderedEmailChannelAdapter` implement cùng `ChannelAdapter` contract:

```php
class RenderedEmailChannelAdapter implements ChannelAdapter
{
    public function send(NotificationDelivery $delivery): array
    {
        // đọc từ delivery->rendered_subject / rendered_html / rendered_text
        // resolve SMTP qua EmailConfiguration::getActiveForCampus($message->campus_id)
        // gửi email, trả về ['email_log_id' => ...]
    }
}
```

Routing trong `SendNotificationDeliveryJob`:

```php
'email' => $delivery->rendered_subject !== null
    ? $renderedEmailChannelAdapter->send($delivery)
    : $emailChannelAdapter->send($delivery),
```

`rendered_subject` có giá trị là signal duy nhất để route sang path mới — không cần registry lookup tại job time.

Columns cần thêm vào `notification_deliveries` (migration mới):

```
rendered_subject  TEXT        NOT NULL  (khi dùng path mới)
rendered_html     LONGTEXT    NOT NULL  (khi dùng path mới)
rendered_text     TEXT        NULL
```

`last_error` đã tồn tại trong model hiện tại — không cần add thêm.

### 8. NotificationMessage.title/body là của in-app — không liên quan đến email

Email và in-app là hai shape khác nhau, không derive từ nhau:

| Channel | Fields |
|---------|--------|
| In-app / realtime | `title`, `body` (ngắn, push-style) |
| Email | `subject`, `html`, `text` (full content) |

Business code build `title/body` cho in-app. `EmailContentProvider` build riêng cho email. Hai path độc lập, không trộn.

---

## Rule để không bị rối

1. Mọi email wording nằm trong `EmailContent/Types/`.
2. Mọi mapping nằm trong `EmailContentRegistry`.
3. Không viết subject/body trong feature service.
4. Content class không query DB.
5. Mỗi `type_key` có đúng một email content class.

---

## Scope của spec này

Spec này chỉ mô tả email content layer: `EmailContentProvider`, `EmailContentRegistry`, và cách render/lưu content.

**Ngoài scope:**
- Đăng ký `type_key` vào `NotificationTypeRegistry` — đó là trách nhiệm của notification domain spec.
- Event mapping, recipient resolution, channel policy.

Khi thêm email content cho một `type_key`, spec này assume `type_key` đó đã tồn tại trong `NotificationTypeRegistry`.

---

## Khi user đưa mẫu mới, quy trình là

**Trường hợp A — `type_key` đã tồn tại trong `NotificationTypeRegistry`:**

1. User gửi nội dung mong muốn cho dev.
2. Dev xác định `type_key`.
3. Dev sửa file content tương ứng trong `EmailContent/Types/`.
4. Nếu chưa có content class cho type đó: tạo mới + thêm vào `EmailContentRegistry` + viết unit test.

**Trường hợp B — `type_key` chưa tồn tại:**

1. Đăng ký `type_key` vào `NotificationTypeRegistry` trước (ngoài scope spec này).
2. Sau đó thực hiện như Trường hợp A.

---

## Kết luận

Nếu mục tiêu là "user đưa mẫu, dev sửa code, nhưng phải dễ quản lý", thì:

- Không dùng DB template trước.
- Không cho business code tự viết mail.
- Gom toàn bộ wording vào một content layer trong code.
- Quản lý bằng registry + từng class theo type.

Đó là cách đơn giản nhất, sạch nhất, ít rủi ro nhất.
