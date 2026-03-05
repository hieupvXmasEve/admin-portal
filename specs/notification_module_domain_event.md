# Spec Phase 1: Notification Module theo Domain Event (Clean-slate Tables)

Mục tiêu bản này: làm mới Notification theo module riêng, **không dùng lại bảng `notifications` hiện tại**, không phụ thuộc bảng notification mặc định của Laravel, nhưng vẫn tương thích thực tế dự án (multi-campus, multi-actor, queue/broadcast hiện có).

---

## 1) Mục tiêu & phạm vi

### Mục tiêu

- Module nghiệp vụ chỉ emit domain event sau commit.
- Notification module tự resolve recipient + channel + template.
- Phase 1 hỗ trợ 2 kênh:
    - Email
    - In-app realtime + lưu DB cho Notification Center.
- Thiết kế sẵn điểm cắm cho Phase 2 (preference/quiet hours) mà không sửa module nghiệp vụ.

### Không làm trong Phase 1

- Opt-in/opt-out user.
- Digest, quiet hours, throttling nâng cao.
- i18n nâng cao.

---

## 2) Quyết định kiến trúc bắt buộc

1. Event là fact (`finance.invoice_paid`), không đặt tên theo hành động gửi.
2. Pipeline cố định: `Event -> Intent -> Policy -> Persist -> Dispatch -> Track`.
3. Idempotency bắt buộc ở cả message và delivery.
4. Emit event after-commit, không gửi noti trong transaction nghiệp vụ.
5. Channel adapter tách riêng (email/realtime), core không biết chi tiết provider.
6. Recipient phải hỗ trợ **đa actor** (`User`, `Student`, `Lecture`...), không khóa cứng `recipient_user_id`.
7. Mọi entity chính có `campus_id` để giữ đúng boundary multi-campus.

---

## 3) Domain Event contract chuẩn

### Event envelope

- `event_id` (UUID, unique)
- `event_name` (vd: `academic.enrollment_confirmed`)
- `event_version` (int, bắt đầu 1)
- `occurred_at` (datetime)
- `aggregate_type` (vd: `invoice`, `enrollment`)
- `aggregate_id`
- `campus_id` (required nếu event thuộc campus)
- `actor_user_id` (nullable)
- `payload` (json)

### Quy ước

- `event_name` format `{domain}.{event}`.
- Payload tối thiểu, đủ resolve notification; dữ liệu lớn thì rehydrate từ DB.

---

## 4) Luồng end-to-end

1. Business action chạy xong, transaction commit.
2. Ghi event vào outbox `notification_event_outbox`.
3. Worker đọc outbox status `pending`.
4. Handler map `event_name` -> tạo `NotificationIntent`.
5. `PolicyResolver` quyết định channel allow/deny (Phase 1: allow all).
6. Persist `notification_messages` + `notification_deliveries` (`pending`).
7. Dispatch channel jobs (email/realtime).
8. Cập nhật delivery status `sent/failed/skipped`.
9. Mark outbox `dispatched` (hoặc `failed` + retry schedule).

Sơ đồ:

```text
Business Action
  -> after-commit write Outbox
  -> Outbox Worker
  -> Notification Handler
  -> Intent + Policy
  -> Persist Message/Deliveries
  -> Dispatch Email/Realtime
  -> Track status + metrics
```

---

## 5) NotificationIntent & type keys

### Type keys ổn định

- `invoice_paid`
- `enrollment_confirmed`
- `document_missing`
- `class_cancelled`
- `assessment_deadline`

### NotificationIntent

- `type_key`
- `recipient_targets` (morph targets: type + id)
- `channels` (`email`, `realtime`)
- `template_key` theo channel (`{type_key}_{channel}_v{n}`)
- `data` (render payload)
- `priority` (`low|normal|high`, reserve)

---

## 6) Data model clean-slate (module tables riêng)

> Không dùng bảng `notifications` cũ. Không dùng bảng notification mặc định Laravel.

### 6.1 `notification_event_outbox`

- `id` (bigint/ulid)
- `event_id` (uuid, unique)
- `event_name`, `event_version`, `occurred_at`
- `aggregate_type`, `aggregate_id`
- `campus_id` (nullable theo event)
- `actor_user_id` (nullable)
- `payload` (json)
- `status` (`pending|dispatched|failed`)
- `attempts`, `last_error`, `next_retry_at`, `dispatched_at`
- timestamps

Indexes:

- unique(`event_id`)
- index(`status`, `next_retry_at`)
- index(`event_name`, `occurred_at`)
- index(`campus_id`, `occurred_at`)

### 6.2 `notification_messages` (1 bản ghi logic cho 1 recipient)

