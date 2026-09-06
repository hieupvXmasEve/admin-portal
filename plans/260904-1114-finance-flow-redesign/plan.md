---
title: "finance-flow-redesign"
description: "Thiết kế lại flow nghiệp vụ Finance cho người dùng cuối theo các quyết định owner chốt 2026-09-04 và 2026-09-06: trạng thái khoản thu hợp nhất, thu tay một thao tác + phiếu thu, thông báo học phí, vòng đời số dư, Finance Inbox hợp nhất; kèm sửa lỗi ưu tiên phân bổ."
status: in-progress
priority: P1
effort: "14-17d"
tags: [finance, ux, settlement, dng, notification]
blockedBy: []
blocks: []
created: 2026-09-04
---

# finance-flow-redesign

## Overview

Nghiệp vụ Finance hiện tại có lõi kế toán mạnh (Settlement Position là seam tính
tiền duy nhất, reservation + settlement version chống thu trùng, fail-closed)
nhưng **thiếu lớp vận hành**: không có trạng thái duy nhất cho người dùng cuối,
thu tay bị chẻ 3 khúc, ngoại lệ tán ra 6 worklist, số dư không có chủ. Plan này
bù đúng các chỗ đó **mà không sửa lõi kế toán**.

Quyết định nghiệp vụ (owner, phiên 2026-09-04):

| # | Quyết định |
|---|---|
| 1 | Flow phục vụ **cả staff và sinh viên/phụ huynh** (end-to-end) |
| 2 | **Không có lịch thu cố định** — staff quyết định hạn nộp từng đợt |
| 3 | **Nhắc nợ thủ công** như hiện tại (không thang bậc, không tự gửi) |
| 4 | SV chỉ **quét QR**; kế toán vẫn **ghi tiền mặt/CK** trong hệ thống |
| 5 | Chứng từ: **thông báo học phí + phiếu thu nội bộ** (không hoá đơn VAT, không GL) |
| 6 | **Phụ huynh được thanh toán** |
| 7 | **Không làm hoàn tiền** — còn học thì tiền giữ trong số dư |
| 8 | **Không có bên thứ ba trả hộ** |
| 9 | Chia đợt là **ngoại lệ theo lý do**; DNG mỗi lúc chỉ nhận **một** khoản = số tiền đợt hiện tại |
| 10 | SV rời trường mà còn dư ⇒ **vào hàng đợi chờ người có thẩm quyền quyết** |

Quyết định bổ sung (owner, phiên 2026-09-06) — thu hẹp phạm vi:

| # | Quyết định | Hệ quả |
|---|---|---|
| 11 | Mở thanh toán QR **và** trả góp Foxpay từ portal: **ai mở cũng được** (SV hoặc PH), không phân quyền riêng | Bỏ Phase 2 |
| 12 | **Không cần truy ai khởi tạo** khoản thanh toán — thanh toán được là đủ | Bỏ phần bằng chứng người trả |
| 13 | **Không dùng `billing_cycles`** — chưa cần thực thể đợt thu | Giữ. Nhưng xem sửa đổi 2026-09-06 bên dưới |

Sửa đổi sau red team (owner, 2026-09-06):

| # | Quyết định | Hệ quả |
|---|---|---|
| 14 | **Vẫn không có thực thể đợt thu**, nhưng phải có **một ngày hạn nộp duy nhất** do người chọn | Phase 5 quay lại với phạm vi tối thiểu: ghi ngày staff chọn lên invoice, bỏ 5 mặc định `now()+30d`. Không bảng mới. |

## Ràng buộc kiến trúc (không được vi phạm)

- ADR-0028: Settlement Position là seam duy nhất; `remaining = gross − discount
  − cash − credit`; settlement **suy dẫn**, không lưu; tiền mặt ≠ credit.
- ADR-0028: đợt trả góp là *kế hoạch thu*, không phải nguồn nợ; mỗi `fee_type`
  chỉ có **một** slot chưa trả; không có "trả số tiền tuỳ ý".
- ADR-0029/0026: `BillingAccount` là payer key; Academic ↔ Finance chỉ qua
  source triple; Academic không chạm tiền.
- ADR-0030: credit không bao giờ hiển thị là "đã thu"; áp credit bị chặn khi
  DNG đang thu vượt vị thế sau credit.
- ADR-0022: tiền dư mặc định **giữ làm số dư sinh viên**.

