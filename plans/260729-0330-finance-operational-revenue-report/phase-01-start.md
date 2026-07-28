---
phase: 1
title: "Revenue Query"
status: pending
priority: P1
effort: "1d"
dependencies: []
---

# Phase 1: Revenue Query

## Overview

Query gộp doanh thu theo học kỳ, đọc qua `SettlementPositionReader` để số liệu khớp theo cấu trúc với Collection Progress. Đây là phần rủi ro nhất của plan — làm xong và có test là coi như xong 70%.

## Requirements

Functional:
- Trả về 1 aggregate / học kỳ, sort theo `semesters.start_date` giảm dần.
- Mỗi kỳ: `gross`, `discount`, `net_billed`, `cash`, `credit`, `outstanding`, `collection_rate`, `invalid_count`, `invalid_gross`.
- `growth_pct`: so `net_billed` với kỳ liền trước trong danh sách đã sort. Kỳ cuối cùng (không có kỳ trước) → `null`, không phải `0`.
- Breakdown `by_campus` và `by_fee_type`, cả trong từng kỳ lẫn tổng.
- Dải `unattributed`: tiền chưa gán invoice, refund, forfeit — không thuộc kỳ nào.
- Filter: `semester_ids[]` (rỗng = mọi kỳ), `campus_id`, `fee_type`.

Non-functional:
- Luôn toàn trường. Không đọc `app('campus')`.
- Tiền cộng bằng `Money`, không dùng float trung gian.

## Architecture

### Luồng

