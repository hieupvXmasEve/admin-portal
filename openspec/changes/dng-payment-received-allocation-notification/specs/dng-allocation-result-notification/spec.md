## ADDED Requirements

### Requirement: Publish allocation result notification to HQ department
Sau khi `DngPaymentService::bridgeToPayment()` gọi `autoAllocatePayment()` thành công, hệ thống SHALL publish domain event `finance.dng_payment_allocated` qua `PublishDomainEventAction::runAfterCommit()`.

Event SHALL target tất cả active members của Department có `code = "HQ"`, không giới hạn campus.

#### Scenario: Allocation successful — notify HQ with details
- **WHEN** `autoAllocatePayment()` trả về Collection không rỗng (có ít nhất 1 PaymentApplication)
- **THEN** in-app notification được gửi cho HQ members với nội dung: số khoản phí đã phân bổ, tổng tiền phân bổ, tên và mã sinh viên

#### Scenario: Allocation failed — notify HQ with warning
- **WHEN** `autoAllocatePayment()` trả về Collection rỗng (không có khoản phí tồn đọng)
- **THEN** in-app notification cảnh báo được gửi cho HQ members: số tiền nhận được nhưng không phân bổ được, tên và mã sinh viên

#### Scenario: HQ department not found — skip silently
- **WHEN** `Department::where('code', 'HQ')->value('id')` trả về null
- **THEN** log warning được ghi, không có notification, không throw exception

#### Scenario: Notification failure does not break payment bridging
- **WHEN** `PublishDomainEventAction::runAfterCommit()` throw exception
- **THEN** exception được catch, log warning, payment đã bridged thành công vẫn được giữ nguyên

### Requirement: HQ allocation notification is in-app only
HQ department notification SHALL chỉ dùng channel `["realtime"]`. Không gửi email cho HQ members từ luồng này.

#### Scenario: Channel is realtime only
- **WHEN** domain event `finance.dng_payment_allocated` được publish
- **THEN** `channels` trong payload là `["realtime"]` và không có `EmailContentProvider` nào registered cho type_key này
