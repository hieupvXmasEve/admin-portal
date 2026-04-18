## Why

Khi DNG webhook xác nhận thanh toán thành công, hệ thống đã ghi nhận payment và phân bổ tự động vào các khoản phí, nhưng không thông báo kết quả cho sinh viên (in-app + email) và không thông báo kết quả phân bổ cho bộ phận quản lý tài chính (HQ department). Sinh viên không biết giao dịch đã hoàn tất; staff HQ không thể phát hiện sớm các trường hợp thanh toán nhận được nhưng không phân bổ được.

## What Changes

- **Thêm notification** sau khi webhook xác nhận thanh toán thành công lần đầu (transition sang PAID status): gửi in-app + email cho sinh viên.
- **Thêm notification** sau khi `autoAllocatePayment` hoàn tất: gửi in-app cho tất cả members của department `code = "HQ"`, bao gồm chi tiết phân bổ hoặc cảnh báo nếu không phân bổ được.
- **Extend `RecipientResolver`**: thêm target type `department` để resolve user_ids từ `DepartmentMembership`.
- **Thêm `DngPaymentReceivedEmailContent`**: email template thông báo thanh toán thành công cho sinh viên.
- **Inject `PublishDomainEventAction`** vào `DngWebhookService`.

## Capabilities

### New Capabilities

- `dng-payment-received-notification`: Gửi in-app + email cho sinh viên khi DNG webhook xác nhận thanh toán thành công lần đầu (first transition to PAID status).
- `dng-allocation-result-notification`: Gửi in-app cho HQ department members sau khi auto-allocation hoàn tất, với đầy đủ chi tiết phân bổ hoặc cảnh báo thất bại.
- `department-recipient-resolution`: `RecipientResolver` hỗ trợ target type `department` — resolve tất cả active members của một department thành user_ids.

### Modified Capabilities

## Impact

- `app/Modules/Finance/Dng/Services/DngWebhookService.php` — inject + call `PublishDomainEventAction`
- `app/Modules/Finance/Dng/Services/DngPaymentService.php` — thêm `publishAllocationNotification()` trong `bridgeToPayment()`
- `app/Modules/Notification/Support/RecipientResolver.php` — thêm case `department`
- `app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php` — new file
- `app/Modules/Notification/Providers/NotificationServiceProvider.php` — register email provider mới
- `app/Modules/Notification/Support/EventIntentMapper.php` — register `finance.dng_payment_received`
- Tests: unit + feature cho 3 capabilities mới
