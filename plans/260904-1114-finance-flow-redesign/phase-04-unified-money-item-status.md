---
phase: 4
title: "Trạng thái khoản thu hợp nhất"
status: completed
priority: P1
effort: "2-2.5d"
dependencies: [5]
---

# Phase 4: Trạng thái khoản thu hợp nhất

## Overview

Một khoản tiền hôm nay có tới 10 máy trạng thái song song (obligation, charge,
invoice cache, settlement suy dẫn, DNG request, installment, credit/discount
entitlement 2 trục, defer case, cancellation operation). Không có trạng thái
nào trả lời câu hỏi của người dùng cuối: *"khoản này đang ở đâu, tôi phải làm
gì?"*. Phase này thêm **một** lớp trạng thái hợp nhất, **suy dẫn** (không lưu),
dùng chung cho staff và portal, và xoá các map nhãn trùng ở frontend.

## Key Insights

- Backend **đã** có một chỗ hợp nhất nhãn:
  `SettlementPositionWorklistPresenter::summarize()` (dòng 30-62) sinh
  `settlement_label` (`Cần kiểm tra` / `Còn phải thu` / `Còn dư` / `Đã thu` /
  `Đã giảm trừ`), dùng lại ở `ListSettlementWorklistQuery.php:205,211`.
  Đây là seam để mở rộng, không phải viết mới.
- Nguồn số liệu: `app/Modules/Finance/Support/StudentFinanceSettlementPositionReader.php`
  — output keys (docblock :33-54): `valid, scope_type, scope_id,
  billing_account_id, position_mode, captured_at, snapshot_version,
  settlement_state, gross, discount, net_due, cash_applied, credit_applied,
  remaining_collectible, unapplied_cash, total_cash_received, status,
  money_actions_available, student_message, issues`.
- 6 settlement state (`SettlementPosition.php:5-11`): `missing, invalid, unpaid,
  partially_settled, settled_by_cash, settled_by_reduction`.
- 23 issue code (`SettlementPositionIssue.php:8-27`).
- 4 trạng thái DNG giữ slot (`DngPaymentRequest::HOLDING_COLLECTION_STATUSES`):
  `pending, pushed_to_dng, unknown_outcome, needs_review`.
- **Sửa lại số liệu (red team 2026-09-06, High).** Plan trước nói "trùng nhãn ở
  tối thiểu 6 chỗ". Verify: `grep -rn "settled_by_cash" resources/js` trả **2 hit,
  cùng 1 file** (`pages/Finance/Invoices/Show.vue:127,136`). Đó là map
  settlement-state **duy nhất** ở FE. Bốn file còn lại map **domain khác**, xoá
  đi là hỏng:
  `pages/Finance/Charges/Show.vue:141-148` = trạng thái `FinanceChargeInstallment`
  (`pending/awaiting_payment/paid/cancelled`);
  `pages/Finance/Operations/Dashboard.vue:210-228` = `student_invoices.status` legacy;
  `pages/Finance/Operations/DueCalendar.vue:151-163` = bucket theo hạn
  (`upcoming/due_today/overdue/paid`), không phải settlement state;
  `pages/Finance/Invoices/Index.vue:96-108` = hàm chọn **variant** badge, không phải map nhãn.
  `resources/js/types/finance.ts:104-158` **không** chứa map settlement-state
  (chỉ `CHARGE_TYPE_LABELS`, `INVOICE_STATUS_LABELS`, `CHARGE_STATUS_LABELS`,
  `PAYMENT_*`) — tiền đề "map chuẩn ở types" cũng sai.
  ⇒ Dedup FE là **1 file**, không phải 6. Không dùng dedup làm lý do biện minh
  cho một contract mới.
