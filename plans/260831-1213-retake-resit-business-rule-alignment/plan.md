---
title: "retake-resit-business-rule-alignment"
description: "Căn chỉnh luồng học lại / thi lại với 8 quy tắc nghiệp vụ đã chốt: giá học lại theo bảng giá Finance, hủy đã trả tiền (retake chưa xếp lớp; thi lại 2 action mất phí / giữ phí dùng sau), sửa giới hạn thi lại, bổ sung ghi nhận no_show."
status: in-progress
priority: P1
effort: "6-8d"
created: 2026-08-31
blockedBy: []
blocks: []
---

# retake-resit-business-rule-alignment

## Overview

Khảo sát 2026-08-31 (read-only, 5 scout + thẩm định trực tiếp) phát hiện 4 nhóm lệch giữa code và quy tắc nghiệp vụ đã được owner chốt:

| # | Quy tắc nghiệp vụ | Hiện trạng code | Lệch |
|---|---|---|---|
| 1 | Giá học lại theo bảng giá Finance, bỏ `unit.retake_fee` | Gate `unit.retake_fee > 0` chặn đăng ký; snapshot hiển thị sai khác số thu thật | Bỏ gate; UI đọc giá từ dữ liệu Finance đã lưu |
| 2 | Thi lại 1 lần hiện tại, sau có thể tăng | `IN_FLIGHT_OR_CONSUMED_STATUSES` chứa `completed` → max_attempts vô hiệu | Tách block-list khỏi limit; enforce theo `attempt_number` |
| 3 | Rớt chuyên cần được thi lại | Đã khớp | Không làm |
| 4 | Học lại đã trả tiền, chưa xếp lớp → được hủy, tiền dùng cho phí phát sinh sau; hủy thi lại đã trả có 2 action: mất phí / giữ phí dùng sau | `CANCELLABLE_STATUSES` không có `paid`; resit chỉ có 1 luồng hủy đã trả (no-refund, giữ charge) | Mở hủy từ `paid` (retake) + 2 action hủy resit |
| 5 | Học lại chỉ xếp lớp vào kỳ đã đăng ký | Đã khớp (link guard khớp semester) | Không làm — thêm regression test |
| 6 | Không hoàn tiền | Đã khớp (`paid_no_refund`) — "giữ phí dùng sau" vẫn không hoàn về DNG | Bổ sung action giữ phí |
| 7 | Giảm giá EGC fix cứng 7.5tr/50% | Đã khớp (hằng số) | Không làm |
| 8 | `no_show` = trượt, được học lại; môn thi lại coi như trượt, MẤT phí | Không có đường ghi nhận no_show; gate học lại chỉ nhận `completed` | Thêm write action + gate học lại nhận no_show |

## Kiến trúc hiện tại (bối cảnh bắt buộc)

- **Academic ↔ Finance tách bạch (ADR-0026):** liên kết qua source triple (`source_system='academic_system'`, `source_kind='course_retake_registration'|'exam_resit_attempt'`, `source_ref`). Academic không cầm số tiền; giá do `FinancePricingCatalog` (`finance_pricing_catalog_items`, ưu tiên rule khớp `facts_match` cụ thể nhất) resolve tại thời điểm intake đồng bộ, bên trong transaction Academic.
- **Sự thật thanh toán** = sổ cái Finance qua `ObligationSettlementReader` / `AcademicObligationSettlement`; cờ `hq_fee_status` chỉ là projection hiển thị.
- **Hủy** đi qua handoff outbox: Academic ghi `academic_finance_cancellation_handoffs` → Finance `FinanceCancellationOperation` xử lý (hủy phí chưa trả; đã trả → giữ tiền, không hoàn) → completion outbox cập nhật lại Academic. Job phục hồi: `RecoverFinanceCancellationWorkAction`.
- **Học lại không tự xếp lớp:** `SyncPaidRetakeRegistrationsAction` → `AutoEnrollRetakeCourseAction` → `LinkPaidRetakeRegistrationToCourseRegistrationAction` (khớp student+semester+unit+course_offering, check settlement đồng bộ).

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Giá học lại theo bảng giá Finance](./phase-01-retake-pricing-from-catalog.md) | Done |
| 2 | [Phase 2: Hủy đã trả tiền — retake chưa xếp lớp + 2 action hủy thi lại](./phase-02-cancel-paid-retake-registration.md) | Done |
| 3 | [Phase 3: Giới hạn thi lại + no_show](./phase-03-resit-attempt-limit-and-no-show.md) | Done |
| 4 | [Phase 4: Regression test, tài liệu, kiểm thử toàn luồng](./phase-04-regression-tests-and-docs.md) | Done |

