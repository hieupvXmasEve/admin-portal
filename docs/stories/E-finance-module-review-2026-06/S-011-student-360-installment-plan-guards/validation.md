# Validation

## Proof Strategy

The story is complete only when both read and write paths reject stale
backfilled installments. UI behavior is not enough; backend actions must enforce
the same eligibility contract.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Eligibility resolver accepts staff-approved collectable installments and rejects backfilled rows, zero-balance charges, voided charges, and non-pending rows. |
| Integration | Student 360 cards do not expose `Đẩy kỳ tới` for a student like `AUH110281` with pending backfilled rows on settled charges. |
| Integration | `PushNextInstallmentAction` refuses zero-balance or non-approved installments and does not create `dng_payment_requests`. |
| Integration | Approved multi-installment plans still expose the next collectable installment and can push DNG exactly once. |
| E2E | Finance staff sees no `Trả góp` action for non-approved students and sees the action only after an explicit approved plan exists. |
| Platform | No student/lecturer portal validation expected unless scope changes. |
| Performance | Student 360 query remains bounded and uses ledger-balance subqueries or service calls without N+1 behavior. |
| Logs/Audit | Rejected unsafe push attempts are logged/audited with reason and actor. |

## Fixtures

- `AUH110281` / student id `588`: pending backfilled Fall 2025 and Spring 2026
  installments on settled charges, plus unpaid Summer 2026 EGC charges that are
  not automatically a trả góp plan.
- A synthetic approved two-installment charge with one pending next installment.
- A voided charge with pending or awaiting installment.
- A DNG-linked paid installment whose local status is stale.

## Commands

Add exact commands after implementation exists.

```text
./scripts/dev.sh test --filter=Student360InstallmentPlanGuardTest
./scripts/dev.sh test --filter=PushNextInstallmentActionTest
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

## Acceptance Evidence

Add command output, before/after target-row counts, and any data-repair review
exports after verification.
