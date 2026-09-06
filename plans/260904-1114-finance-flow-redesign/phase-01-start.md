---
phase: 1
title: "Toàn vẹn ưu tiên phân bổ tiền"
status: completed
priority: P1
effort: "2d"
dependencies: []
---

# Phase 1: Toàn vẹn ưu tiên phân bổ tiền

## Overview

Thứ tự ưu tiên phân bổ tiền hiện có **ba bản sao** và **không validate**, nên
một chuỗi sai chính tả làm đổi thứ tự phân bổ **im lặng**; đồng thời cả ba bản
chỉ phủ 4/8 loại phí debit, khiến thứ tự của 4 loại còn lại là hệ quả phụ chưa
ai quyết định. Phase này biến ưu tiên phân bổ thành một chính sách duy nhất,
có enum, phủ đủ.

## Key Insights

- Backend const: `app/Modules/Finance/Services/PaymentService.php:30-35` —
  `tuition_term, egc_level_fee, retake_fee, manual_fee` (private const).
- Bulk path nhận mảng chuỗi tự do từ staff:
  `app/Modules/Finance/Http/Requests/Operations/ApplySettlementRequest.php:19-20`
  (`'priority_order' => ['required','array']`, `'priority_order.*' => ['string']`),
  `app/Modules/Finance/Http/Web/Admin/PaymentController.php:226-243`,
  `app/Modules/Finance/Http/Web/Admin/BillingSettlementController.php:28-30`.
- Sắp xếp: `app/Modules/Finance/Services/SettlementService.php:680-715`;
  `:704` `$priorityRank[$chargeType] ?? $fallbackRank` — loại không có trong
  danh sách dồn vào **một bucket cuối**, thứ tự nội bộ do `due_date` → tuổi
  hồ sơ (`:706-713`).
- Enum đầy đủ 8 loại debit: `resources/js/types/finance.ts:89`
  (`tuition_term | egc_level_fee | retake_fee | exam_resit_fee | manual_fee |
  admission_fee | adjustment | bhyt`); phía backend là hằng `TYPE_*` trên
  `app/Modules/Finance/Models/FinanceCharge.php` + `ObligationTypeRegistry`.
- **Bản sao thứ 4 ở backend (red team, plan trước đếm thiếu)**:
  `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php:107-127`
  — `array_flip(AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)` cộng tuple
  sắp xếp y hệt `SettlementService`. **Đây chính là query Phase 3 dùng làm
  preview cho kế toán xác nhận** ⇒ nếu bỏ sót, preview xếp theo thứ tự cũ còn
  thực thi xếp theo thứ tự mới: tiền vào khoản khác với khoản kế toán đã duyệt.
- Tổng số bản sao thứ tự/rank: **7** (4 backend + 3 FE), không phải 3.
- Bản sao FE: `resources/js/pages/Finance/Operations/Settlement.vue:172`
  (literal cứng), `resources/js/pages/Finance/Payments/AutoAllocate.vue:58-62`
  (default kéo-thả), `resources/js/pages/Finance/Payments/Components/AutoAllocateDialog.vue:26-29`
  (nhãn tiếng Anh).
- Nhãn sai trên chính màn hình xếp ưu tiên: `AutoAllocate.vue:61` gắn
  `retake_fee` = "Phí thi lại" (đúng phải là *học lại*; `exam_resit_fee` mới là
  thi lại — nhãn chuẩn đã có ở `resources/js/types/finance.ts:106-111`).

## Requirements

- Functional: `priority_order` chỉ nhận mã loại phí debit hợp lệ; giá trị lạ →
  lỗi validation, **không** rơi vào bucket cuối.
- Functional: danh sách phải phủ **đủ** 8 loại debit; thiếu loại → lỗi
  validation (không có thứ tự ngầm).
- Functional: một nguồn duy nhất — server trả danh sách mặc định; FE không
  hardcode danh sách hoặc nhãn.
- Functional: nhãn hiển thị lấy từ map chuẩn duy nhất, `retake_fee` = "Phí học
  lại môn".
- Non-functional: không đổi công thức số tiền; không đổi thứ tự sắp xếp phụ
  (due_date → created_at → id) ngoài phần rank.

## Architecture

1. Đưa danh sách ưu tiên lên **public API của Finance**:
   `ObligationTypeRegistry::allocationPriorityOrder()`. Danh sách loại debit
   **đã có sẵn** — `ObligationTypeRegistry::chargeTypes()`
   (`app/Modules/Finance/Support/ObligationType/ObligationTypeRegistry.php:75-81`,
   lọc `isDebit()`) trả đúng 8 loại khớp `FinanceCharge::TYPE_*` (`:49-63`).
   **Không** lấy danh sách từ `resources/js/types/finance.ts` — TS không phải
   nguồn sự thật. Lưu ý: CHECK constraint DB
   (`2026_06_14_000003_add_finance_money_sign_checks.php:35`) còn dung thứ một
   giá trị legacy `course_fee` không có trong registry — thêm một lý do nữa để
   giữ fallback ở đường đọc (Architecture 3).