## Hiện trạng đã xác minh lại

Bốn nhận định trong phiên brainstorm sai so với code — đã kiểm lại:

| Nhận định trong phiên | Sự thật trên code |
|---|---|
| "Chặn trả góp retake/resit chưa enforce" | **Đã enforce** — `SplitChargeIntoInstallmentsAction.php:105-110` |
| "Defer PARTIAL là drift phải implement" | **Là thiết kế cố ý** — owner đã LOẠI PARTIAL (validation session plan `260901-0155`); `ApplyDeferFinancePolicyAction.php:43,125` |
| "Phụ huynh trả được là lỗ hổng phân quyền" | **Đúng ý đồ nghiệp vụ.** 4 route write của portal (`dngQr`, `dngInstallment`, `dngRequestQr`, `dngRequestInstallment`) **không mutate tiền**: chỉ đọc `DngPaymentRequest` đã `pushed_to_dng` rồi mint link provider; `CreateStudentDngPaymentAccessAction::assertRequestIsSafe():163-190` chặn cứng nếu status/`item_id`/số tiền lệch vị thế. Owner chốt (#11): ai mở cũng được |
| ~~"`now()+30d` chỉ lấp cột NOT NULL"~~ **← nhận định này SAI, red team 2026-09-06 lật đổ** | **`student_invoices.due_date` chính là ngày sinh viên thấy và ngày mọi phép quá hạn dùng.** `GetStudentFinancePresentationQuery.php:445` (`$invoice->due_date?->isPast() ? 'overdue' : 'open'`), `ListCollectionProgressQuery.php:250-251` (aging bucket), `CreateFinanceChargeAction.php:420` (trạng thái invoice). Ngày staff chọn ở `CommitBatchDngRequest.php:25` chỉ vào `dng_payment_requests`, **không** vào invoice. Tệ hơn: `InvoiceGenerationService.php:43-47` chỉ gán `due_date` khi `! $invoice->exists`, còn `GenerateBatchChargesAction::findReusableInvoice():457-465` tái dùng invoice cũ ⇒ **ngày của batch đầu tiên là vĩnh viễn**. ⇒ Phase 5 quay lại (phạm vi tối thiểu, không đợt thu) |
| ~~"`billing_cycles` là bảng chết"~~ **← SAI** | Không có writer, nhưng **6 đường đọc sống**, trong đó `InvoiceGenerationService.php:69-72` dùng `billing_cycle_id` để quyết charge nào thuộc invoice nào — đọc thành phần tiền, trong `SettlementMutationGuard`. Còn `BillingInvoiceController.php:101`, `StudentInvoiceResource.php:52` (payload sinh viên), `PayInvoiceRequest.php:38`, `FinanceAuditSearchRequest.php:26`, `GetFinanceAuditGraphQuery.php:55`. Quyết định "để nguyên, không xoá" vẫn đúng; **chữ "chết" thì sai** và không được viết vào ADR |
| ~~"`access_level` là cột chết"~~ **← SAI** | Là method trên public cross-module contract (`app/Shared/Contracts/Identity/GuardianAccessGrantWriter.php:20`), có action riêng (`ChangeGuardianAccessLevelAction.php:13-32` — nhận **bất kỳ** chuỗi non-empty, không allow-list), writer, DTO, audit, index, type FE. Đúng phải là: **được ghi và audit, cố ý không dùng làm cổng phân quyền** |
| "4 route portal không mutate tiền" | **Đúng một nửa.** Không ghi ledger local ✔, nhưng cả 2 handler POST ra ngoài (`DngClient.php:141,171`) và `installment` mở hợp đồng trả góp Foxpay đứng tên SV. Ngoài ra `assertRequestIsSafe` **bỏ qua** kiểm số tiền ở nhánh request không có reservation target (`CreateStudentDngPaymentAccessAction.php:183-184`), và 4 route **không có throttle** (`routes/api/v1/student.php:37-38` rate limiter bị comment). Quyết định #11 giữ nguyên; throttle là vấn đề tách biệt chưa xử lý |

Còn drift thật duy nhất (EGC 50% động, dead-code sweep) **đã thuộc plan khác**
— xem Cross-Plan Dependencies.

## Goals

| # | Goal | Quyết định | Priority |
|---|------|---|----------|
| 1 | Ưu tiên phân bổ tiền không thể nhận giá trị lạ, phủ đủ 8 loại phí, một nguồn duy nhất | — (lỗi tiền sai phát hiện trong phiên) | P1 |
| 2 | Thu tay = ghi nhận + phân bổ + phiếu thu trong một thao tác | #4, #5 | P1 |
| 3 | Một trạng thái khoản thu hợp nhất (6 giá trị, suy dẫn) dùng chung staff + portal | #1 | P1 |
| 3b | Một ngày hạn nộp duy nhất do người chọn (không có thực thể đợt thu) | #2, #14 | P1 |
| 4 | Thông báo học phí phát được cho SV + PH | #5 | P2 |
| 5 | Vòng đời số dư: chặn hoàn tiền, hàng đợi khi SV rời trường | #7, #10 | P2 |
| 6 | Finance Inbox hợp nhất + ADR + docs-site | #3, #9 | P2 |
| 7 | Bề mặt portal sinh viên (`FE/student-nuxt` — repo riêng) | #1 | P2 |

## Cross-Plan Dependencies

| Relationship | Plan | Lý do |
|---|---|---|
| Phối hợp (không block) | `260901-0155-academic-finance-audit-remediation` | Phase 7 (EGC 50% động) và Phase 8 (dead code + cập nhật `docs/audit-academic-finance.md` + ADR RULE-09) còn Pending. Plan này **không** chạm EGC discount và **không** viết lại audit doc; Phase 8 của plan này chỉ thêm ADR mới. Nếu hai plan chạy song song, thống nhất trước khi sửa `docs/audit-academic-finance.md` và `docs-site`. |

## Phases

Số phase giữ nguyên theo bản gốc (2 đã bỏ). Phase 5 quay lại sau red team với
phạm vi khác hẳn: **một ngày hạn nộp**, không phải thực thể đợt thu.

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Toàn vẹn ưu tiên phân bổ tiền](./phase-01-start.md) | Completed |
| 3 | [Phase 3: Thu tay một thao tác + phiếu thu nội bộ](./phase-03-manual-payment-single-action.md) | Completed |
| 4 | [Phase 4: Trạng thái khoản thu hợp nhất](./phase-04-unified-money-item-status.md) | Pending |
| 5 | [Phase 5: Một ngày hạn nộp duy nhất](./phase-05-single-due-date.md) | Pending |
| 6 | [Phase 6: Thông báo học phí](./phase-06-tuition-notice.md) | Pending |
| 7 | [Phase 7: Vòng đời số dư](./phase-07-surplus-lifecycle.md) | Pending |
| 8 | [Phase 8: Finance Inbox, ADR & tài liệu](./phase-08-finance-inbox-and-docs.md) | Pending |
| 9 | [Phase 9: Bề mặt portal sinh viên](./phase-09-student-portal-surface.md) | Pending |