- `id`
- `event_id` (uuid)
- `event_name`
- `type_key`
- `campus_id` (nullable)
- `recipient_type` (morph class hoặc alias)
- `recipient_id`
- `actor_user_id` (nullable)
- `title` (nullable)
- `body` (nullable)
- `data` (json)
- `status` (`active|archived`)
- `read_at` (nullable)
- `archived_at` (nullable)
- `expires_at` (nullable)
- timestamps

Idempotency/Indexes:

- unique(`event_id`, `type_key`, `recipient_type`, `recipient_id`)
- index(`recipient_type`, `recipient_id`, `read_at`, `created_at`)
- index(`campus_id`, `type_key`, `created_at`)

### 6.3 `notification_deliveries` (theo channel)

- `id`
- `message_id` (fk -> `notification_messages.id`)
- `channel` (`email|realtime`)
- `status` (`pending|sent|failed|skipped`)
- `attempts`
- `provider_message_id` (nullable)
- `email_log_id` (nullable, liên kết `email_logs.id` nếu dùng email subsystem hiện tại)
- `last_error` (nullable)
- `queued_at`, `sent_at`, `failed_at`, `next_retry_at` (nullable)
- timestamps

Idempotency/Indexes:

- unique(`message_id`, `channel`)
- index(`status`, `next_retry_at`)
- index(`channel`, `status`, `created_at`)

---

## 7) Channel adapters (Phase 1)

### Email adapter

- Input: `message + template_key + data`.
- Reuse SMTP config hiện có qua `EmailService` để giảm risk vận hành.
- Ghi `email_log_id` vào `notification_deliveries` nếu gửi qua pipeline email hiện tại.
- Retry/backoff theo policy channel.

### Realtime adapter

- Input: `message`.
- Emit private channel theo recipient morph, format đề xuất: `notify.{recipient_type}.{recipient_id}`.
- Auth channel phải check đúng actor + campus boundary.

---

## 8) Template strategy

- Key format: `{type_key}_{channel}_v{n}`.
- Phase 1 để template trong code.
- Phase 2 có thể chuyển DB template mà không đổi contract Intent/Delivery.

---

## 9) PolicyResolver contract

### Phase 1

- Rule mặc định: allow all channels trong intent.
- Có thể hard-allow loại `system/security`.

### Contract mở rộng

`decide(intent, recipient) -> {allow_channels[], deny_channels[], reason}`

Phase 2 cắm vào:

- opt-in/opt-out theo `type_key + channel`
- quiet hours
- digest/throttling

---

## 10) Reliability, idempotency, observability

- Idempotency tầng event: unique `event_id` ở outbox.
- Idempotency tầng message: unique (`event_id`, `type_key`, `recipient_type`, `recipient_id`).
- Idempotency tầng delivery: unique (`message_id`, `channel`).
- Retry 2 tầng:
    - outbox dispatch retry
    - delivery retry theo channel
- Dead-letter logic: quá N lần fail thì giữ `failed`, không loop vô hạn.
- Log correlation bắt buộc: `event_id`, `message_id`, `delivery_id`, `campus_id`.

---

## 11) Kế hoạch cutover phù hợp codebase hiện tại

1. Tạo mới 3 bảng module (`notification_event_outbox`, `notification_messages`, `notification_deliveries`).
2. Build handler + adapter mới, chưa đụng endpoint cũ.
3. Chọn 1-2 domain event quan trọng để pilot (`academic`, `finance`).
4. Chạy dual-read ngắn hạn ở UI Notification Center (feature flag).
5. Ổn định xong mới chuyển read/write chính sang module mới.
6. Freeze flow cũ, deprecate dần service/listener cũ.

---

## 12) Deliverables Phase 1

1. Event contract + naming convention chuẩn.
2. Outbox table + dispatcher worker.
3. Notification handlers (event -> intent).
4. Persist message + deliveries bằng clean-slate tables.
5. Channel jobs: email + realtime.
6. Idempotency constraints + retry policy.
7. Monitoring cơ bản theo `event_id/channel/type/status`.

---

## 13) Acceptance criteria

- Event hợp lệ tạo đúng message cho đúng recipient (đa actor).
- Retry không tạo duplicate message/delivery.
- Delivery status rõ ràng: `sent/failed/skipped`.
- Realtime channel không rò cross-actor/cross-campus.
- Thêm preference ở Phase 2 chỉ sửa Notification module.

---

## Unresolved Questions

1. Recipient chuẩn hóa theo morph class đầy đủ hay alias domain (`user|student|lecturer`)?
2. `campus_id` bắt buộc toàn bộ event/message hay cho phép null với event global?
3. Giai đoạn cutover có cần backfill từ bảng `notifications` cũ sang bảng mới không?
4. Realtime provider chính thức giữ Ably hiện tại hay abstraction để thay provider sau?
