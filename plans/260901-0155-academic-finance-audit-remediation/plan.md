---
title: "academic-finance-audit-remediation"
description: "Khắc phục các vấn đề đã xác nhận trong docs/audit-academic-finance.md: sync payment P-01, gate thi lại live settlement, disposition hủy có kiểu, chặn trả góp, defer COURSES-scope (loại PARTIAL), chính sách EGC 50% động + bỏ credit 15M cứng, dọn dead code."
status: in-progress
priority: P1
effort: "10-11d"
created: 2026-09-01
blockedBy: [260831-1213-retake-resit-business-rule-alignment]
blocks: []
---

# academic-finance-audit-remediation

## Overview

Thực hiện các fix đã được owner chốt trong bảng **Decisions (2026-09-01)** của
`docs/audit-academic-finance.md`, cộng P-01 (bug sync thật, user duy nhất
2026-09-01 scope FULL) và dọn dẹp P-08/P-10/P-11. Mọi claim file:line dưới đây
đã được scout xác minh lại trên code hiện tại ngày 2026-09-01 (sau khi plan
`260831-1213` hoàn thành 4 phase) — 5 khu vực giữ nguyên hiện trạng audit,
4 khu vực đã đổi và được ghi chú tương ứng.

Bối cảnh kiến trúc bắt buộc (ADR-0026):

- Academic ↔ Finance tách bạch; liên kết qua source triple
  (`source_system='academic_system'`, `source_kind`, `source_ref`).
- Sự thật thanh toán = sổ cái Finance (`ObligationSettlementReader` /
  `AcademicObligationSettlement`); cờ `hq_fee_status` chỉ là projection hiển thị
  — "projection không bao giờ là điều kiện duy nhất của một hard gate".
- Hủy đi qua handoff outbox (`academic_finance_cancellation_handoffs` →
  `FinanceCancellationOperation`), xử lý bởi
  `ProcessFinanceCancellationOperationAction`.
- Cấm Academic tự sửa invoice/charge (`SettlementMutationGuard`).

## Goals

| # | Goal | Problem | Priority |
|---|------|---------|----------|
| 1 | Job reconcile/bridge DNG đồng bộ trạng thái Academic | P-01 | P1 |
| 2 | Gate thi lại (xếp lịch + ghi kết quả) dùng live settlement + 1 helper dùng chung | P-03, P-10 | P1 |
| 3 | Disposition hủy đã trả theo kiểu (typed), bỏ string matching; hủy retake 2 action | Q-04, Q-07, P-04 | P1 |
| 4 | Chặn trả góp cho phí không hỗ trợ (`supportsInstallments`) | P-05, Q-06 | P2 |
| 5 | Defer: COURSES-scope implement thật; PARTIAL bị loại khỏi hệ thống | Q-08 (sửa theo validation) | P1 |
| 6 | EGC retake discount = 50% động; bỏ flat credit 15M | Q-05, Q-12, P-02, P-12 | P1 |
| 7 | Baseline regression test chứng minh từng defect trước khi fix | — | P1 |
| 8 | Dead code + comment cũ + tài liệu | P-08, P-09, P-11 | P3 |

## Cross-Plan Dependencies

