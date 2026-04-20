## Context

Trang fee summary hiện tại tính toán các con số tài chính theo 2 luồng song song và không nhất quán:
1. `GetStudentFeeSummaryQuery` dùng `max(stored_snapshot, live_computed)` — compensate cho stale data
2. `SettlementService::deriveInvoiceSnapshot()` là source of truth thực sự

Ngoài ra, tuition charges được tạo không có `source_type/source_id` link đến `TuitionPlanTerm`, buộc checklist phải dùng semester-count inference — logic này sai với deferred students.

Không có audit log nào cho thay đổi tiền, làm cho việc debug discrepancy rất khó.

## Goals / Non-Goals

**Goals:**
- Tuition charges tạo mới luôn có `source_type = TuitionPlanTerm, source_id = term.id`
- Fee summary query đọc stored values (không tự tính lại), trust DB snapshot
- `payment_status` trong checklist chỉ dùng actual applied discounts, không dùng estimated
- Mọi thay đổi tiền được ghi vào `finance_mutation_logs` với before/after state
- `refreshInvoiceFromCharges` không orphan payment applications khi void lines
- Frontend không fallback `discount.amount` khi `allocated_amount = 0`

**Non-Goals:**
- Không retroactively fix existing charges thiếu source_type link (handled by backfill command riêng nếu cần)
- Không thay đổi business logic thanh toán hoặc discount rules
- Không paginate fee summary query (performance concern riêng)
- Không expose audit log ra UI trong scope này

## Decisions

### D1: Trust stored snapshot, bỏ `max(stored, live)`

**Vấn đề**: `max()` compensates cho stale snapshot nhưng tạo ra false positives — nếu discount face value > allocated, `max()` pick face value và hiển thị sai.

**Quyết định**: Bỏ `max()`, đọc stored columns trực tiếp. Đảm bảo mọi mutation path đều gọi `recalculateInvoiceSnapshot()`.

**Tradeoff**: Nếu có mutation path chưa gọi recalculate, stored value sẽ stale và hiển thị sai. Mitigation: audit log giúp phát hiện missing recalculate calls.

**Thay thế đã xem xét**: Luôn derive live từ DB relations (bỏ stored snapshot) — rejected vì N+1 queries, performance issue.

---

### D2: Không thêm source_type/source_id vào tuition_term charges — giữ fallback hiện tại

**Vấn đề ban đầu**: Lo ngại rằng checklist dùng global semester count sẽ sai với deferred students.

**Phát hiện sau khi kiểm tra DB**: Fallback trong `buildTuitionPlanChecklist` đã dùng `intake_major` (không phải `intake_semester_id`) làm reference point. Kiểm tra toàn bộ 275 tuition_term charges → OK: 275 | MISMATCH: 0 | NO_TERM: 0. Fallback đang đúng 100% với data hiện tại và các plan structure (kể cả plan có term=0M là free semester).

**Quyết định**: Không thêm source_type/source_id. Không backfill. `semester_id` trên charge đã đủ để identify kỳ học; `term_number` chỉ là display label được resolve đúng qua fallback.

**Tradeoff chấp nhận**: Nếu trong tương lai có student genuinely defer 1 kỳ trên plan không có 0-amount term, `term_number` hiển thị sẽ là global offset thay vì study count. Đây là display-only, không ảnh hưởng số tiền hay payment logic. Không trong scope hiện tại.

**Thay thế đã xem xét**: Dùng charge-based count → rejected vì sẽ break 124 charges trên plans có 0-amount term (term 2=0), vì charge-rank 2 → term 2 (0M) ≠ charge amount (40-45M).

---

### D3: Tách `projected_amount_due` khỏi `payment_status`

**Vấn đề**: `buildTuitionPlanChecklist` dùng estimated scholarship discount để tính `amountDue`, từ đó tính `payment_status`. Kết quả: badge "Paid" khi discount chưa được apply.