## Success Criteria

- [ ] Đăng ký học lại thành công với môn KHÔNG có `unit.retake_fee` nhưng bảng giá Finance có rule; màn hình học lại hiển thị đúng số tiền sẽ thu.
- [ ] Nhân viên hủy được đăng ký học lại ở trạng thái `paid` chưa gắn lớp; tiền đã thu trở thành unapplied balance dùng cho phí sau; không hoàn tiền về DNG.
- [ ] Giới hạn thi lại enforce theo `max_attempts` hiện hành (live policy, hiện = 1 lần/bản ghi điểm; tăng cấu hình được mà không sửa code).
- [ ] Resit paid-cancel có 2 action: "mất phí" giữ charge; "lưu phí dùng sau" void + balance tăng; paid-evidence 2 lần chỉ release 1 lần.
- [ ] no_show = forfeit phí; hủy trước thi (chưa trả) vẫn void như cũ.
- [ ] Nhân viên ghi nhận được `no_show`; `no_show` = trượt, mở luồng học lại (kể cả rớt vì điểm), và không cho tạo đơn thi lại thứ 2 trên cùng bản ghi.
- [ ] Không phá vỡ: không hoàn tiền, xếp lớp đúng kỳ, giảm giá EGC, boundary ADR-0026 (arch tests xanh).
- [ ] `./scripts/dev.sh artisan test --compact --filter=Retake|Resit` xanh; `composer exec pint -- --dirty`; `./scripts/check-docs.sh` xanh.

## Scope decisions (chủ động không làm)

- Không đổi luồng thu hộ DNG, không đổi machine trạng thái DNG.
- Không tự động-xếp-classes cho học lại (giữ staff-owned).
- Không động vào `is_retake_paid` kiểu string trên `CourseRegistration` (legacy, chỉ ghi 'yes'/'no' — dọn dẹp nằm ngoài scope này).
- Không đổi giá trị giảm giá EGC (quy tắc 7: giữ cứng).

## Rủi ro tổng thể

- Phase 2 chạm tiền đã thu (settlement mutation) — bắt buộc đi qua Finance Cancellation Operation, cấm Academic tự sửa invoice/charge (ADR-0026 + `SettlementMutationGuard`).
- Phase 3 mở khóa điều kiện chặn tạo đơn thi lại — có nguy cơ double-register nếu tách `completed` khỏi block list mà không có enforced limit; mitigate bằng `assertAttemptsRemaining` dùng consumed-attempt count + lockForUpdate.

## Validation Log

### Session 1 — 2026-08-31
**Trigger:** `/ak:plan validate` sau red-team; verification pass được bỏ qua vì `## Red Team Review` đã có bằng chứng.
**Questions asked:** 4 (+1 làm rõ follow-up)

#### Questions & Answers

1. **[Architecture]** [Phase 1] Lưu pricing_rule_version ở đâu để hiển thị + audit?
   - Options: cột riêng | tái dụng policy_snapshot | đọc runtime
   - **Answer:** "lấy giá trị ở finance đã lưu ấy" — đọc giá từ dữ liệu Finance đã lưu cho source_ref, không tạo cột cục bộ.
   - **Rationale:** một nguồn sự thật; hiển thị luôn = obligation thực tế.
2. **[Assumption]** [Phase 2] Permission cho hủy retake `paid`?
   - **Answer:** Tái dụng quyền hủy hiện tại.
   - **Rationale:** owner chấp nhận trade-off; không thêm permission mới.
3. **[Assumption]** [Phase 3] no_show giữ phí thế nào?
   - **Answer (custom, sau làm rõ):** phân biệt 2 trường hợp — hủy trước khi thi: payment đã nộp được LƯU LẠI dùng sau; no_show (đã có lịch, không dự thi): coi như bỏ thi, MẤT tiền. **Và: cần 2 action riêng biệt để staff quyết định hủy thi mất tiền hay giữ tiền.**
   - **Rationale:** mở rộng scope Phase 2 sang resit paid-cancel với 2 action tường minh.
4. **[Risk]** [Phase 2] Verification FAIL thì sao?
   - **Answer:** Dừng, chốt ADR với owner.

#### Confirmed Decisions
- Phase 1: hiển thị giá từ dữ liệu Finance đã lưu (không cột cục bộ) — 1 nguồn sự thật.
- Phase 2: tái dụng permission hủy hiện tại cho retake paid-cancel.
- Phase 2 (mở rộng): resit paid-cancel có 2 action riêng (forfeit / keep-for-later) — staff chọn hệ quả tiền.
- Phase 3: no_show = forfeit phí; hủy trước thi = giữ tiền dùng sau.
- Phase 2: verification FAIL → dừng + ADR với owner.

