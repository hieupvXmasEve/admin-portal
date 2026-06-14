# Validation

## Proof Strategy

Prove exact amount parity between preview and execute paths, and prove totals
reconcile after discount, installment, and DNG pivot operations.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Scholarship cap, EGC fee source, rounding remainder, casts |
| Integration | Charge generation, split installment, DNG batch request creation |
| Integration | Void charge with linked installments is cancelled, blocked, or audited as designed |
| E2E | Smoke charge preview/execute page if UI output changes |
| Platform | Docker wrapper commands |
| Performance | Batch generation should not add uncontrolled per-student queries |
| Logs/Audit | Existing charge creation/void audit behavior preserved |

## Fixtures

- Scholarship greater than charge amount.
- Voided charge with same student/semester/type.
- EGC preview with page-local block override.
- Installment split followed by discount change.
- DNG request with multiple charge links and rounding remainder.
- Charge with linked unpaid and paid installments before void.

## Commands

```text
./scripts/dev.sh test --filter=Generate
./scripts/dev.sh test --filter=Installment
./scripts/dev.sh test --filter=Dng
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
git diff --check
```

## Acceptance Evidence

Add targeted test output and amount examples after implementation.