**Quyết định**: Tách thành 2 fields:
- `amount_due` — chỉ từ actual `InvoiceDiscount` records (stored discount_total)
- `projected_amount_due` — dùng estimated scholarship (nullable, chỉ khi `is_estimated_discount = true`)
- `payment_status` — chỉ từ `amount_due` thực tế

**Tradeoff**: Checklist có thể show "Unpaid" cho term mà staff biết sẽ được discount. Mitigation: show `projected_amount_due` kèm label "(dự kiến)" rõ ràng hơn.

---

### D4: `finance_mutation_logs` — explicit logging, không dùng Model observers

**Vấn đề**: Model observers (boot hooks) khó trace context (không biết action nào trigger), dễ bị silent fail.

**Quyết định**: Explicit logging qua `FinanceMutationLog::record()` static method được gọi tại các action/service cụ thể:
- `SettlementService::createPaymentApplication()`
- `VoidFinanceChargeAction::handle()`
- `GenerateBatchChargesAction` (charge creation)
- `ApplyEgcRetakeDiscountAction::run()`
- `SettlementService::recalculateInvoiceSnapshot()` (before/after snapshot)

**Thay thế đã xem xét**: Laravel Activity Log package — rejected vì overhead, không cần UI actor log, muốn control schema.

---

### D5: Fix `refreshInvoiceFromCharges` — release trước khi void

**Vấn đề**: Direct `InvoiceLine::update(['status' => 'void'])` không qua `releaseLinePayments()`, để lại orphaned PaymentApplications.

**Quyết định**: Trước khi void mỗi line, gọi:
1. `settlementService->releaseLinePayments(line)`
2. `settlementService->releaseLineDiscounts(line)`

Sau đó mới `line->update(['status' => 'void'])`.

## Risks / Trade-offs

**[Risk] Existing charges không có source_type link** → Checklist vẫn dùng inference cho charges cũ. Mitigation: Trong scope này chỉ fix charges mới. Backfill là separate task với DBA review.

**[Risk] Stored snapshot stale từ mutation paths chưa phát hiện** → Fee page hiển thị số cũ. Mitigation: Audit log `recalculated` events giúp trace. Long-term: add test coverage cho mọi mutation path.

**[Risk] Semester count từ charges có thể sai khi backfill** → Nếu charge được tạo retroactively cho semester cũ, count sẽ không phản ánh đúng timeline. Mitigation: Backfill flow phải set `source_id` trực tiếp, không qua count logic.

**[Risk] Finance mutation logs tăng write load** → Mỗi PaymentApplication write thêm 1 log row. Mitigation: Index by `(student_id, created_at)`, không index toàn bộ. Partition by month nếu cần sau này.

## Migration Plan

1. Deploy Phase 1 (term linking + semester count fix + estimated discount fix + void fix):
   - Non-breaking: existing charges không bị ảnh hưởng
   - Charges mới sẽ có source_type link
   - Checklist display thay đổi cho estimated discount (less misleading)

2. Deploy Phase 2 (audit log):
   - Run migration để tạo `finance_mutation_logs` table
   - Logging bắt đầu từ deploy point, không retroactive

3. Deploy Phase 3 (display cleanup):
   - Bỏ `max()` — **cần verify** tất cả mutation paths gọi recalculate trước khi deploy
   - Vue fix: low risk, isolated change

**Rollback**: Mỗi phase là independent, có thể rollback riêng. Phase 3 (bỏ `max()`) là riskiest — cần có audit log từ Phase 2 để verify trước.

## Open Questions

- **Q1**: Với charges legacy không có source_type, có cần backfill command không? Nếu có, cần DBA sign-off trước khi chạy trên production.
- **Q2**: Semester count từ charges — nếu 1 student có charge bị void rồi recreate, count có bị lệch không? Cần test case cụ thể.
- **Q3**: `finance_mutation_logs` cần retention policy không? Hiện tại grow indefinitely.