| Relationship | Plan | Lý do |
|---|---|---|
| blockedBy | `260831-1213-retake-resit-business-rule-alignment` | Phase 4 xây trên máy móc `fee_outcome` / `FinanceCancellationFeeDisposition.PaidReleaseToBalance` / idempotency guards do plan đó delivery (phase 2, Done). Plan đó còn status in-progress. |
| Phối hợp (không block) | `260818-2139-dng-push-over-collection-replacement` | Cùng đụng `CaptureDngProviderReceiptAction`. Chạy Phase 2 sau khi plan đó land, hoặc coordinate trước khi sửa. |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Baseline regression test chứng minh defect](./phase-01-start.md) | Done |
| 2 | [Phase 2: DNG fallback đồng bộ trạng thái Academic (P-01)](./phase-02-dng-reconciliation-syncs-academic.md) | Done |
| 3 | [Phase 3: Gate thi lại dùng live settlement (P-03, P-10)](./phase-03-resit-gates-live-settlement.md) | Done |
| 4 | [Phase 4: Disposition hủy có kiểu + retake 2 action (Q-04, Q-07, P-04)](./phase-04-structured-cancellation-disposition.md) | Done |
| 5 | [Phase 5: Chặn trả góp theo registry (P-05)](./phase-05-enforce-installment-eligibility.md) | Done |
| 6 | [Phase 6: Defer COURSES-scope + loại bỏ PARTIAL (Q-08)](./phase-06-defer-partial-and-courses-scope.md) | Done |
| 7 | [Phase 7: EGC 50% động + bỏ flat credit 15M (Q-05, Q-12)](./phase-07-egc-discount-and-credit-policy.md) | Pending |
| 8 | [Phase 8: Dead code, comment, tài liệu (P-08, P-09, P-11)](./phase-08-dead-code-and-docs-sweep.md) | Pending |

## Success Criteria

- [x] Webhook DNG mất → job 15 phút reconcile ghi nhận tiền VÀ bản ghi Academic chuyển `paid` (retake tự xếp lớp, resit xếp lịch được) — Phase 2.
- [x] Sinh viên đã trả (qua đường không-webhook) xếp lịch thi lại và ghi kết quả được mà không cần staff can thiệp; gate là live ledger, 2 gate dùng 1 helper — Phase 3.
- [x] Kết quả tiền khi hủy đã trả quyết định bởi trường typed trong handoff payload, typo trong reason text không đổi được kết quả; hủy retake đã trả có 2 action giữ/mất phí — Phase 4.
- [x] Split installment cho retake/resit fee bị từ chối với lỗi rõ ràng; học phí vẫn split bình thường — Phase 5.
- [x] Staff không thể chọn defer PARTIAL nữa (validation reject); COURSES-scope settlement thật cho charge gắn môn, invoice không gắn môn loại trừ + log review; guard discount (RULE-16) vẫn hiệu lực — Phase 6.
- [ ] EGC retake discount = đúng 50% phí block đích (đúng cả khi phí ≠ 15M); nút credit 15M biến mất, carry-forward là cơ chế duy nhất — Phase 7.
- [ ] `CancelFinanceObligationAction`, 2 Simple charge action, hằng số deprecated bị xóa; comment "DNG/fee worklist" sửa; `docs/audit-academic-finance.md` cập nhật trạng thái — Phase 8.
- [ ] `./scripts/dev.sh artisan test --compact --filter=Retake|Resit|Defer|Egc|Installment|Dng` xanh; `composer exec pint -- --dirty --format agent`; `./scripts/check-docs.sh` xanh.

## Scope decisions (chủ động không làm)

- **P-06** financial hold tự động — owner xác nhận manual by design (Q-01).
- **P-07** alert cho worklist kẹt — owner chọn ops định kỳ, không alert (Q-09).
- **P-09** grace 14 ngày — informational only; chỉ bổ sung ghi chú tài liệu (Q-02, Phase 8).
- **Q-10** giá production — owner tự cấp số liệu từ Pricing Operations, không sửa seed.
- **Q-11** scholarship auto-trigger — staff-only by design.
- 3 events Finance không listener (`ChargeFullySettled`, `InstallmentPushed`, `InstallmentPushFailed`) — **giữ nguyên** vì được dispatch từ code tiền sống; P-01 fix bằng gọi syncer trực tiếp, không qua listener. Ghi nhận quyết định ở Phase 8.

## Rủi ro tổng thể

- Phase 2/4 chạm đường tiền thật (reconcile, cancellation). Mọi mutation tiền đi
  qua Finance, tuân `SettlementMutationGuard`; syncer Academic là write projection,
  không đụng ledger.
