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

### Implementation evidence (2026-06-15)

- `BillingExceptionsQueryTest`: 6 passed — list rows, retake fix, retake fix idempotency, wrong-semester refusal, per-registration retake detection, defer refusal
- `DueItemsSummaryParityTest`: 1 passed — upcoming summary excludes due-today (FIN-26)
- `ListDueItemsQueryPaginationTest`: 1 passed — 25 rows paginated with &lt; 6 queries (FIN-27)
- `SettlementServicePriorityOrderTest`: 1 passed — `retake_fee` sorts before `tuition_term` (FIN-22)
- `GetDueInvoicesSummaryQueryTest`: 1 passed — overdue amount via eager-loaded snapshots (FIN-29)
- `LifecycleDueItemPredicateJoinTest`: 1 passed — active scope uses JOIN not EXISTS (DB-23)
- `DueCalendarLifecycleFilterTest`: 2 passed — lifecycle filter still works after JOIN rewrite
- `RetakeUnpaidCountTest`: 1 passed — paid invoice with stale cache not counted unpaid (FIN-28)
- `finance:audit-invariants --sample`: all 15 invariants 0 offending rows
- Pint: `./scripts/dev.sh artisan pint` not registered in this container; skipped
- ESLint: repo-wide baseline drift (5158 pre-existing); touched Finance Operations files not flagged in targeted review
- `AutoAllocatePaymentsTest`: pre-existing 419 CSRF failures on web POST route; priority ordering covered by unit test instead

### Code review follow-up evidence (2026-06-15)

- `./scripts/dev.sh test --filter='BillingExceptionsQueryTest|DueItemsSummaryParityTest|ListDueItemsQueryPaginationTest|GetDueInvoicesSummaryQueryTest|LifecycleDueItemPredicateJoinTest|RetakeUnpaidCountTest|SettlementServicePriorityOrderTest'`: 12 passed / 33 assertions
- `git diff --check`: passed
- Remaining follow-up: `ListBillingExceptionsQuery` still materializes exception rows before pagination; code review recommends DB-level pagination/aggregation for this queue before merge-ready signoff.