## Success Criteria

- [x] `priority_order` từ mọi đường vào chỉ nhận giá trị thuộc enum loại phí và phủ đủ 8 loại debit; giá trị lạ bị từ chối chứ không xếp cuối im lặng — Phase 1.
- [x] Kế toán ghi một khoản tiền mặt/CK: tiền được phân bổ theo đúng ưu tiên, phiếu thu in được, và trạng thái Academic (retake/resit) đồng bộ ngay — Phase 3.
- [ ] Một khoản thu có **một** trạng thái hiển thị; portal và mọi trang staff đọc cùng nguồn; không còn map nhãn trùng trong `resources/js` — Phase 4.
- [ ] Mọi charge/invoice sinh ra mang **ngày hạn nộp do người chọn**; `grep -rn "addDays(30)" app/Modules/Finance` trả 0 — Phase 5.
- [ ] Thông báo học phí gửi được cho SV + PH, in được, số tiền khớp Settlement Position lúc phát; campus thiếu template ⇒ **fail rõ ràng**, không gửi boilerplate — Phase 6.
- [ ] Lối hoàn tiền bị chặn; SV tốt nghiệp/thôi học còn dư sinh việc trong Finance Inbox — Phase 7.
- [ ] Tám loại việc hiện trong Cockpit **có gate permission per-source** (user chỉ có permission X thấy đúng dòng của X); 2 ADR accept; `check-docs.sh` + `check-docs-freshness.sh` xanh — Phase 8.
- [ ] Portal (`FE/student-nuxt`) hiển thị "Đợt 1/3 · Hạn …" và không còn `snake_case` thô — Phase 9.
- [ ] Regression: `./scripts/dev.sh artisan test --compact --filter=Finance|Settlement|Dng|Payment|Invoice` xanh; `composer exec pint -- --dirty --format agent`.