1. Load candidate lines:
   ```php
   InvoiceLine::query()
       ->join('student_invoices', 'student_invoices.id', '=', 'invoice_lines.invoice_id')
       ->whereNotIn('student_invoices.status', ['cancelled', 'void'])
       ->when($semesterIds !== [], fn ($q) => $q->whereIn('student_invoices.semester_id', $semesterIds))
       ->get(['invoice_lines.id', 'student_invoices.semester_id', 'student_invoices.student_id']);
   ```
   Giữ map `line_id → [semester_id, student_id]` trong PHP.

   **Neo học kỳ = `student_invoices.semester_id`** (quyết định validation #4). `finance_charges` cũng có `semester_id` riêng; hiện 0/988 dòng lệch nhưng không có ràng buộc DB nào chặn. Chọn invoice vì đó là thứ Collection Progress dùng — nếu neo vào charge thì test đối chiếu ở bước 3 mất ý nghĩa.

2. `StudentReferenceReader::findMany($studentIds)` → `campusId` mỗi sinh viên. Line có student không resolve được thì bỏ, đếm vào `unresolved_student_count`.

3. Một lần gọi:
   ```php
   $positions = $reader->batch(array_map(
       fn (int $id): SettlementPositionScope => SettlementPositionScope::payableLine($id),
       $lineIds,
   ));
   ```
   `batch()` gom mọi scope cùng snapshot thành **một** query `InvoiceLine` + 3 query evidence. Không N+1.

   Dùng scope `payableLine` (không phải `invoice` hay `payableLines`) vì hai scope kia gộp nhiều line thành một position và sẽ bắn `MISMATCHED_BILLING_ACCOUNT` khi các line thuộc billing account khác nhau — làm hỏng cả cụm. Per-line thì một line hỏng chỉ ảnh hưởng chính nó.

4. Fold theo `(semester_id, campus_id, fee_type)`:
   - `$position->valid === true` → cộng `amounts` vào bucket tiền.
   - `$position->valid === false` → tăng `invalid_count`, cộng `raw_evidence->gross` vào `invalid_gross`. **Không cộng vào bucket tiền.**
   - `fee_type` lấy từ `$position->fee_type` (null → `'—'`).

5. Dải `unattributed` (query riêng, không đi qua reader):
   - Chưa gán: mỗi `payments` (status `completed`), `amount − Σ payment_applications.amount − Σ payment_surplus_dispositions.amount (refund|retain_forfeit)`, clamp `max(0, …)`.
   - Refund / forfeit: `Σ payment_surplus_dispositions.amount` theo `type`. **Chỉ đọc `payment_surplus_dispositions`**, không đọc `payments.status='refunded'` (quyết định validation #3) — bảng dispositions có `audit_signature`, `approved_by`, `idempotency_key`, và đó là nguồn `ListCollectionProgressQuery` đang dùng.

6. `growth_pct` tính sau khi đã sort, trong PHP.

### Vì sao không SQL

Xem "Quyết định kiến trúc" trong [plan.md](./plan.md). Ghi comment tại đầu query:

```php
// ponytail: đọc qua SettlementPositionReader thay vì SQL aggregate — ~1k invoice_lines
// hiện tại nên chi phí không đáng kể, và tránh bản cài đặt thứ hai của công thức
// settlement (11 validity gate) lệch âm thầm khỏi Collection Progress.
// Nếu vượt ~50k line: thay bước 3 bằng CTE gộp cash/discount/credit theo line,
// nhưng phải port đủ validity gate và thêm test đối chiếu với reader.
```

### Bẫy

`gross <= 0` → invalid. Đó là cơ chế loại dòng `finance_charges` âm legacy. Không được cộng thẳng `amount_snapshot` ở bất cứ đâu.

`credit` không phải doanh thu và không phải tiền thu (ADR-0030). Cột riêng, tuyệt đối không gộp vào `cash`.

## Related Code Files

- Create: `app/Modules/Finance/Queries/Reporting/GetRevenueByPeriodQuery.php`
- Create: `app/Modules/Finance/Support/Reporting/UnappliedCashReader.php` — công thức tiền chưa phân bổ dùng chung
- Create: `app/Modules/Finance/Support/Reporting/RevenuePeriodBucket.php` — chỉ tách khi query vượt ~200 dòng
- Create: `tests/Feature/Finance/Reporting/GetRevenueByPeriodQueryTest.php`
- Modify: `app/Modules/Finance/Queries/Reporting/ListCollectionProgressQuery.php` — thay thân `unappliedByStudent` bằng lời gọi `UnappliedCashReader`
- Read trước khi viết: `app/Modules/Finance/Support/SettlementPosition/CurrentPayableSettlementPositionReader.php`, `SettlementPositionAmounts.php`, `Money.php`

### Phạm vi tách `unapplied` (quyết định validation #1)

Công thức này lặp ở **~10 query file** (`ListPaymentsQuery`, `GetStudentFeeSummaryQuery`, `GetPaymentDetailsQuery`, `Student360/*`, `Operations/GetBillingDashboardStatsQuery`, …). Phase này **chỉ** đưa 2 query reporting về dùng chung: Revenue mới + `ListCollectionProgressQuery`.

8 file còn lại **để nguyên**. Hợp nhất toàn bộ là refactor riêng đụng Student360, payment history và KPI — ngoài phạm vi plan này. Đừng mở rộng khi đang code phase 1.

## Implementation Steps

1. Test trước (RED): hai học kỳ, hai campus, mỗi kỳ có line hợp lệ + một line `gross <= 0` + một invoice `cancelled`. Assert `net_billed` chỉ gồm line hợp lệ, `invalid_count = 1`, invoice cancelled không xuất hiện.
2. Viết `GetRevenueByPeriodQuery::handle(array $filters): array` theo luồng trên.
3. Test đối chiếu (quan trọng nhất): với 1 kỳ + 1 campus, `cash` của revenue query == `paid_total` của `GetCollectionProgressSummaryQuery`. Test này là thứ chặn hồi quy nếu ai đó sau này thay bước 3 bằng SQL.
4. Tạo `UnappliedCashReader` mang công thức unapplied. Đổi `ListCollectionProgressQuery::unappliedByStudent` thành lời gọi tới nó. **Chỉ 2 site này** — xem "Phạm vi tách" ở trên. Lấy baseline test Finance **trước** khi sửa, rồi chạy lại `tests/Feature/Finance/Reporting/CollectionProgressViewTest.php` và so với baseline.
5. Thêm breakdown `by_campus` / `by_fee_type`.
6. Thêm dải `unattributed` + test riêng: payment thu 100, gán 60, refund 10 → chưa gán 30.
7. `growth_pct` + test: kỳ cũ nhất `null`, kỳ có `net_billed` kỳ trước = 0 → `null` (không chia cho 0).

## Success Criteria

- [ ] Line `gross <= 0` không nằm trong bất kỳ cột tiền nào, đếm vào `invalid_count`
- [ ] Invoice `cancelled`/`void` bị loại
- [ ] `credit` là cột riêng, không cộng vào `cash`
- [ ] Test đối chiếu với `GetCollectionProgressSummaryQuery` pass
- [ ] `growth_pct` = `null` khi không có kỳ trước hoặc kỳ trước = 0
- [ ] Dải `unattributed` khớp công thức unapplied hiện có
- [ ] Không đọc `app('campus')` (grep để chắc)

## Risk Assessment

| Rủi ro | Giảm thiểu |
|---|---|
| Số lệch Collection Progress | Test đối chiếu ở bước 3 là gate chặn. Chạy trước khi sang phase 2. |
| Tách `unapplied` làm vỡ Collection Progress | Chạy `tests/Feature/Finance/Reporting/CollectionProgressViewTest.php` ngay sau khi tách. Suite Finance **có sẵn test đỏ từ trước** — số chính xác chưa verify trong session này, nên phải chụp baseline trước khi sửa rồi mới so, đừng tin con số ghi sẵn ở đâu đó. |
| Bị cuốn vào hợp nhất cả 10 site unapplied | Phạm vi đã chốt: đúng 2 site. Nếu thấy site thứ 3 hấp dẫn, dừng và mở plan riêng. |
| `batch()` chậm khi data lớn | Đã ghi ceiling + đường nâng cấp trong comment. Không tối ưu trước. |
| Test dùng `--env=testing` xoá nhầm DB dev | **KHÔNG** chạy artisan với `--env=testing`. Repo không có `.env.testing` nên nó trỏ vào DB `asia`. Chạy test qua đường chuẩn của repo. |