- **Consumer thật của `settlement_label` (plan trước đếm 2, thực tế 5 class inject)**:
  `ListSettlementWorklistQuery` (`:179,210,292`), `ListDngWorklistQuery`
  (`:147,170,515,529,681,741,788`), `GetBillingDashboardStudentsQuery`
  (`:169,182,280`), `GetBillingDashboardStatsQuery` (inject `:19`),
  `PreviewManualAllocationQuery` (`:141`). Cộng 3 khai báo type FE
  (`Operations/Settlement.vue:35,61`, `Operations/Dashboard.vue:72`) và
  `tests/Feature/Finance/SettlementPosition/StaffWorklistConsumersTest.php:101,117`.
  Mở rộng presenter ⇒ phải duyệt cả 5 + test đó.
- Frontend map cũ (ngữ cảnh lịch sử): `resources/js/types/finance.ts:104-158`
  (map chuẩn), `pages/Finance/Invoices/Show.vue:121-131`,
  `pages/Finance/Charges/Show.vue:141-156`,
  `pages/Finance/Operations/Dashboard.vue:210-228`,
  `pages/Finance/Operations/DueCalendar.vue:151-164`,
  `pages/Finance/Invoices/Index.vue:96-104`.

## Requirements

- Functional: 6 trạng thái hiển thị, suy dẫn theo thứ tự ưu tiên xác định:
  1. `Đang rà soát` — position không valid / `missing` / DNG `needs_review` /
     `unknown_outcome` (che số tiền không tin cậy với SV).
  2. `Đã điều chỉnh` — obligation `cancelled/voided/superseded` hoặc charge void.
  3. `Đã hoàn tất` — `remaining ≤ 0` (`settled_by_cash` / `settled_by_reduction`
     / hỗn hợp), vẫn tách rõ *đã nộp* và *được giảm*.
  4. `Đang xử lý thanh toán` — DNG ở trạng thái giữ slot, hoặc có tiền chưa
     phân bổ liên quan.
  5. `Quá hạn` — còn phải thu và qua hạn nộp **do người chọn** (Phase 5).
  6. `Chờ thanh toán` — còn phải thu, còn trong hạn, **hoặc không có ngày hạn
     nào do người chọn** (không bịa quá hạn từ ngày mặc định).
- Functional: staff thấy thêm issue code + evidence + hành động sửa; SV/PH chỉ
  thấy thông điệp trung tính (bề mặt SV giao ở **Phase 9**).
- Non-functional: **suy dẫn, không lưu**; không cột cache mới; không công thức
  tiền mới.
- Non-functional: một nguồn nhãn duy nhất cho FE.

## Architecture

0. **Nguồn ngày hạn nộp — phụ thuộc Phase 5 (red team 2026-09-06, Critical).**
   Plan trước liệt "hạn nộp" làm input mà không chỉ nguồn. Có **3** ứng viên với
   ngữ nghĩa khác nhau: `student_invoices.due_date` (ngày mặc định `now()+30d`,
   là ngày portal đang render — `GetStudentFinancePresentationQuery.php:445`),
   `dng_payment_requests.due_date` (staff chọn lúc commit, chỉ có sau khi push),
   `finance_charge_installments.due_date` (theo đợt trả góp).
   Reader mà phase này lấy số liệu **không có** khoá ngày nào:
   `StudentFinanceSettlementPositionReader.php:31-51`.
   ⇒ Phase 5 làm ngày trên invoice trở thành ngày do người chọn; Phase này đọc
   **một** nguồn đó. Quy tắc, ghi vào truth table: đợt trả góp có ngày riêng thì
   dùng ngày đợt; không có ngày do người chọn ⇒ **không** có trạng thái quá hạn.
1. **Contract — mở rộng presenter, không tạo class mới (red team, High).**
   `SettlementPositionWorklistPresenter::summarize()` (`:26`) đã sinh
   `settlement_label` (`:59`) và đã có 5 consumer. Thêm `money_item_status` vào
   chính nó thay vì dựng `MoneyItemStatusResolver` — một abstraction chỉ có một
   caller. Giữ khoá `settlement_label` cho consumer cũ.
   Chữ ký mở rộng nhận **một VO input tường minh** (position + obligation/charge
   state + DNG holding + due date), để hàm thuần và test được.
