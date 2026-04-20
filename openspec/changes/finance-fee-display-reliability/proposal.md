## Why

Trang fee summary (`/students/:id/academic-summary/fees`) chứa nhiều điểm tính toán sai hoặc không đáng tin: tuition charges không được link trực tiếp vào TuitionPlanTerm khi tạo, discount face value có thể cao hơn allocated thực tế, và không có audit log nào cho các thay đổi liên quan đến tiền. Ba vấn đề này cùng nhau tạo ra rủi ro hiển thị sai số dư và trạng thái thanh toán cho staff.

## What Changes

- **Fix estimated discount không ảnh hưởng payment_status**: `buildTuitionPlanChecklist` sẽ tách `projected_amount_due` (dùng estimated) khỏi `payment_status` (chỉ dùng actual data)
- **Fix `refreshInvoiceFromCharges` void không release payments**: Phải gọi `releaseLinePayments` và `releaseLineDiscounts` trước khi void line
- **Add `finance_mutation_logs`**: Bảng audit log ghi lại mọi thay đổi tiền với before/after snapshot
- **Remove `max(stored, live)`**: Fee summary query đọc stored values trực tiếp sau khi đảm bảo tất cả mutations đều gọi `recalculateInvoiceSnapshot()`
- **Fix Vue discount fallback**: Không fallback `allocated_amount || discount.amount` — hiển thị `allocated_amount` thực tế

## Capabilities

### New Capabilities

- `finance-mutation-audit-log`: Bảng `finance_mutation_logs` và `FinanceMutationLog` model để track mọi thay đổi tiền (charge creation/void, payment application/reversal, discount allocation, invoice snapshot recalculation)
- `tuition-charge-term-linking`: Xác nhận fallback `intake_major`-based term resolution đang đúng (275/275 charges verified); spec ghi lại expected behavior để regression test

### Modified Capabilities

- `fee-summary-display`: Thay đổi cách tính và hiển thị: bỏ `max()` logic, tách estimated discount khỏi payment_status, fix Vue fallback

## Impact

**Backend:**
- `app/Modules/Finance/Services/InvoiceGenerationService.php` — fix refreshInvoiceFromCharges void flow
- `app/Modules/Academic/Queries/GetStudentFeeSummaryQuery.php` — bỏ `max()`, fix estimated discount logic
- `app/Modules/Finance/Services/SettlementService.php` — add logging calls
- **New**: `app/Models/FinanceMutationLog.php`
- **New**: `database/migrations/..._create_finance_mutation_logs_table.php`

**Frontend:**
- `resources/js/pages/students/AcademicSummary/FeeTab.vue` — fix discount display fallback

**Tests:**
- Update `tests/Feature/Academic/GetStudentFeeSummaryQueryTest.php`
- New tests cho tuition charge term linking
- New tests cho audit log
