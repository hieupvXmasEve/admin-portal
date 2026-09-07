---
phase: 7
title: "Vòng đời số dư"
status: completed
priority: P2
effort: "1-1.5d"
dependencies: [4]
---

# Phase 7: Vòng đời số dư

## Overview

Owner chốt: **không làm hoàn tiền** — sinh viên còn học thì tiền dư giữ trong
số dư; SV **tốt nghiệp hoặc thôi học mà còn dư** thì đưa vào hàng đợi chờ
người có thẩm quyền quyết. Hôm nay lối `refund` **đang mở** trong hệ thống, và
khoản dư không có chủ, không có mốc, không xuất hiện ở hàng đợi nào.

## Key Insights

- 3 disposition được staff gửi hôm nay:
  `app/Modules/Finance/Http/Requests/Student360/DisposePaymentSurplusRequest.php:24-28`
  → `reallocate` (perm `allocate_finance_payment`), `refund`
  (perm `refund_finance_payment`), `retain_forfeit`
  (perm `forfeit_finance_payment_surplus`). **`refund` reachable từ UI.**
- Model: `app/Modules/Finance/Models/PaymentSurplusDisposition.php:5-9`
  (`TYPE_REALLOCATE`, `TYPE_REFUND`, `TYPE_RETAIN_FORFEIT`).
- Action: `app/Modules/Finance/Actions/DisposePaymentSurplusAction.php`;
  entry point `FinanceStudentPaymentController::dispose:33-41`.
- Số dư suy dẫn: `StudentFinanceSettlementPositionReader` trả `unapplied_cash`
  (= `payment.amount − allocated − disposed(refund|retain_forfeit)`).
- ADR-0022: tiền dư **mặc định giữ làm số dư sinh viên** — quyết định của owner
  trùng ADR, không cần ADR mới cho phần này.
- ADR-0028/0030: `retain_forfeit` là "policy-authorized forfeiture/retention",
  phải là workflow được phê duyệt riêng.
- **SỬA nhận định sai của plan trước (red team 2026-09-06, High).** "Hiện chỉ có
  permission, không có bước phê duyệt" — sai. `reason` **và** `policy_code` đã
  `required_if:type,retain_forfeit` (`DisposePaymentSurplusRequest.php:31-32`);
  `reason`, `approved_by`, `evidence` (JSON), `audit_signature` (HMAC) đã được
  lưu (`DisposePaymentSurplusAction.php:70-71,84-88`); cột đã có sẵn từ
  `2026_07_11_230000_create_payment_surplus_dispositions_table.php:22-25`.
  ⇒ Migration thêm `reason`/`decided_by_user_id` là **no-op**, và tiêu chí "không
  thực hiện được khi thiếu lý do" **đã xanh hôm nay** = phantom test.
  Khoảng trống **thật sự** còn lại: người quyết định phải **khác** người thao tác
  (ADR-0028 "policy-authorized"), hiện `authorize()` chỉ là một `match` một
  permission (`:15-21`), không có khái niệm actor thứ hai.
- **`Payment::STATUS_REFUNDED` là hằng chết — đã xác minh (validation 2026-09-06).**
  Khai báo ở `app/Modules/Finance/Models/Payment.php:62` nhưng
  `grep -rn "refunded" app/` cho thấy **không chỗ nào trong `app/` gán giá trị
  này**. Không phải lane hoàn tiền thứ hai đang sống. `'status'` vẫn nằm trong
  `$fillable` (`:17-28`) nên rủi ro chỉ là lý thuyết (mass-assignment từ code
  tương lai). ⇒ **Ngoài phạm vi phase này**; ghi nhận trong ADR là hằng chưa dùng.
- **Dữ liệu dev (validation 2026-09-06):** `payment_surplus_dispositions` có **0
  dòng mọi loại**, `payments.status='refunded'` có **0 dòng**. Dev không phải
  production — bước kiểm dữ liệu ở Implementation Step 2 vẫn giữ.
- Trạng thái học vụ đọc qua `ProgramEnrollmentReader` (đã dùng ở
  `SendDueItemRemindersAction` để bỏ qua SV `deferred/dropout/left/…`).

## Requirements

- Functional: lối `refund` bị chặn (không nằm trong tập giá trị hợp lệ) với
  thông điệp rõ ràng "chính sách hiện tại không hoàn tiền".
- Functional: SV có `unapplied_cash > 0` **và** trạng thái học vụ là đã tốt
  nghiệp / thôi học / rời trường ⇒ sinh một **việc** trong hàng đợi Finance,
  không tự tất toán.
- Functional: `retain_forfeit` cần **người quyết định khác người thao tác**
  (lý do + `policy_code` **đã** bắt buộc hôm nay — không phải phạm vi mới).
- Functional: khi SV còn học, số dư giữ nguyên — **không** có mốc thời gian nào
  buộc xử lý (đúng quyết định owner).
