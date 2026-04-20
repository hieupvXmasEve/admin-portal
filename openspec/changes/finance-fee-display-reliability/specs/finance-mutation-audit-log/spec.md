## ADDED Requirements

### Requirement: Finance mutation log records all money-changing events
Hệ thống SHALL ghi một row vào `finance_mutation_logs` mỗi khi có bất kỳ thay đổi nào liên quan đến tiền. Log phải chứa: entity_type, entity_id, event_type, student_id, actor_user_id (nullable cho system), amount, before_snapshot (JSON), after_snapshot (JSON), context (JSON), created_at.

#### Scenario: Payment application được tạo
- **WHEN** `SettlementService::createPaymentApplication()` được gọi với `entry_type = 'application'`
- **THEN** một row được insert vào `finance_mutation_logs` với `entity_type = 'payment_application'`, `event_type = 'applied'`, `amount = signed_amount`, `context.payment_id`, `context.invoice_line_id`, `context.source_action`

#### Scenario: Payment reversal được tạo
- **WHEN** `SettlementService::createPaymentApplication()` được gọi với `entry_type = 'reversal'`
- **THEN** một row được insert với `entity_type = 'payment_application'`, `event_type = 'reversed'`, `amount = negative_amount`

#### Scenario: FinanceCharge bị void
- **WHEN** `VoidFinanceChargeAction::handle()` hoàn thành
- **THEN** một row được insert với `entity_type = 'charge'`, `event_type = 'voided'`, `amount = charge.amount`, `context.void_reason`, `before_snapshot.status = 'active'`, `after_snapshot.status = 'void'`

#### Scenario: FinanceCharge được tạo
- **WHEN** `GenerateBatchChargesAction` hoặc `GenerateEgcChargesAction` tạo charge mới
- **THEN** một row được insert với `entity_type = 'charge'`, `event_type = 'created'`, `amount = charge.amount`, `context.charge_type`, `context.semester_id`

#### Scenario: InvoiceDiscount được tạo
- **WHEN** `SettlementService::createOrRefreshInvoiceDiscount()` tạo mới InvoiceDiscount
- **THEN** một row được insert với `entity_type = 'invoice_discount'`, `event_type = 'created'`, `amount = discount.amount`, `context.discount_type`, `context.invoice_id`

#### Scenario: DiscountAllocation được tạo
- **WHEN** `SettlementService::synchronizeDiscountAllocations()` hoặc `ApplyEgcRetakeDiscountAction` tạo DiscountAllocation
- **THEN** một row được insert với `entity_type = 'discount_allocation'`, `event_type = 'allocated'`, `amount = allocation.amount`, `context.invoice_discount_id`, `context.invoice_line_id`

#### Scenario: Invoice snapshot được recalculate
- **WHEN** `SettlementService::recalculateInvoiceSnapshot()` được gọi
- **THEN** một row được insert với `entity_type = 'invoice_snapshot'`, `event_type = 'recalculated'`, `before_snapshot = {subtotal, discount_total, total_amount, paid_amount, status}` trước khi update, `after_snapshot = {...}` sau khi update

---

### Requirement: Audit log không block mutations nếu log fail
Hệ thống SHALL tiếp tục thực hiện mutation dù log operation fail. Log failure MUST được ghi vào Laravel application log (`Log::error`) nhưng MUST NOT throw exception hoặc rollback transaction.

#### Scenario: Log insert fail
- **WHEN** DB insert vào `finance_mutation_logs` throw exception
- **THEN** exception được catch và logged vào `Log::error`, mutation gốc vẫn hoàn thành bình thường

---

### Requirement: Audit log có thể query theo student và thời gian
Bảng `finance_mutation_logs` MUST có index trên `(student_id, created_at DESC)` để hỗ trợ query lịch sử theo student hiệu quả.

#### Scenario: Query log theo student
- **WHEN** query `FinanceMutationLog::where('student_id', $id)->orderByDesc('created_at')->limit(100)->get()`
- **THEN** query chạy sử dụng index, không full table scan