2. Validation: `ApplySettlementRequest` + 2 chỗ `validate()` inline trong
   `PaymentController` dùng `Rule::in(<enum>)` và kiểm **đủ số lượng + không
   trùng** (custom rule dùng chung, ví dụ `AllocationPriorityOrder`).
3. **Fail-closed CHỈ ở biên ghi, KHÔNG ở sort dùng chung.**
   `SettlementService::getOutstandingLinesForStudent()` **giữ** `?? $fallbackRank`.
   Lý do (red team 2026-09-06, Critical): sort này có **6 caller**, trong đó 4
   caller truyền hằng `AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER` (chỉ
   **4 phần tử**) mà không FormRequest nào validate — 2 caller là query **chỉ
   đọc**. Bỏ fallback biến một SV có `bhyt`/`exam_resit_fee`/`admission_fee`/
   `adjustment` thành **500 trên trang Student 360**, không phải 422 trên form.
   Ngoài ra `$line->charge?->charge_type ?? ''` (`SettlementService.php:702`) trả
   chuỗi rỗng khi quan hệ charge null — cũng sẽ ném.
   Fail-closed đặt ở: rule validation của 3 đường vào (staff nhập), và một
   assert **một lần trước vòng sort** nếu vẫn muốn phát hiện sớm — báo lỗi kèm
   danh sách charge id vi phạm, không ném từ trong comparator.
4. FE: server-side props cung cấp `allocation_priority_options` (id + label từ
   map chuẩn); xoá literal ở `Settlement.vue`, xoá default ở `AutoAllocate.vue`
   và `AutoAllocateDialog.vue`.

**Thứ tự chính sách — ĐÃ CHỐT (owner, validation 2026-09-06):**

```
tuition_term → egc_level_fee → bhyt → exam_resit_fee
  → retake_fee → admission_fee → manual_fee → adjustment
```

Lý do: phí gắn gate học vụ (thi lại / học lại chặn xếp lịch) và bảo hiểm bắt
buộc ưu tiên cao hơn phí thủ công. Đây là hằng số chính sách, viết vào
`ObligationTypeRegistry::allocationPriorityOrder()` kèm comment giải thích thứ tự.
<!-- Updated: Validation Session 1 - chốt thứ tự ưu tiên 8 loại debit -->

## Related Code Files

- Modify: `app/Modules/Finance/Services/PaymentService.php:30-35,231-233`
- Modify: `app/Modules/Finance/Services/SettlementService.php:680-715`
- Modify: `app/Modules/Finance/Http/Requests/Operations/ApplySettlementRequest.php:19-20`
- Modify: `app/Modules/Finance/Http/Web/Admin/PaymentController.php:226-243`
- Modify: `app/Modules/Finance/Http/Web/Admin/BillingSettlementController.php:28-30`
- Modify: `app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php:21-26` (`DEFAULT_PRIORITY_ORDER` — đồng bộ với nguồn mới; hằng 4 phần tử này là thứ 4 caller truyền vào sort)
- Modify: `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php:107-127` (gọi `SettlementService::getOutstandingLinesForStudent()` thay vì tự sắp xếp lại)
- Kiểm (không nhất thiết sửa) 4 caller đọc/ghi khác của sort dùng chung: `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php:143`, `app/Modules/Finance/Queries/Student360/GetStudentFinanceReviewSignalsQuery.php:196`, `app/Modules/Finance/Actions/VoidFinanceChargeAction.php:154`, `app/Modules/Finance/Queries/Operations/PreviewAutoAllocateQuery.php:92`
- Modify (test hiện có): `tests/Feature/Finance/AutoAllocatePaymentsTest.php`
- Create: `app/Rules/AllocationPriorityOrder.php` — convention đã xác minh: repo dùng `app/Rules/` (`app/Rules/CourseOffering`), module Finance **không** có thư mục `Rules/`
- Modify: `app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php:65-78` — **thêm campus scoping** (xem Security Considerations)
- Modify: `resources/js/pages/Finance/Operations/Settlement.vue:172`
- Modify: `resources/js/pages/Finance/Payments/AutoAllocate.vue:58-62`
- Modify: `resources/js/pages/Finance/Payments/Components/AutoAllocateDialog.vue:26-29`
- Modify (nếu cần bổ sung nhãn): `resources/js/types/finance.ts:104-158`

## Implementation Steps

1. Test đỏ: gửi `priority_order` chứa `tuition_termm` (typo) qua
   `finance.payments.auto-allocate` → hôm nay trả 200 và đổi thứ tự; test phải
   chứng minh hành vi này trước khi sửa.
2. Test đỏ: charge `exam_resit_fee` + `manual_fee` cùng outstanding → hôm nay
   `manual_fee` được phân bổ trước; ghi lại hành vi.
