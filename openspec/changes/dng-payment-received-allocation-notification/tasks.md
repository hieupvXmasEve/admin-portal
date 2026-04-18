## 1. RecipientResolver — Department target type

- [x] 1.1 Thêm case `department` vào `RecipientResolver::resolve()`: query `DepartmentMembership::where('department_id', $id)->where('is_active', true)->pluck('user_id')`, không filter campus
- [x] 1.2 Thêm case `department_not_found` vào unresolved khi department id không tồn tại
- [x] 1.3 Viết unit test cho `RecipientResolver`: department có members, department rỗng, department không tồn tại

## 2. DngPaymentReceivedEmailContent

- [x] 2.1 Tạo `app/Modules/Notification/EmailContent/Types/DngPaymentReceivedEmailContent.php` implement `EmailContentProvider`
- [x] 2.2 `subject()`: trả về `"[Asia Việt Nam] Xác nhận thanh toán thành công"` (có thể thêm học kỳ nếu có)
- [x] 2.3 `htmlBody()`: nội dung đơn giản — tên + mã SV, số tiền formatted, học kỳ (skip nếu null), ngày nhận tiền, bilingual (VI + EN)
- [x] 2.4 Register trong `NotificationServiceProvider::registerEmailContentProviders()` với key `dng_payment_received`
- [x] 2.5 Viết unit test cho `DngPaymentReceivedEmailContent`: có semester, không có semester

## 3. EventIntentMapper — Register event name

- [x] 3.1 Thêm `'finance.dng_payment_received' => 'dng_payment_received'` vào `resolveTypeKey()` trong `EventIntentMapper`
- [x] 3.2 Thêm `'finance.dng_payment_allocated' => 'dng_payment_allocated'` vào `resolveTypeKey()`

## 4. DngWebhookService — Payment received notification

- [x] 4.1 Inject `PublishDomainEventAction` vào constructor của `DngWebhookService`
- [x] 4.2 Thêm private method `publishPaymentReceivedNotification(DngPaymentRequest $request): void`
- [x] 4.3 Trong method đó: build `DomainEventEnvelope` với event `finance.dng_payment_received`, channels `['realtime', 'email']`, target `student`, data bao gồm `dng_payment_request_id`, `student_name`, `amount_formatted`, `paid_at`
- [x] 4.4 Wrap trong try-catch: log warning nếu fail, không throw
- [x] 4.5 Gọi `publishPaymentReceivedNotification($request->fresh())` sau `$event->markProcessed()` trong `processEvent()`

## 5. DngPaymentService — Allocation result notification

- [x] 5.1 Thêm private method `publishAllocationNotification(DngPaymentRequest $request, Payment $payment, Collection $allocations): void`
- [x] 5.2 Lookup `Department::where('code', 'HQ')->value('id')` — nếu null thì log warning và return
- [x] 5.3 Build body cho success case: `"Đã phân bổ tự động {N} khoản phí / {X VNĐ} từ thanh toán của SV {name} ({code})"`
- [x] 5.4 Build body cho failure case (allocations rỗng): `"Cảnh báo: Thanh toán {X VNĐ} của SV {name} ({code}) nhận được nhưng không tìm thấy khoản phí tồn đọng để phân bổ"`
- [x] 5.5 Publish event `finance.dng_payment_allocated`, channels `['realtime']`, target `[{'type': 'department', 'id': $deptId}]`
- [x] 5.6 Wrap trong try-catch: log warning, không throw
- [x] 5.7 Gọi `publishAllocationNotification($request, $payment, $allocations)` sau `autoAllocatePayment()` trong `bridgeToPayment()`

## 6. Tests

- [x] 6.1 Feature test: webhook nhận payment thành công → notification event được publish (assert outbox record)
- [x] 6.2 Feature test: webhook duplicate (markSkipped) → không có notification event
- [x] 6.3 Feature test: `bridgeToPayment()` với allocations có data → HQ notification published với success body
- [x] 6.4 Feature test: `bridgeToPayment()` với allocations rỗng → HQ notification published với warning body
- [x] 6.5 Feature test: `bridgeToPayment()` không tìm thấy HQ dept → không có exception, log warning