2. `SettlementPositionWorklistPresenter::summarize()` gọi resolver mới thay vì
   tự nối chuỗi; giữ khoá `settlement_label` để không phá consumer hiện tại,
   thêm khoá `money_item_status`.
3. Portal: `GetStudentFinancePresentationQuery` /
   `GetStudentPortalFinanceSummaryQuery` trả `money_item_status` cho từng khoản
   (dùng `label_student`), giữ nguyên hợp đồng `settlement_position` hiện có ở
   `docs/api/student/finance.md`.
4. FE: `resources/js/types/finance.ts` giữ **duy nhất** map nhãn; 5 map cục bộ
   ở các trang bị xoá và đọc `money_item_status` từ server.
5. Từ vựng người học (Phase 8 áp cho portal): `retake_fee` → "Phí học lại môn",
   `exam_resit_fee` → "Phí thi lại", `credit` → "Nhà trường đã giảm",
   `unapplied_cash` → "Số dư của bạn".

## Related Code Files

- Modify: `app/Modules/Finance/Support/SettlementPosition/SettlementPositionWorklistPresenter.php:26-62` (thêm `money_item_status`, giữ `settlement_label`)
- Create: VO input tường minh cho `summarize()` (position + obligation/charge state + DNG holding + due date)
- Create: batch loader phân giải DNG holding theo `invoice_line` cho cả trang (tránh N+1 ở `ListSettlementWorklistQuery.php:197-202`)
- Kiểm 4 consumer khác của presenter: `ListDngWorklistQuery`, `GetBillingDashboardStudentsQuery`, `GetBillingDashboardStatsQuery`, `PreviewManualAllocationQuery:141`
- Modify (test hiện có): `tests/Feature/Finance/SettlementPosition/StaffWorklistConsumersTest.php:101,117`
- Modify: `docs-site/src/content/docs/{,en/,ko/,zh/}finance-office/index.md` (4 locale — `Invoices/Index.vue` nằm trong `source:`)
- Modify: `app/Modules/Finance/Support/SettlementPosition/SettlementPositionWorklistPresenter.php:30-62`
- Modify: `app/Modules/Finance/Queries/Operations/ListSettlementWorklistQuery.php:205,211`
- Modify: `app/Modules/Finance/Queries/GetStudentFinancePresentationQuery.php`
- Modify: `app/Modules/Finance/Queries/GetStudentPortalFinanceSummaryQuery.php`
- Modify: `resources/js/types/finance.ts:104-158`
- Modify (xoá map cục bộ): `resources/js/pages/Finance/Invoices/Show.vue:121-131`, `pages/Finance/Charges/Show.vue:141-156`, `pages/Finance/Operations/Dashboard.vue:210-228`, `pages/Finance/Operations/DueCalendar.vue:151-164`, `pages/Finance/Invoices/Index.vue:96-104`
- Modify: `docs/api/student/finance.md` (thêm `money_item_status`)

## Implementation Steps

1. Viết bảng chân lý (truth table) cho 6 trạng thái × (6 settlement state × DNG
   holding × quá hạn × obligation lifecycle) và đưa vào test dạng data provider
   **trước** khi viết resolver.
2. Cài resolver; parity test: với mọi tổ hợp, `money_item_status` không được
   mâu thuẫn `settlement_state` (ví dụ không thể `Đã hoàn tất` khi
   `remaining > 0`).
3. Nối vào presenter; giữ `settlement_label` tương thích ngược.
4. Nối vào 2 query portal; cập nhật hợp đồng API.
5. FE: xoá 5 map cục bộ; đọc trạng thái từ server; giữ badge class theo map chuẩn.
6. Test: SV nhìn khoản `invalid` **không** thấy số tiền, chỉ thấy thông điệp.

## Todo

