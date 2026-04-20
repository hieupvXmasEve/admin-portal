## Phase 1 — Display Fix (Non-breaking)

> **Note**: Tasks 1.1 và 1.2 (source_type/source_id linking) đã được loại bỏ sau khi kiểm tra DB.
> Fallback trong `buildTuitionPlanChecklist` đang dùng `intake_major` và resolve đúng 100% (275/275 charges).
> `semester_id` trên charge đã đủ — không cần thêm explicit link.

### 1.1 Fix InvoiceGenerationService — release trước khi void
- **File**: `app/Modules/Finance/Services/InvoiceGenerationService.php`
- **Change**: Trong `refreshInvoiceFromCharges()`, với mỗi line cần void: gọi `$settlementService->releaseLinePayments($line)` rồi `$settlementService->releaseLineDiscounts($line)` trước khi `update(['status' => 'void'])`
- **Test**: Feature test — void line có payment → verify không có orphaned PaymentApplication

### 1.2 Fix buildTuitionPlanChecklist — tách estimated discount
- **File**: `app/Modules/Academic/Queries/GetStudentFeeSummaryQuery.php`
- **Change**: `$amountDue` chỉ dùng actual InvoiceDiscount (stored `discount_total`), không dùng estimated
- **Change**: Tạo `$projectedAmountDue` riêng cho estimated scholarship case
- **Change**: `$paymentStatus` chỉ tính từ `$amountDue` thực tế
- **Test**: Update `GetStudentFeeSummaryQueryTest` — assert payment_status = 'unpaid' khi discount chưa apply

### 1.3 Fix Vue discount fallback
- **File**: `resources/js/pages/students/AcademicSummary/FeeTab.vue`
- **Change**: Xóa `|| discount.amount` fallback, chỉ hiển thị `discount.allocated_amount`
- **Test**: Manual verify trang fee với student có discount chưa apply

---

## Phase 2 — Audit Log

### 2.1 Tạo migration finance_mutation_logs
- **File**: `database/migrations/YYYY_MM_DD_create_finance_mutation_logs_table.php`
- **Schema**: `id, entity_type, entity_id, event_type, student_id, actor_user_id (nullable), amount, before_snapshot (json), after_snapshot (json), context (json), created_at`
- **Index**: `(student_id, created_at DESC)`

### 2.2 Tạo FinanceMutationLog model
- **File**: `app/Models/FinanceMutationLog.php`
- **Change**: Implement static `record(array $data): void` — insert with try/catch, Log::error on failure, không throw
- **Test**: Unit test — DB exception khi insert → không throw, Log::error được gọi

### 2.3 Add logging vào SettlementService
- **File**: `app/Modules/Finance/Services/SettlementService.php`
- **Change**: Trong `createPaymentApplication()` — gọi `FinanceMutationLog::record()` sau khi insert
- **Change**: Trong `recalculateInvoiceSnapshot()` — snapshot before + after, log `invoice_snapshot.recalculated`
- **Change**: Trong `createOrRefreshInvoiceDiscount()` khi tạo mới — log `invoice_discount.created`
- **Change**: Trong `synchronizeDiscountAllocations()` khi tạo mới allocation — log `discount_allocation.allocated`

### 2.4 Add logging vào VoidFinanceChargeAction
- **File**: `app/Modules/Finance/Actions/VoidFinanceChargeAction.php`
- **Change**: Sau khi void hoàn thành — log `charge.voided` với before/after snapshot

### 2.5 Add logging vào GenerateBatchChargesAction
- **File**: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php`
- **Change**: Sau khi tạo charge thành công — log `charge.created` với amount, context.semester_id, context.charge_type

---

## Phase 3 — Remove max() Compensation (Deploy sau khi Phase 2 đã chạy ≥ 1 tuần)

### 3.1 Remove max() từ GetStudentFeeSummaryQuery
- **Precondition**: Verify qua audit log rằng tất cả mutation paths đều gọi recalculateInvoiceSnapshot()
- **File**: `app/Modules/Academic/Queries/GetStudentFeeSummaryQuery.php`
- **Change**: Trong `deriveInvoiceSnapshot()`, thay `max(stored, face_value)` bằng đọc trực tiếp `$invoice->discount_total`
- **Change**: Đọc trực tiếp `$invoice->subtotal`, `$invoice->total_amount`, `$invoice->paid_amount` từ stored columns
- **Test**: Update `GetStudentFeeSummaryQueryTest` — assert stored values được dùng, không live-computed