#### Impact on Phases
- Phase 1: bỏ migration/cột mới; UI đọc giá từ Finance-stored data; effort giữ 1.5d.
- Phase 2: mở rộng sang resit hai action (forfeit / keep-for-later) + tham số hóa `resolveFeeDisposition`/`reconcileLatePayment` theo fee_outcome + cập nhật 3 guard idempotency + mapping Academic; effort 1.5d → 2.5d.
- Phase 3: no_show = forfeit; ghi rõ khác biệt với hủy.
- Phase 4: thêm scenario resit hai action; effort giữ 1.5d.

#### Verification Results
- Tier: n/a — bỏ qua theo guard (Red Team Review đã có bằng chứng file:line).

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01..04 (sau propagation).
- Decision deltas checked: 5 (giá từ Finance-stored; permission tái dụng; resit 2 action; no_show forfeit; verification-gate dừng+ADR).
- Reconciled stale references: 3 (plan.md bảng quy tắc #4/#6 mở rộng sang resit; overview effort 5-7d → 6-8d; scenario Phase 4 cập nhật).

## Red Team Review

### Session — 2026-08-31
**Reviewers:** 3 (Security Adversary + Fact Checker; Failure Mode Analyst + Flow Tracer; Assumption Destroyer + Scope Auditor). Standard tier.
**Findings:** 18 thô → 7 sau dedupe/gộp (7 accepted, 1 rejected/superseded)
**Severity breakdown:** 3 Critical, 4 High (sau gộp), 2 Medium

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| A | Phase 2 dựa trên mô tả SAI hiện trạng: paid-void path **đã** release tiền về unapplied balance (`VoidFinanceChargeAction.php:77-82,163-172`); disposition mới sẽ double-release + phá guard idempotency `KeptPaidNoRefund` (`ResumeFinanceCancellationOnPaidEvidenceAction.php:69-77`, `ProcessFinanceCancellationOperationAction.php:211-214,232-235`) | Critical | Accept | Phase 2 (viết lại: Academic-only, verification bắt buộc) |
| B | `FinancePricingCatalog.resolve()` không tồn tại; API thật là `price(FinanceIntakeData)` throw `RuntimeException` (`FinancePricingCatalog.php:26,70-77`); thiếu try/catch → 500 thay vì ValidationException | Critical | Accept | Phase 1 |
| C | Retake không capture intake amount (`markFinanceObligationCreated($userId)` không amount) → snapshot hiển thị resolve độc lập, có thể lệch tiền thu thật | High | Accept | Phase 1 |
| D | Baseline Phase 3 stale: `IN_FLIGHT_STATUSES` đã tồn tại, no_show đã wire một phần, chỉ thiếu write action; 2 định nghĩa consumed sẽ diverge → một định nghĩa duy nhất = `attempt_number NOT NULL`; enforcement là live-policy (snapshot non-null default(1)) | High | Accept | Phase 3 |
| E | MarkExamResitNoShowAction: thiếu `can:` permission gate, audit actor/reason, idempotency; `attempt_number` phải set qua shared `nextAttemptNumber` | High | Accept | Phase 3 |
| F | docs-site có 4 locale (vi/en/ko/**zh**); trang retake/resit chưa tồn tại → CREATE 4 trang, effort +0.5d | Medium | Accept | Phase 4 |
| — | Shared cancellation processor không phân biệt retake/resit (RTFailure#1) | Critical | Reject/superseded | — (moot khi không thêm disposition) |

**Verified-safe (không cần sửa):** cancel-vs-link race có lock serialization; outbox idempotent (`firstOrCreate` theo source triple); `RecoverFinanceCancellationWorkAction` cover path mới; `SyncPaidRetakeRegistrationsAction` loại `finance_pending_cancellation`.

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01..04 (sau khi apply).
- Decision deltas checked: (1) Phase 2 = Academic-only, không disposition mới; (2) Phase 1 dùng `price()`/throw, snapshot từ intake amount; (3) Phase 3 consumed = attempt_number, live-policy; (4) Phase 4 = 4 locale CREATE.
- Reconciled stale references: 4 (plan.md bảng quy tắc + phases mô tả giữ ở mức tổng hợp, đã khớp với phase files; scenario 6 đổi live-policy; effort Phase 4 = 1.5d).
- Unresolved contradictions: 0.