- Non-functional: không thay đổi công thức `unapplied_cash`; hàng đợi là **read
  model suy dẫn**, không cột trạng thái mới trên `payments`.

## Architecture

1. **Chặn refund — đặt ở `authorize()`, không chỉ ở `rules()` (red team, Medium).**
   `authorize()` rẽ nhánh theo `$this->input('type')` và chạy **trước** `rules()`
   (`DisposePaymentSurplusRequest.php:15-21`, `default => false`). Nếu chỉ bỏ
   `refund` khỏi `Rule::in`, chỉ staff **có** `refund_finance_payment` mới nhận
   422 kèm thông điệp chính sách; mọi người khác nhận 403 trần, không hiểu vì sao.
   ⇒ Từ chối `refund` ngay trong `authorize()` (hoặc `prepareForValidation`) để
   **mọi** caller nhận cùng một phản hồi chính sách.
   **Giữ** hằng `TYPE_REFUND` + enum cột DB cho dữ liệu lịch sử. Ẩn lựa chọn UI.
2. **Hàng đợi "số dư cần quyết"**: query mới
   `ListUnresolvedSurplusQuery` = SV có `unapplied_cash > 0` ∩ trạng thái học vụ
   thuộc tập rời trường (qua `ProgramEnrollmentReader`, dùng cùng danh sách
   trạng thái mà reminder đang loại trừ). Suy dẫn, không bảng mới.
3. **Phê duyệt hai người** — phần duy nhất còn thiếu. `reason`/`policy_code`/
   `approved_by`/`evidence`/`audit_signature` **đã có**; **không** thêm migration.
   Việc cần làm: bắt buộc `approved_by != created_by_user_id` (người phê duyệt
   khác người thao tác) và khoản dư phải đã nằm trong hàng đợi trước khi tịch thu.
4. **Hiển thị**: thẻ "Số dư cần quyết" trong Finance Inbox (Phase 8) và trên
   Student 360 hiển thị rõ số dư + trạng thái học vụ.

## Related Code Files

- Modify: `app/Modules/Finance/Http/Requests/Student360/DisposePaymentSurplusRequest.php:24-28`
- Modify: `app/Modules/Finance/Actions/DisposePaymentSurplusAction.php`
- Modify: `app/Modules/Finance/Models/PaymentSurplusDisposition.php:13-17` (docblock nêu rõ `refund` bị vô hiệu theo chính sách)
- Create: `app/Modules/Finance/Queries/Operations/ListUnresolvedSurplusQuery.php`
- ~~migration thêm `reason`/`decided_by_user_id`~~ — **KHÔNG cần**, cột đã tồn tại (`2026_07_11_230000_...:22-25`)
- **Consumer `refund` chưa được liệt kê (red team, High — plan trước liệt 7, thực tế 16):**
  - `app/Modules/Finance/Queries/Reporting/GetRevenueByPeriodQuery.php:301` (chiều báo cáo `refund`)
  - `app/Modules/Finance/Support/Reporting/UnappliedCashReader.php:49,84,98,104,126`
  - `app/Modules/Finance/Queries/Student360/GetStudentFinanceReviewSignalsQuery.php:60,182`
  - `app/Modules/Finance/Queries/Student360/GetStudentFinancePaymentHistoryQuery.php:66`
  - `app/Modules/Finance/Queries/Student360/GetStudentFinanceOverviewKpisQuery.php:147`
  - `app/Modules/Finance/Support/StudentFinanceSettlementPositionReader.php:219`
  - `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php:92` (`can_refund_surplus`)
  - `resources/js/pages/Finance/Student360/Show.vue:285`, `resources/js/components/finance/student360/SurplusDispositionDrawer.vue:11,30,33`, `resources/js/types/finance.ts:392`
  - Test sẽ đỏ: `tests/Feature/Finance/Student360/PaymentSurplusDispositionTest.php`, `tests/Feature/Finance/Reporting/GetRevenueByPeriodQueryTest.php`
  **ĐÃ CHỐT (owner, validation 2026-09-06): giữ phần ĐỌC, chặn phần GHI.**
  - **Giữ nguyên**: chiều báo cáo `refund` trong `GetRevenueByPeriodQuery:301`,
    các nhánh `refund` trong `UnappliedCashReader`, `GetStudentFinanceReviewSignalsQuery`,
    `GetStudentFinancePaymentHistoryQuery`, `GetStudentFinanceOverviewKpisQuery`,
    `StudentFinanceSettlementPositionReader:219` — đọc lịch sử, sẽ luôn bằng 0
    nếu chưa từng dùng.
  - **Bỏ khỏi UI**: nút hoàn tiền `Student360/Show.vue:285`,
    `SurplusDispositionDrawer.vue:11,30,33`, cờ `can_refund_surplus`
    (`FinanceStudentOverviewController:92`, `resources/js/types/finance.ts:392`).
  - Sửa 2 test theo: `PaymentSurplusDispositionTest`, `GetRevenueByPeriodQueryTest`.
  <!-- Updated: Validation Session 1 - refund giữ đọc, chặn ghi -->