3. Chốt thứ tự chính sách 8 loại (Todo dưới) rồi đưa vào nguồn duy nhất.
4. Thêm rule validation dùng chung; áp cho cả 3 đường vào.
5. **Giữ** `?? $fallbackRank` trong sort; đồng bộ `DEFAULT_PRIORITY_ORDER` lên
   đủ 8 loại để 4 caller không-validate không còn xếp ngầm.
6. Cho `PreviewManualAllocationQuery` gọi sort dùng chung (xoá bản sao thứ 4).
7. FE: nhận options từ server, xoá 3 bản sao, sửa nhãn `retake_fee`.
8. Chạy test đã đỏ → xanh; thêm test phủ đủ 8 loại theo thứ tự chính sách; thêm
   test **parity preview ↔ thực thi**: cùng SV, cùng dữ liệu → thứ tự khoản của
   `PreviewManualAllocationQuery` khớp thứ tự `SettlementService`.
9. Chạy `tests/Feature/Finance/AutoAllocatePaymentsTest.php`.

## Todo

- [x] ~~Chốt thứ tự ưu tiên 8 loại debit~~ — **đã chốt** (validation 2026-09-06), xem Architecture
- [x] Campus scoping cho `runForStudents()` + test cross-campus
- [x] Test đỏ: typo trong `priority_order` đổi thứ tự im lặng
- [x] Test đỏ: loại ngoài danh sách bị xếp cuối
- [x] Nguồn ưu tiên duy nhất (public) + rule validation dùng chung
- [x] `DEFAULT_PRIORITY_ORDER` lên đủ 8 loại; sort dùng chung **giữ** fallback
- [x] `PreviewManualAllocationQuery` dùng sort dùng chung (xoá bản sao thứ 4)
- [x] Test parity preview ↔ thực thi
- [x] FE đọc options từ server; xoá 3 bản sao; nhãn `retake_fee` đúng
- [x] Test xanh + `pint --dirty`

## Success Criteria

- [x] Gửi `priority_order` có giá trị lạ → 422, không có allocation nào xảy ra.
- [x] Gửi `priority_order` thiếu loại → 422.
- [x] `grep -rn "'tuition_term', 'egc_level_fee'" resources/js` trả 0 kết quả.
- [x] Không còn nhãn `retake_fee` = "Phí thi lại" trong `resources/js`.
- [x] Thứ tự phân bổ 8 loại có test khẳng định, đúng thứ tự chính sách đã chốt.
- [x] Staff campus A gửi `student_ids` của campus B → bị từ chối, 0 allocation.
- [x] Trang Student 360 của SV có `bhyt`/`exam_resit_fee`/`admission_fee`/`adjustment` vẫn tải bình thường (không 500) — test hồi quy cho 2 query chỉ đọc.
- [x] Preview phân bổ tay và thực thi trả **cùng** thứ tự khoản.

## Risk Assessment

- **Trung bình:** đổi thứ tự ưu tiên **thay đổi kết quả phân bổ thật** cho các
  student còn nhiều khoản. Không backfill/không phân bổ lại lịch sử; chỉ áp cho
  allocation mới. Nêu rõ trong changelog nội bộ.
- **Thấp:** FE mất default cứng → phải bảo đảm props luôn có; fallback là danh
  sách rỗng + nút disabled, không phải danh sách đoán.

## Security Considerations

`priority_order` là input staff không được validate hôm nay — đây là đường
tác động trực tiếp tới phân bổ tiền. Sau phase này input đóng theo enum.

**Lỗ hổng liền kề, KHÔNG đóng bởi phase này (red team 2026-09-06, Critical).**
Trong cùng file phase này sửa: `ApplySettlementRequest.php` có `authorize()` trả
`true` và `student_ids.*` chỉ validate `exists:students,id`; controller đẩy thẳng
sang `AutoAllocatePaymentsAction::runForStudents()` (`:65-78`) — nhánh này
**không có campus predicate** (nhánh `run()` thì có, `:41-47`). Staff campus A
truyền `student_ids` của campus B ⇒ ghi payment application xuyên campus.
Phase này **không** được tuyên bố "input surface đã đóng" nếu chưa xử lý. Hai lựa
**ĐÃ CHỐT (owner, validation 2026-09-06): sửa luôn trong phase này, +0.5d.**
Thêm campus scoping vào `AutoAllocatePaymentsAction::runForStudents()` — **đặt ở
action, không ở controller**, vì `PaymentController::allocate` và mọi caller
tương lai đều cần. Theo mẫu `FinanceStudentPaymentController::assertCampusVisible():66-78`,
tôn trọng `view_finance_all_campus`. Test bắt buộc: staff campus A + `student_ids`
campus B → 403/404 và **0** dòng `payment_applications` được tạo.
Sau đó Phase 1 mới được phép nói "input surface đã đóng".
<!-- Updated: Validation Session 1 - campus scoping vào Phase 1 -->

## Next Steps

Phase 3 dựa trên đường auto-allocate đã đúng để gộp thu tay một thao tác.
