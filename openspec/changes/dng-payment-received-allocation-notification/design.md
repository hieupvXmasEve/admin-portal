## Context

Hệ thống notification đã có sẵn từ change `dng-payment-pushed-email-inapp-notification`:
- `PublishDomainEventAction` → `NotificationEventOutbox` → `HandleOutboxEventAction` → `RecipientResolver` → `SendNotificationDeliveryJob`
- `RecipientResolver` hỗ trợ target types: `user`, `student`, `lecture`
- `EmailContentRegistry` + `EmailContentProvider` contract cho rendered email
- `RenderedEmailChannelAdapter` gửi email với pre-rendered HTML

Hiện tại sau khi webhook xác nhận payment thành công (`DngWebhookService::processEvent()`), không có notification nào được gửi cho sinh viên. Tương tự, `bridgeToPayment()` gọi `autoAllocatePayment()` nhưng không thông báo kết quả cho HQ department.

## Goals / Non-Goals

**Goals:**
- Gửi in-app + email cho sinh viên khi DNG webhook chuyển request sang PAID status lần đầu.
- Gửi in-app cho tất cả active members của department `code = "HQ"` sau khi auto-allocation hoàn tất (thành công hoặc không phân bổ được).
- `RecipientResolver` hỗ trợ target type `department` — resolve toàn bộ active DepartmentMembership thành user_ids.

**Non-Goals:**
- Email cho HQ department members.
- Notification khi reconciliation (ReconcileDngPaymentsJob) xử lý.
- Push notification (mobile).
- Notification cho các trạng thái trung gian (PUSHED_TO_DNG, CANCELLED).

## Decisions

### D1: Hook point cho payment received notification

**Quyết định**: Publish notification trong `DngWebhookService::processEvent()`, ngay sau `$event->markProcessed()`.

**Lý do**: `markProcessed()` chỉ được gọi khi status thực sự chuyển từ non-PAID → PAID (các case duplicate/skip đã `return` sớm qua `markSkipped()`). Đây là guard tự nhiên, không cần thêm điều kiện.

**Thay thế đã xem xét**: Hook vào `bridgeToPayment()` — bị loại vì `bridgeToPayment()` được gọi từ nhiều nơi (reconciliation, webhook), có thể gửi notification trùng.

### D2: Hook point cho allocation notification

**Quyết định**: Publish trong `DngPaymentService::bridgeToPayment()`, sau `autoAllocatePayment()`, trong cùng DB transaction.

**Lý do**: `bridgeToPayment()` có lock (`lockForUpdate`) và early return nếu đã bridged — đảm bảo chỉ chạy một lần. `PublishDomainEventAction` đã inject sẵn.

**Lưu ý**: Dùng `runAfterCommit()` để đảm bảo event chỉ publish sau khi transaction commit thành công.

### D3: Recipient target type mới cho department

**Quyết định**: Thêm target type `department` với `id` = department DB id (int).

Caller (`DngPaymentService`) tự lookup:
```php
$deptId = Department::where('code', 'HQ')->value('id');
```

`RecipientResolver` thêm case:
```php
if ($type === 'department') {
    $userIds = DepartmentMembership::where('department_id', $id)
        ->where('is_active', true)
        ->pluck('user_id')
        ->toArray();
    // không filter campus (per requirement)
}
```

**Thay thế đã xem xét**: Target type `department_code` với field `code` (string). Bị loại vì phá vỡ schema `{type, id}` của `recipient_targets`, cần sửa nhiều nơi.

### D4: Email content cho payment received

**Quyết định**: Tạo `DngPaymentReceivedEmailContent` — nội dung đơn giản, không cần bảng chi tiết phức tạp. Chỉ cần: student name, amount, semester (nếu có), ngày thanh toán.

**Lý do**: Sinh viên cần xác nhận nhanh là tiền đã vào. Thông tin chi tiết có trên portal.

### D5: Data cho allocation notification

**Quyết định**: Publisher (`DngPaymentService`) tự tổng hợp data từ `$allocations` Collection trước khi publish:
```php
$allocatedCount = $allocations->count();
$allocatedTotal = $allocations->sum('amount');
$isFullyAllocated = $allocations->isNotEmpty();
```

Body notification:
- Thành công: `"Đã phân bổ tự động [N] khoản phí / [X VNĐ] từ thanh toán của SV [name] ([code])"`
- Thất bại: `"Cảnh báo: Thanh toán [X VNĐ] của SV [name] ([code]) nhận được nhưng không tìm thấy khoản phí tồn đọng để phân bổ"`

## Risks / Trade-offs

**[Risk] HQ department không tồn tại trong DB** → Mitigation: Wrap trong try-catch, log warning, không throw exception (notification là side-effect, không nên ảnh hưởng flow chính).

**[Risk] HQ department có nhiều members** → Mitigation: RecipientResolver trả về tất cả user_ids, hệ thống tạo delivery cho từng user. Chấp nhận scale nhỏ (HQ dept thường không quá lớn).

**[Risk] `bridgeToPayment()` được gọi từ reconciliation sau một thời gian dài** → Mitigation: Vẫn đúng hành vi — lần đầu bridge mới notify, guard `hasBridgedPayment()` đã có sẵn.

## Migration Plan

Không có migration DB mới (dùng schema outbox đã có). Deploy bình thường.

## Open Questions

_Không còn open questions._
