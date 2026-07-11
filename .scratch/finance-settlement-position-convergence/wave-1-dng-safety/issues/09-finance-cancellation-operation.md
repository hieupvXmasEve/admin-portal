# 09 — Hủy obligation qua Finance Cancellation Operation

**Status:** completed
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

## Comments

### Hardening pass 2026-07-11 (review blockers)

Addressed Standards + Spec blockers from second review:

| Blocker | Fix |
|---|---|
| Durable handoff | Academic writes `academic_finance_cancellation_handoffs` **in the same TX** as Finance-Pending; job delivers Finance request after commit |
| ADR-0026 overclaim | ADR rewritten: same correctness window = pending + Academic handoff outbox (not cross-context Finance write) |
| Late payment production trigger | `ResumeFinanceCancellationOnPaidEvidenceAction` from DNG webhook (late cancelled receipt + first settlement) and `BridgePaidDngRequestsForChargeAction` |
| Aggregate fail-open | Invalid Settlement Position → `requires_review`, **no** guessed local balance replacement |
| Stale claim double provider | `provider_attempts` ledger: `in_flight` refuse re-call on reclaim → review |
| Append-only completion | Dropped unique on operation_id; versioned outbox rows (`completed`, `paid_disposition_upgrade`) |
| Lost enqueue recovery | Scheduled one-minute scanner re-dispatches pending/failed Academic handoffs, requested/stale Finance operations, and pending completion events |
| Provider exception retry | Ambiguous provider exceptions persist as `unknown`; re-entry refuses another provider call until reconciliation proves outcome |
| Canonical replacement atomicity | Replacement validates/creates inside the void transaction before void; invalid/missing Settlement Position leaves the target active and moves operation to review |

### Quality gates (2026-07-11)

| Gate | Result | Notes |
|---|---|---|
| Targeted Pest (cancellation) | **33 passed / 171 assertions** | isolated DBs, sequential |
| CancellationOperationTest | **17 passed / 81 assertions** | recovery scanner, including pending/failed handoffs and stale operations; ambiguous provider retry; canonical fail-closed atomicity |
| Exam resit cancel | 10 passed | handoff deliver helper under Queue::fake |
| Retake cancel | 6 passed / 27 assertions | handoff deliver helper under Queue::fake |
| Pint `--dirty` | **passed** | |
| `pnpm type-check` | **passed** | `vue-tsc --noEmit` |
| `pnpm lint` | **passed** | `eslint . --fix`; no unrelated file drift |
| Full suite | **exit 0** | single process on isolated `db_test_issue09_full`; Collision emitted no count output |

### Completed after final Standards + Spec review found no remaining acceptance-criteria blocker.
