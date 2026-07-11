# 09 — Hủy obligation qua Finance Cancellation Operation

**Status:** done  
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Triển khai durable, source-keyed Finance Cancellation Operation cho source workflow thực sự hủy obligation. Source chuyển sang Finance-Pending Cancellation, Finance xử lý collection theo state, void payable effects sau confirmed outcome, rồi publish durable outbox completion để source context kết thúc idempotently. Aggregate DNG có target khác phải được cancel và tạo replacement rõ ràng cho phần còn lại.

## Acceptance criteria

- [x] Cancellation request tạo một idempotent Finance Cancellation Operation theo neutral source reference.
- [x] Source ở Finance-Pending Cancellation cho tới khi Finance phát completion; polling không phải correctness mechanism.
- [x] No-request/unattempted/confirmed-unpaid/unknown/paid states đi đúng nhánh và unknown không terminalize source.
- [x] Paid request được canonical receipt bridge trước khi payable bị void; Payment/DNG history được giữ.
- [x] Aggregate request có nhiều obligations được confirmed-cancel, target bị void, và replacement chỉ gồm remaining canonical targets.
- [x] Completion event phát qua outbox sau khi Finance effects commit; source consumer xử lý idempotently.
- [x] Không có cross-context transaction hoặc model callback giữ lock qua provider call.

## Blocked by

- [08 — Hủy collection mà không void obligation](08-cancel-collection-without-voiding-obligation.md)

---

## Comments (implementation hardening 2026-07-11)

### Standards blockers fixed

1. Academic no longer reads FinanceCharge/DNG directly for cancel preconditions — uses `FinanceCancellationChargeStateReader` + `FinanceCancellationChargeState` DTO.
2. Fee disposition constants live in shared `FinanceCancellationFeeDisposition` enum; Academic maps from payload values only.
3. `CancelExamResitAttemptAction::run(array $data)` is static (repo convention).
4. `RequestFinanceCancellationOperationAction` wraps firstOrCreate + `afterCommit` job dispatch in a Finance DB transaction.

### Spec blockers fixed

1. **No cross-context TX:** Academic marks Finance-Pending under its own transaction, then calls Finance request **outside** that transaction (exam resit + retake).
2. **Concurrency claim:** processor optimistically claims `requested|requires_review|(stale processing)` → `processing` before provider cancel; concurrent workers exit without calling provider.
3. **Aggregate replacement:** planned during collection cancel; created **after successful void** inside settlement TX; amounts from Settlement Position when available, else ledger cash/discount remaining (not pivot copy alone).
4. **Late payment after completion:** re-entry on `completed` upgrades to `kept_paid_no_refund`, bridges cash, re-dispatches completion outbox; Academic upgrades fee disposition on already-cancelled sources.
5. **Tests added** in `CancellationOperationTest`: concurrent claim, provider once on re-entry, late paid disposition, canonical remaining, no replacement when void fails.

### Validation

- `CancellationOperationTest`: 12 passed
- Exam resit + retake cancel suites: 16 passed
- Isolated test DB `db_test_cancel` used when shared `db_test` had migration races