- Phase 6 là khu vực ít được hiểu nhất (`DeferCaseService::processFeePolicy()`
  chưa từng được trace). Phase có gate dừng nếu trace phát hiện hành vi PARTIAL
  đã tồn tại trong toán học hiện tại. Semantics PARTIAL đã bị owner LOẠI
  (validation session 1) — chỉ còn PRESERVE FULL hoặc COURSES-scope.
- Phase 4 phải tương thích ngược với handoff đang chờ xử lý tạo trước khi có
  trường typed — bắt buộc fallback có log, không fallback âm thầm.

## Validation Log

_Red team đã chạy 2026-09-01 (xem `## Red Team Review` — 2/4 lens đầy đủ, 2 lens
bù verification inline). Khuyến nghị chạy `/ak:plan validate` trước khi cook._

## Red Team Review

### Session — 2026-09-01
**Reviewers:** 2 subagent hostile reviews hoàn chỉnh (Scope & Complexity Critic
+ Contract Verifier; Security Adversary + Fact Checker). 2 lens còn lại
(Failure Mode Analyst, Assumption Destroyer) bị chặn bởi rate limit tài khoản
cho toàn bộ subagent — bù bằng verification inline của controller: guard
terminal re-dispatch đọc trực tiếp (`ResumeFinanceCancellationOnPaidEvidenceAction`
xử lý cả `KeptPaidNoRefund` lẫn `PaidReleaseToBalance` là terminal), contract
settlement đọc trực tiếp (`AcademicObligationSettlement.forExamResit/isExamResitSettled`
tồn tại — Phase 3 khả thi). Khuyến nghị: chạy lại `/ak:plan red-team` đầy đủ
4 lens trước khi cook nếu muốn phủ kín.
**Findings:** 12 thô → 11 sau merge (11 accepted, 0 rejected)
**Severity breakdown:** 2 Critical, 5 High, 4 Medium

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | Xóa 2 Simple charge action = viết lại fixture của 11 test file + 2 helper, không phải "vài test reference" | Critical | Accept | Phase 8 (re-scope: test factory + migrate + re-estimate 2-2.5d) |
| 2 | Retake forfeit thiếu gate xác nhận "no refund" — plan giả sử sai rằng RULE-11 đã có sẵn trên retake | Critical | Accept | Phase 4 (thêm acknowledgement mirror resit) |
| 3 | Phase 7 quên test consumer của `ApplyEgcMajorEntryCreditAction` (1 arch test hard-code path + 2 feature test) | High | Accept | Phase 7 |
| 4 | Phase 1 không thể viết test đỏ cho defer chưa trace (`processFeePolicy()` chưa từng đọc) | High | Accept | Phase 1 bỏ 2 test defer; Phase 6 absorb sau trace |
| 5 | Resit `feeOutcome()` silently default FORFEIT khi thiếu field — mâu thuẫn criterion "2 action tường minh" | High | Accept | Phase 4 (bỏ silent default) |
| 6 | Phase 2: syncer throw `RuntimeException` trong batch reconcile → 1 student lỗi rollback settlement các student khác | High | Accept | Phase 2 (try/catch per-student) |
| 7 | Test baseline P-04 "typo" là phantom — `paid_void_reason` derive từ constant, typo không thể xảy ra runtime | High | Accept | Phase 1 + 4 (reframe: typed field authoritative) |
| 8 | `EgcExemptCredit` là type nền tảng (enum/registry/label/backfill), xóa registration vỡ dữ liệu lịch sử | Medium | Accept | Phase 7 (giữ registration, chỉ xóa đường tạo) |
| 9 | Permission `apply_egc_retake_adjustment` dùng chung với endpoint discount còn lại — không được xóa | Medium | Accept | Phase 7 |
| 10 | Base "50%" phải là net amount của charge đích, không phải resolver fee (FALLBACK 15M có thể lệch) | Medium | Accept | Phase 7 (charge net + guard lệch catalog) |
| 11 | `GateDecision` VO mới là over-engineering — đã có `ObligationSettlementResult` | Medium | Accept | Phase 3 (dùng DTO hiện có) |

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01..08 (sau khi áp findings).
- Decision deltas checked: 7 (Phase 1 còn 7 test; defer test chuyển Phase 6; Phase 2 batch isolation; Phase 3 DTO hiện có; Phase 4 acknowledgement + bỏ silent default + reframe P-04; Phase 7 giữ permission/type + base = charge net + test consumers; Phase 8 test factory migration + effort 2-2.5d).
- Reconciled stale references: effort tổng 9-10d → 10-11d (red-team +1.5d Phase 8, validation -1d Phase 6); bảng defect Phase 1 ghi rõ 7 hàng.
- Unresolved contradictions: 0.

