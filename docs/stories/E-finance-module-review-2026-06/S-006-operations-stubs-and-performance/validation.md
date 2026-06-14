# Validation

## Proof Strategy

Prove each formerly stubbed operation has honest behavior and each rewritten
query returns the same or corrected rows without loading the whole table.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Due date bucket boundaries and allocation priority ordering |
| Integration | Billing exception list/fix behavior, due item list/summary parity |
| Integration | Lifecycle predicate query uses a join or proven index, not an unbounded correlated scan |
| E2E | Browser smoke for touched Finance Operations pages |
| Platform | Docker wrapper commands |
| Performance | Query count or `EXPLAIN` for DB-paginated replacements |
| Logs/Audit | No fake success; real writes are auditable |

## Fixtures

- Due today and upcoming DNG requests.
- Billing exception that can be fixed or explicitly rejected.
- Large enough due item set to prove DB pagination.
- Lifecycle due item set with deferred/dropout and active students.

## Commands

```text
./scripts/dev.sh test --filter=BillingOperations
./scripts/dev.sh test --filter=DueItems
./scripts/dev.sh test --filter=Dashboard
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run type-check
git diff --check
```

## Acceptance Evidence

Add targeted test output and query/performance notes after implementation.