## Scope decisions (chủ động không làm)

- **Phân quyền thanh toán của phụ huynh**: không làm (#11). Portal mở QR và trả
  góp Foxpay cho cả SV lẫn PH. `access_level` trên `guardian_access_grants`
  **được ghi và audit nhưng cố ý không dùng làm cổng phân quyền thanh toán** —
  không phải "cột chết" (nó là public cross-module contract). Phase 8 ghi câu
  chính xác đó vào ADR.
  **Throttle:** 4 route portal không có throttle trong khi mỗi call là POST
  outbound tới DNG/Foxpay ⇒ **thêm throttle ở Phase 8** (validation 2026-09-06).
  Đây là chống lạm dụng, không phải phân quyền — #11 giữ nguyên.
- **Bằng chứng người trả (payer identity)**: không làm (#12). Hệ quả biết trước:
  `ParentStudentAccess.php:70` `setUserResolver(fn () => $student)` khiến log
  không phân biệt được PH hay SV bấm; nếu sau này có tranh chấp thì không tra được.
- **Thực thể đợt thu (`billing_cycles`)**: không làm (#13, #14). Bảng + model để
  **nguyên trạng, không xoá** — nó có 6 đường đọc sống (một trong đó quyết thành
  phần tiền của invoice), nên xoá là rủi ro thật chứ không phải dọn rác.
  Thay vào đó Phase 5 làm ngày hạn nộp do người chọn — rẻ hơn nhiều và giải
  quyết đúng vấn đề nghiệp vụ.
  Báo cáo tiến độ giữ nguyên 6 chiều đang có (`by_fee_type`, `by_program`,
  `by_intake`, `by_balance_state`, `by_aging_bucket`, `by_lifecycle_exception`,
  lọc theo `semesterId`) — đủ trả lời "%thu / còn ai chưa nộp", và `by_aging_bucket`
  chỉ đúng **sau khi** Phase 5 land.
- **Hoàn tiền**: không xây workflow (#7) — chỉ chặn lối hiện có.
- **Thang nhắc nợ tự động / cron nhắc**: không làm (#3).
- **Lịch thu cố định theo kỳ**: không làm (#2).
- **Bên thứ ba trả hộ / payer thứ hai trên BillingAccount**: không làm (#8) — cần ADR riêng nếu sau này có nhu cầu.
- **Hoá đơn VAT / e-invoice / xuất sổ GL**: ngoài phạm vi (#5).
- **Financial hold tự động**: giữ thủ công (đã chốt Q-01 ở plan `260901-0155`).
- **EGC 50% động, dead-code sweep**: thuộc plan `260901-0155` Phase 7-8.
- **Đổi đường push trả góp sang DNG**: giữ nguyên (#9) — chỉ sửa nhãn portal.

## Rủi ro tổng thể

- Phase 1 và Phase 3 chạm đường tiền thật (phân bổ). Mọi mutation đi qua
  `SettlementMutationGuard`; không được thêm công thức số tiền mới.
- Phase 4 dễ bị làm sai thành "lưu trạng thái": bắt buộc suy dẫn từ
  Settlement Position, có test parity.
- Phase 6 thêm `type_key` vào enum đóng của module Notification — thay đổi
  cross-module, phải tuân contract của `docs/features/email/template-contract.md`,
  và phải seed template cho **mọi** campus đang hoạt động.

## Red Team Review

### Session — 2026-09-06
**Findings:** 15 sau dedupe (40 thô từ 4 reviewer) — 15 accepted, 0 rejected
**Severity breakdown:** 6 Critical, 8 High, 1 Medium
**Reviewers:** Security Adversary (Fact Checker), Failure Mode Analyst (Flow
Tracer), Assumption Destroyer (Scope Auditor), Scope & Complexity Critic
(Contract Verifier). Verification tier: Full (5+ phase).

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | Fail-closed trong sort dùng chung làm 500 hai query chỉ đọc (6 caller, 4 truyền hằng 4 phần tử không validate) | Critical | Accept | Phase 1 |
| 2 | `now()+30d` **là** ngày sinh viên thấy và ngày mọi phép quá hạn dùng; invoice tái dùng không bao giờ nhận ngày staff chọn | Critical | Accept | Phase 5 (mới), Phase 4, plan.md |
| 3 | Inbox không giữ được phân quyền: permission ở route middleware, query không có authorization | Critical | Accept | Phase 8 |
| 4 | Mọi deliverable portal nhắm `FE/student-nuxt` (repo lồng có `.git` riêng), 0 file được pin | Critical | Accept | Phase 9 (mới), Phase 4, Phase 8 |
| 5 | Template thiếu → nuốt lỗi → gửi boilerplate tiếng Anh không số tiền, đánh dấu đã gửi | Critical | Accept | Phase 6 |
| 6 | Dedup `content_hash` khoá vĩnh viễn lần phát lại sau khi gửi hỏng; V2 flag mặc định tắt ⇒ publish là no-op câm | Critical | Accept | Phase 6 |
| 7 | `runForStudents()` không có campus predicate — ghi tiền xuyên campus, trong đúng file Phase 1 sửa | High | Accept | Phase 1 (Security Considerations) |
| 8 | Claim "cột chết"/"bảng chết" sai cho cả `access_level` lẫn `billing_cycles`; ADR sẽ đóng dấu điều sai | High | Accept | Phase 8 (ADR), plan.md |
| 9 | `finance_tuition_notices` trùng `notification_messages` + `notification_deliveries.rendered_html` | High | Accept | Phase 6 (bỏ bảng) |
| 10 | `items_table` không thể thoả escape contract → HTML injection vào email SV/PH | High | Accept | Phase 6 |
| 11 | `SettlementMutationGuard` **chính là** transaction ⇒ "sync sau commit" bất khả; `store()` không có idempotency key | High | Accept | Phase 3 |
| 12 | `allocatePayment()` skip câm bằng `continue`, không trả lý do — yêu cầu "phần dư kèm lý do" không cài được | High | Accept | Phase 3 |
| 13 | Phase 7 tiêu chí đã xanh sẵn (reason/policy_code đã bắt buộc); `refund` có 16 consumer, plan liệt 7; 2 test sẽ đỏ | High | Accept | Phase 7 |
| 14 | Cockpit đã có 7 queue đúng shape ⇒ `ListFinanceInboxQuery` là viết lại; 6 paginator độc lập đọc global `request()` không ghép được | High | Accept | Phase 8 |
| 15 | docs-site có 4 locale (`zh/` bị bỏ sót); gate đúng là `check-docs-freshness.sh`; gate đỏ ở Phase 4 không phải Phase 8; không có convention in ấn (`@media print` = 0) | Medium | Accept | Phase 4, 8, 3, 6 |

**Số đếm plan trước sai, đã verify lại:**

| Hạng mục | Plan trước | Thực tế |
|---|---|---|
| Bản sao thứ tự ưu tiên | 3 | **7** (4 backend + 3 FE); thiếu `PreviewManualAllocationQuery:107-127` — chính là preview Phase 3 dùng |
| Consumer `settlement_label` | 2 call site | **5 class inject**, 17 điểm phát, 3 khai báo type FE, 1 test |
| Map nhãn FE trùng | 6 | **1** (`Invoices/Show.vue`); 4 file kia là domain khác |
| Consumer `refund` | 7 file | **16** điểm, gồm 1 chiều báo cáo + 2 test sẽ đỏ |
| Locale docs-site | 3 | **4** (thiếu `zh/`) |
| Loại việc chưa có queue | 8 | **2** (6 cái đã có trong Cockpit) |

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01, phase-03, phase-04, phase-05 (mới), phase-06, phase-07, phase-08, phase-09 (mới)
- Decision deltas checked: 6 (ngày hạn nộp; câu chữ ADR; tách portal; bỏ bảng notice; ranh giới transaction; Cockpit thay vì query mới)
- Reconciled stale references: 11
- Unresolved contradictions: 0

**Câu hỏi mở chuyển sang giai đoạn cook** (ghi trong từng phase, không chặn plan):
1. Phiếu thu nội bộ có cần số tuần tự không đứt quãng theo campus? (Phase 3)
2. `Payment::STATUS_REFUNDED` trong hay ngoài phạm vi chặn hoàn tiền? (Phase 7)
3. Có bản ghi disposition `refund` trong production không? (Phase 7)
4. Chiều báo cáo `refund` + `can_refund_surplus` giữ hay bỏ? (Phase 7)
5. Ai sở hữu `FE/student-nuxt`, deploy cùng cửa sổ không? (Phase 9)
6. Campus scoping cho `runForStudents()`: làm trong Phase 1 (+0.5d) hay tách issue bảo mật riêng? (Phase 1)
7. 4 route portal có thêm throttle không? (tách biệt với quyết định #11)

## Validation Log

### Session 1 — 2026-09-06
**Câu hỏi:** 6 (config `questions=3-8`)
**Verification pass:** bỏ qua theo guard — `## Red Team Review` đã có bằng chứng
đầy đủ cùng ngày; không còn tag `[UNVERIFIED]` nào trong plan.

**Giải được từ repo, không cần hỏi:**

| Câu | Kết luận | Bằng chứng |
|---|---|---|
| Convention rule validation | `app/Rules/` (Finance **không** có thư mục `Rules/`) | `app/Rules/CourseOffering` |
| `Payment::STATUS_REFUNDED` có phải lane hoàn tiền thứ hai? | **Không** — hằng khai báo, `grep -rn "refunded" app/` cho thấy **không chỗ nào gán** | `app/Modules/Finance/Models/Payment.php:62` |
| Portal deploy có tooling trong repo này? | Có | `scripts/deploy-fe.sh`, `scripts/portal-status.sh` |
| Dev DB có dòng `refund` không? | **0** dòng disposition mọi loại; **0** payment `refunded` | `artisan tinker` query |

**Quyết định đã chốt:**

| # | Câu hỏi | Quyết định | Áp vào |
|---|---|---|---|
| V1 | Thứ tự ưu tiên 8 loại debit | `tuition_term → egc_level_fee → bhyt → exam_resit_fee → retake_fee → admission_fee → manual_fee → adjustment` — phí gắn gate học vụ + bảo hiểm trước phí thủ công | Phase 1 |
| V2 | Lỗ hổng ghi tiền xuyên campus (`runForStudents()`) | **Sửa luôn trong Phase 1**, +0.5d. Đặt scoping ở **action** không phải controller (mọi caller đều cần) | Phase 1 |
| V3 | Throttle 4 route portal | **Thêm ở Phase 8**, limiter mới theo mẫu `merchandise-checkout`. Chống lạm dụng ≠ phân quyền; #11 không đổi | Phase 8 |
| V4 | Số phiếu thu tuần tự? | **Không** — ngẫu nhiên + retry như `invoice_number` (mẫu `CreateFinanceChargeAction:327-353`). Không sequence, không khoá ghi | Phase 3 |
| V5 | 16 consumer của `refund` | **Giữ phần đọc** (chiều báo cáo, `UnappliedCashReader`, các query Student360), **bỏ phần ghi + UI** (`can_refund_surplus`, nút hoàn tiền). Sửa 2 test | Phase 7 |
| V6 | Gate `check-docs-freshness.sh` đỏ ở Phase 4 | **Phase nào chạm file anchored thì phase đó cập nhật docs-site** ⇒ Phase 4 làm mới 4 trang finance-office. Mỗi phase merge rời vẫn xanh | Phase 4 |

**Ảnh hưởng effort:** Phase 1 `1.5d → 2d` (campus scoping). Tổng `13-16d → 14-17d`.

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01, phase-03, phase-04, phase-05, phase-06, phase-07, phase-08, phase-09
- Decision deltas checked: 6 (V1-V6)
- Reconciled stale references: 4 (Todo "cần owner chốt" ở Phase 1; Unresolved số phiếu ở Phase 3; Unresolved `STATUS_REFUNDED` ở Phase 7; câu ADR "chưa có throttle" ở Phase 8)
- Unresolved contradictions: **0**

**Câu hỏi mở còn lại (không chặn implementation):**
1. Có dòng `payment_surplus_dispositions` với `type='refund'` trong **production**
   không? Dev có 0; production là pilot-scale có dữ liệu thật. Kiểm ở Phase 7
   Implementation Step 2 trước khi land, không chặn plan.
2. `FE/student-nuxt` là repo git riêng nên commit/PR đi đường khác, dù deploy có
   script trong repo này. Xác nhận cửa sổ deploy khi tới Phase 9.