- Modify: `config/permission.php` — quyết định giữ hay ẩn `refund_finance_payment` (không xoá permission nếu còn gán cho role)

## Implementation Steps

1. Test đỏ: staff có `refund_finance_payment` gửi disposition `refund` → hôm nay
   thành công.
2. Kiểm dữ liệu: đã có bản ghi disposition `refund` trong DB chưa? Ghi kết quả
   vào phase (quyết định giữ hằng/enum).
3. Chặn `refund` ở request + action + UI; thông điệp rõ ràng.
4. `ListUnresolvedSurplusQuery` + test: SV `left/dropout/graduated` còn dư xuất
   hiện; SV đang học còn dư **không** xuất hiện.
5. Bắt buộc `approved_by != created_by_user_id` cho `retain_forfeit` (lý do +
   `policy_code` đã bắt buộc sẵn — **đọc schema trước khi viết test đỏ**).
6. Duyệt 16 consumer `refund` ở trên; sửa 2 test sẽ đỏ.
7. Test xanh.

## Todo

- [x] Test đỏ: `refund` hiện tại thực hiện được
- [x] Kiểm dữ liệu disposition `refund` đã tồn tại (ghi kết quả)
- [x] Chặn `refund` (request + action + UI + docblock)
- [x] `ListUnresolvedSurplusQuery` (suy dẫn, không bảng mới)
- [x] `retain_forfeit`: người phê duyệt khác người thao tác
- [x] ~~Chốt: giữ hay bỏ chiều báo cáo `refund`~~ — **giữ đọc, bỏ UI** (validation 2026-09-06)
- [x] ~~Chốt: `Payment::STATUS_REFUNDED` trong hay ngoài phạm vi~~ — **ngoài**, hằng chết chưa ai gán
- [x] Duyệt 16 consumer + sửa 2 test đỏ
- [x] Test xanh + `pint --dirty`

## Success Criteria

- [x] Gửi disposition `refund` → cùng một phản hồi chính sách cho **mọi** caller,
      kể cả staff không có `refund_finance_payment` (không phải 403 trần).
- [x] SV đã rời trường còn dư xuất hiện trong hàng đợi; SV đang học thì không.
- [x] `retain_forfeit`: một staff chỉ có `forfeit_finance_payment_surplus`
      **không** tự hoàn tất được; cần người thứ hai ghi quyết định
      (`approved_by != created_by_user_id`).
      *(Tiêu chí cũ "thiếu lý do thì không làm được" đã bị bỏ — nó xanh sẵn.)*
- [x] `unapplied_cash` không đổi công thức (test parity với Settlement Position).
- [x] Staff campus A không thấy dòng số dư của campus B trong hàng đợi.

## Risk Assessment

- **Trung bình:** chặn `refund` là **thu hẹp năng lực đang tồn tại**. Nếu dữ
  liệu cho thấy nghiệp vụ đã dùng thật thì phải báo owner trước khi land (quyết
  định #7 nói không làm hoàn tiền, nhưng dữ liệu lịch sử vẫn phải đọc được).
- **Trung bình:** `ListUnresolvedSurplusQuery` phải **có campus scoping**. Ba
  worklist hiện có xử lý `app('campus')` null theo 3 kiểu khác nhau — một kiểu bỏ
  luôn bộ lọc (`ListLifecycleDueExceptionsQuery.php:30,43-46`). Quy tắc cho query
  mới: campus null ⇒ **từ chối** trừ khi có `view_finance_all_campus`, theo mẫu
  `FinanceStudentPaymentController::assertCampusVisible():66-78`.
- **Thấp:** danh sách trạng thái "rời trường" phải lấy đúng nguồn Academic
  (`ProgramEnrollmentReader`), không tự định nghĩa lại. Lưu ý N+1:
  `SendDueItemRemindersAction.php:62` gọi `forStudentId()` **trong vòng lặp**;
  query mới phải dùng dạng batch.

## Security Considerations

`retain_forfeit` biến tiền của sinh viên thành doanh thu ⇒ bắt buộc lý do +
dấu vết người quyết định (ADR-0028 yêu cầu workflow được phê duyệt riêng).

## Unresolved questions

- Có bản ghi `payment_surplus_dispositions` với `type='refund'` trong
  **production** không? Dev (2026-09-07): **0** dòng `refund`, **0** disposition
  mọi loại. Production chưa truy vấn được từ session này. Giữ enum `TYPE_REFUND`
  + permission `refund_finance_payment` (không xoá). Hàng đợi leaver dùng
  `graduated|dropout|dropout_transfer` (không gồm deferred). `approved_by` là
  user id người thứ hai; `created_by_user_id` ghi trong `evidence` (không có cột).

## Next Steps

Phase 8 hiển thị hàng đợi này trong Finance Inbox và ghi quyết định chính sách
vào ADR.