- [x] Truth table 6 trạng thái + test data provider (gồm dòng "không có ngày hạn")
- [x] `money_item_status` trên presenter + VO input (không tạo class resolver riêng)
- [x] Batch loader DNG theo invoice_line (chống N+1)
- [ ] Làm mới 4 trang docs-site finance-office — skipped: Invoices/Index.vue not changed, freshness gate stays green
- [x] Presenter dùng resolver, giữ `settlement_label`
- [x] Portal trả `money_item_status`; cập nhật `docs/api/student/finance.md`
- [x] Xoá map nhãn settlement-state ở Invoices/Show.vue (file duy nhất có settled_by_cash); không xoá map installment/due-calendar
- [x] Test parity + test che số tiền khi invalid
- [x] `pint --dirty` + eslint/prettier cho file FE đã sửa

## Success Criteria

- [x] Không có cột DB mới nào lưu trạng thái hợp nhất.
- [x] Test parity: không tổ hợp nào cho trạng thái mâu thuẫn Settlement Position.
- [x] `grep -rn "settled_by_cash" resources/js/pages` trả 0 (nhãn đã tập trung).
- [x] Portal: khoản `invalid` hiển thị thông điệp trung tính, không có số tiền.

## Risk Assessment

- **Trung bình:** dễ bị hiện thực thành cột cache. Success criteria đầu tiên là
  gate chống việc này.
- **Cao:** input DNG chưa có ở call site (red team, Medium). `summarize()` hôm
  nay nhận `(SettlementPosition, bool $hasSurplus = false)`; call site mức invoice
  (`ListSettlementWorklistQuery.php:197-202`) gọi trong `map()` **không truyền
  `$hasSurplus`** và DNG được batch theo **student+fee_type**
  (`:194-196`), không theo invoice. Tra DNG bên trong `summarize()` = 1 query mỗi
  dòng invoice. Cần một batch loader phân giải DNG holding theo invoice_line cho
  cả trang **trước** vòng map — thêm bước này vào Implementation Steps.
- **Cao:** `unapplied_cash` là đại lượng mức **payment/sinh viên**, không quy
  cho khoản nào (red team, Medium). Trạng thái #4 kích hoạt bởi "có tiền chưa
  phân bổ liên quan" ⇒ hoặc mọi khoản còn nợ đều lật sang `Đang xử lý thanh toán`,
  hoặc một khoản không xác định lật. Cả hai phá lời hứa "một khoản, một trạng
  thái". **Quy tắc bắt buộc ghi vào truth table:** chỉ khoản được reservation
  target của DNG trỏ tới mới mang tín hiệu này; không khoản nào khác.
- **Trung bình:** thứ tự ưu tiên giữa `Quá hạn` và `Đang xử lý thanh toán` là
  quyết định sản phẩm, không phải kỹ thuật: khoản quá hạn mà DNG đang giữ slot
  hiển thị `Đang xử lý thanh toán` (ưu tiên 4 trước 5) để SV không bị giục khi
  tiền đang trên đường. Ghi vào test parity.
- **Thấp:** consumer cũ đọc `settlement_label` — giữ khoá này.

## Security Considerations

`label_student` không được chứa issue code, tên bảng, hay số tiền không tin cậy
(ADR-0028: student surface chỉ nhận thông điệp trung tính).

## Next Steps

Phase 9 áp từ vựng người học lên portal (`FE/student-nuxt` — repo riêng).

## Ghi chú phạm vi (red team 2026-09-06)

- Bề mặt **portal** của phase này thuộc `FE/student-nuxt` (repo lồng, có `.git`
  riêng, không phải submodule) — **không** phải `resources/js`. Mọi deliverable
  portal chuyển sang Phase 9; phase này chỉ giao khoá backend + FE staff.
- `resources/js/pages/Finance/Invoices/Index.vue` mà phase này sửa nằm trong
  `source:` frontmatter của **4** trang docs-site (vi/en/ko/zh) ⇒
  `./scripts/check-docs-freshness.sh` sẽ đỏ ở **phase này**, không phải Phase 8.
  **ĐÃ CHỐT (owner, validation 2026-09-06):** phase nào chạm file anchored thì
  phase đó cập nhật docs-site ⇒ làm mới 4 trang finance-office **ngay trong phase
  này**, để mỗi phase merge rời vẫn xanh.
  <!-- Updated: Validation Session 1 - Phase 4 sở hữu việc làm mới docs-site -->
