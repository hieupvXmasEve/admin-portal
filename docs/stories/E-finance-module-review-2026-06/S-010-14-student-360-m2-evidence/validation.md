# Validation

## Proof Strategy

This story is complete only when the final M2 evidence has been recorded in the
umbrella validation packet and Harness trace.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Use targeted tests from owning stories if any unit tests are introduced. |
| Integration | Full M2 backend suite passes. |
| Integration | M1 Student 360/search/shell regression suite passes. |
| E2E | Permission matrix and focus highlight smoke recorded. |
| Platform | Frontend build succeeds; targeted lint succeeds. |
| Performance | Deferred ledger behavior still prevents eager full-ledger initial render. |
| Logs/Audit | Invariant evidence before/after record-payment and DNG-cancel/void smoke recorded. |

## Fixtures

- Student with invoices, payments, active DNG, and installments.
- DNG with linked charges.
- DNG with bridged payment/blocking reason.
- Users with incremental finance permissions.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360 tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php tests/Feature/Finance/Search tests/Feature/Finance/Shell
./scripts/dev.sh npm run build
./scripts/dev.sh artisan finance:audit-invariants
```

## Acceptance Evidence

Recorded 2026-06-15.

| Check | Result | Detail |
| --- | --- | --- |
| M2 backend suite | **PASS** | 22 tests, 136 assertions |
| M1 regression | **PASS** | 10 tests, 55 assertions (Search + Shell + Audit) |
| Frontend build | **PASS** | 5405 modules; chunk-size advisory only |
| Targeted ESLint | **PASS** | 0 errors on M2 `student360/` + `Show.vue` + route helpers |
| Deferred ledger | **PASS** | `Student360OverviewTest` — `ledger_groups` absent from initial payload, loads via deferred reload |
| Invariants (dev `asia`) | **PASS (no regression)** | Pre-existing INV-6 (28) + INV-13 (1); unchanged after M2 test run |
| Write-path isolation | **PASS** | Feature tests use `db_test` + `RefreshDatabase` |
| Permission matrix (server) | **PASS** | See umbrella `validation.md` §M2 permission matrix table |
| Browser smoke (rendered UI) | **Manual** | Documented in umbrella validation; no Playwright harness |

### Commands executed

```text
./scripts/dev.sh test tests/Feature/Finance/Student360 tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
# → 22 passed (136 assertions)

./scripts/dev.sh test tests/Feature/Finance/Search tests/Feature/Finance/Shell tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php
# → 10 passed (55 assertions)

./scripts/dev.sh npm run build
# → success

./scripts/dev.sh npm exec eslint -- resources/js/components/finance/student360/ resources/js/pages/Finance/Student360/Show.vue resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
# → 0 errors

./scripts/dev.sh artisan finance:audit-invariants
# → INV-6 ❌ 28, INV-13 ❌ 1 (pre-existing dev data); all others ✅ 0
```

### Friction

- Parallel test invocations corrupted `db_test` (`RefreshDatabase` race). Fixed by
  recreating `db_test` and re-running suites sequentially.
- `finance:audit-invariants` audits the dev `asia` dataset, not `db_test`; write-path
  tests are isolated and do not explain INV-6/INV-13 counts.