### Session 1 — 2026-09-01
**Trigger:** `/ak:plan validate` sau red-team; verification pass bỏ qua theo
guard (Red Team Review đã có bằng chứng).
**Questions asked:** 4

#### Questions & Answers

1. **[Assumptions]** Semantics PARTIAL của defer: phần tiền vượt preserve amount xử lý thế nào?
   - Options: N = tiền bảo lưu, vượt forfeit | N = giới hạn phạm vi charge | Chưa chốt — để gate dừng Phase 6
   - **Answer (custom):** "loại bỏ bảo lưu 1 phần, chỉ có bảo lưu toàn phần hoặc bảo lưu theo môn học"
   - **Rationale:** Owner LOẠI chính sách PARTIAL — thay đổi phạm vi Q-08: không implement PARTIAL mà remove khỏi hệ thống.
2. **[Assumptions]** Defer COURSES-scope: charge không gắn môn (semester invoice) xử lý thế nào?
   - Options: loại trừ + log review | block nếu còn invoice dương | bao gồm cả invoice
   - **Answer:** Loại trừ invoice + log review.
   - **Rationale:** Giữ scope settlement đúng các charge gắn môn; invoice học kỳ không bị đụng ngoài ý muốn.
3. **[Risks]** Plan blockedBy `260831-1213` (in-progress, 4 phase Done). Bắt đầu thế nào?
   - Options: chạy Phase 1 ngay | chờ plan trước close | chạy toàn bộ ngay
   - **Answer:** Chờ plan trước close.
   - **Rationale:** blockedBy là ràng buộc nghiêm; không chạy phase nào trước khi `260831-1213` close.
4. **[Tradeoffs]** Phase 8: chiến lược thay fixture của 2 Simple charge action?
   - Options: factory mới + xóa action | giữ action làm test fixture
   - **Answer:** Factory mới + xóa action.
   - **Rationale:** Honor ADR-0026 + mục tiêu dead-code; chấp nhận +1.5d.

#### Confirmed Decisions
- Defer PARTIAL: LOẠI khỏi hệ thống (validation reject + Finance guard) — không implement.
- Defer COURSES-scope: invoice không gắn môn → loại trừ + log review.
- Sequencing: chờ `260831-1213` close trước khi bắt đầu bất kỳ phase nào.
- Phase 8: test factory mới bọc `FinanceIntakeContract`, migrate 11 file + 2 helper, xóa 2 Simple action.

#### Impact on Phases
- Phase 6: viết lại — PARTIAL bị loại (reject), COURSES-scope giữ; effort 2-2.5d → 1.5-2d.
- plan.md: goals #5, success criteria, risk, effort tổng 11-12d → 10-11d.
- Phase 1: không đổi (defer tests đã chuyển Phase 6 từ red-team).

### Whole-Plan Consistency Sweep (Session 1)
- Files reread: plan.md, phase-01..08 (sau khi áp decisions).
- Decision deltas checked: 4 (PARTIAL bị loại; COURSES invoice loại trừ + log review; sequencing chờ `260831-1213` close; Phase 8 factory + xóa action).
- Reconciled stale references: goals #5, Phases table row 6, success criteria Phase 6, frontmatter description, Rủi ro tổng thể, phase-06 viết lại toàn bộ, effort tổng.
- Unresolved contradictions: 0.
