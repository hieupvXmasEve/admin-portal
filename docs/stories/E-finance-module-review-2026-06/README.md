# Finance Module Review 2026-06 Story Set

Source review: `docs/features/finance/finance-module-review-2026-06-13.md`

This epic decomposes the Finance module review into small high-risk Harness
stories. The order intentionally keeps measurement and immediate safety first,
then fixes money truth, data guards, DNG reliability, operations cleanup, and
finally UI/BOD surfaces.

## Story Order

| Order | Story id                                              | Story packet                                     | Main scope                                                                                                                            | Depends on                                   |
| ----- | ----------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| 1     | `FIN-REV-001-audit-and-safety-gates`                  | `S-001-audit-and-safety-gates/`                  | Run/record invariants, close immediate dangerous UI/action gaps                                                                       | None                                         |
| 2     | `FIN-REV-002-ledger-source-of-truth`                  | `S-002-ledger-source-of-truth/`                  | One balance source, immutable invoice snapshot, allocation locks/idempotency, transparent cache/rebuild, DNG-vs-ledger reconciliation | 001                                          |
| 3     | `FIN-REV-003-data-guards-and-constraints`             | `S-003-data-guards-and-constraints/`             | Clean duplicates, then add DB uniqueness/check/FK/status/model guards and any operation-token schema needed by 002                    | 001, 002 for final constraints               |
| 4     | `FIN-REV-004-charge-discount-installment-correctness` | `S-004-charge-discount-installment-correctness/` | Scholarship/EGC/installment/pivot/void amount correctness                                                                             | 001, preferably 002                          |
| 5     | `FIN-REV-005-dng-security-and-idempotency`            | `S-005-dng-security-and-idempotency/`            | Webhook checksum, dedup, locks, cancelled status ordering                                                                             | 001, 003 for payload cleanup                 |
| 6     | `FIN-REV-006-operations-stubs-and-performance`        | `S-006-operations-stubs-and-performance/`        | Replace stub ops, due-reminder naming/counts, query pagination/perf                                                                   | 001                                          |
| 7     | `FIN-REV-007-finance-ui-foundation-and-navigation`    | `S-007-finance-ui-foundation-and-navigation/`    | Shared finance UI foundation, navigation links, unsafe form rewrites                                                                  | 001, 002 for money display confidence        |
| 8     | `FIN-REV-008-bod-finance-oversight`                   | `S-008-bod-finance-oversight/`                   | Read-only BOD finance overview with charts after cache/rebuild and DNG-vs-invoice rail decisions                                      | 002, 003, 007                                |
| 9     | `FIN-REV-009-finance-audit-workspace`                 | `S-009-finance-audit-workspace/`                 | Universal-search Finance Audit Workspace built on the ledger graph, derived settlement truth, and permission-checked deep links       | 002, 005, 007                                |
| 10.1  | `FIN-REV-010-finance-staff-workspace`                 | `S-010-finance-staff-workspace/`                 | Milestone 1 consolidated story: Finance shell + Student 360 foundation                                                                | 002, 004, 005, 006, 009 for audit/deep links |
| 10.2  | `FIN-REV-010-student-360-full`                        | `S-010-student-360-full/`                        | Milestone 2 consolidated story: full Student 360 operator surface and safe action drawers                                             | 010.1, 002, 005                              |
| 10.3  | `FIN-REV-010-cockpit`                                 | `S-010-cockpit/`                                 | Milestone 3 consolidated story: Cockpit Hôm nay triage, queue action panel, data-health, and invariant drilldown                      | 010.1, 010.2                                 |
| 10.4  | `FIN-REV-010-batch-studio`                            | `S-010-batch-studio/`                            | Milestone 4 consolidated story: Batch Studio wizard + preview-token safety for bulk charge gen, DNG push, and reminders               | 010.1, 010.2, 002, 004, 005, 006             |
| 10.5  | `FIN-REV-010-lookup-and-audit`                        | `S-010-lookup-and-audit/`                        | Milestone 5 consolidated story: unified lookup standard (charges/invoices/payments) + Audit Workspace money-flow graph                | 010.1, 010.2, 009; 010.4 for batch hand-off  |
| 10.6  | `FIN-REV-010-cutover-and-uat`                         | `S-010-cutover-and-uat/`                         | Milestone 6 consolidated story: Finance Office cutover, legacy entrypoint decisions, and role-based operator UAT                      | 010.1, 010.2, 010.3, 010.4, 010.5            |
| 12    | `FIN-REV-012-fee-tracking-reporting-requirements`     | `S-012-fee-tracking-reporting-requirements/`     | Requirement holder for Finance Reporting fee tracking, statistics, drilldowns, campus scope, and page structure                       | 010.1-010.6, 008, 009; `ACAD-RET-001` before retake/resit expected-fee implementation |
| 13    | `FIN-REV-013-dng-payment-request-batch-studio-cutover` | `S-013-dng-payment-request-batch-studio-cutover/` | Make Batch Studio the primary bulk DNG payment-request creation path while keeping DNG audit/detail routes intact                     | 010.4, 010.6, 005                            |
| 14    | `FIN-REV-014-detail-deeplink-audit-repair`            | `S-014-detail-deeplink-audit-repair/`            | Keep charge/invoice/payment/DNG detail backend routes as secondary detail/deep-link/audit/repair pages                               | 010.5, 010.6; coordinate with 013            |
| 15    | `FIN-REV-015-hp-egc-generation-batch-studio-cutover`  | `S-015-hp-egc-generation-batch-studio-cutover/`  | Migrate HP/Tuition and EGC charge generation so Batch Studio is the only primary generation command surface                           | 010.4, 010.6, 004; coordinate with 013, 014  |
| 16    | `FIN-REV-016-finance-reporting-shell`                 | `S-016-finance-reporting-shell/`                 | Build the read-only Finance Reporting shell with permission, route, sidebar entry, URL-backed views, campus context, and freshness conventions | 012, 010.6                              |
| 17    | `FIN-REV-017-finance-reporting-fee-monitor`           | `S-017-finance-reporting-fee-monitor/`           | Build the Fee Monitor lens for expected/generated/missing fee completeness and drilldowns                                             | 016; `ACAD-RET-001` before retake/resit missing-fee completeness |
| 18    | `FIN-REV-018-finance-reporting-collection-progress`   | `S-018-finance-reporting-collection-progress/`   | Build the Collection Progress lens using canonical settlement/ledger money truth                                                      | 016, 002                                    |
| 19    | `FIN-REV-019-finance-reporting-dng-lifecycle`         | `S-019-finance-reporting-dng-lifecycle/`         | Build the DNG/Payment Lifecycle lens with attention buckets, webhook/payment bridge state, and related semester lineage               | 016, 005, 009                              |
| 20    | `FIN-REV-020-defer-finance-settlement`                | `S-020-defer-finance-settlement/`                | Align academic defer item evidence, non-billable deferred registrations, and Finance ledger behavior without introducing a parallel settlement ledger | 002, 004, 005, 017                         |

## FIN-REV-020 Money-Phase Child Stories

`FIN-REV-020` slice 1 (item-level defer evidence, non-billable deferred
registrations, backfill dry-run) is implemented. The money-settlement slice is
decomposed into small high-risk increments, executed in order, each TDD with a
clean `finance:audit-invariants` pass. PARTIAL and COURSE-scope settlement stay
report-only (needs-review) until a later increment defines a course-fee
allocation rule.

| Order | Story id                                        | Story packet                               | Main scope                                                                                                       | Depends on            |
| ----- | ----------------------------------------------- | ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- | --------------------- |
| 20.1  | `FIN-REV-020-01-defer-policy-settlement-action` | `S-020-01-defer-policy-settlement-action/` | `ApplyDeferFinancePolicyAction` FULL-scope PRESERVE/FORFEIT, isolated; void-and-release → consume; auto-safe gate | 020 slice 1, 002, 005 |
| 20.2  | `FIN-REV-020-02-defer-generation-non-billable`  | `S-020-02-defer-generation-non-billable/`  | Generation/preview treat defer as non-billable; remove preserve-skip so M1 voids are not regenerated             | 020.1                 |
| 20.3  | `FIN-REV-020-03-defer-runtime-auto-apply`       | `S-020-03-defer-runtime-auto-apply/`       | Wire runtime auto-apply for auto-safe FULL PRESERVE/FORFEIT; needs-review recorded + flagged; idempotent          | 020.1, 020.2          |
| 20.4  | `FIN-REV-020-04-defer-reenrollment-allocation`  | `S-020-04-defer-reenrollment-allocation/`  | Re-enrollment generates a normal charge + AutoAllocate uses preserved cash                                       | 020.1, 020.2, 020.3   |

## FIN-REV-010 Consolidated Stories

FIN-REV-010 is tracked as milestone-sized Harness stories. The old
task-sized child packets are retained as historical notes only so validation
evidence and debugging context are not lost.

| Order | Story id                              | Story packet                     | Main scope                                                                                                                                                  | Depends on                                  |
| ----- | ------------------------------------- | -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| 10.1  | `FIN-REV-010-finance-staff-workspace` | `S-010-finance-staff-workspace/` | M1 shell/foundation: route helpers, permissions, Student 360 shell, global search, semester context, sidebar IA, topbar mount, and M1 evidence              | 002, 004, 005, 006, 009                     |
| 10.2  | `FIN-REV-010-student-360-full`        | `S-010-student-360-full/`        | M2 Student 360 full surface: status cards, grouped ledger, allocation preview, record-payment adapter, reviewed DNG cancel, action drawers, and M2 evidence | 010.1, 002, 005                             |
| 10.3  | `FIN-REV-010-cockpit`                 | `S-010-cockpit/`                 | M3 Cockpit Hôm nay: KPI ribbon, queues, Action Panel, deferred data-health, invariant drilldown, phase shortcuts, and M3 evidence                           | 010.1, 010.2                                |
| 10.4  | `FIN-REV-010-batch-studio`            | `S-010-batch-studio/`            | M4 Batch Studio: shared 4-step wizard, preview-token drift safety, bulk charge gen / DNG push / reminders, and M4 invariant evidence                        | 010.1, 010.2, 002, 004, 005, 006            |
| 10.5  | `FIN-REV-010-lookup-and-audit`        | `S-010-lookup-and-audit/`        | M5 Lookup & Audit: `useDataTable` lookup standard for charges/invoices/payments, row→360 + Batch Studio selection, Audit money-flow graph + timeline polish | 010.1, 010.2, 009; 010.4 for batch hand-off |
| 10.6  | `FIN-REV-010-cutover-and-uat`         | `S-010-cutover-and-uat/`         | M6 Cutover & UAT: route/menu/page inventory, legacy entrypoint decisions, navigation cutover, permission pass, and operator UAT evidence                    | 010.1, 010.2, 010.3, 010.4, 010.5           |

### Retired Child Packets

These task-sized packets were useful while executing/debugging the milestones,
but they are no longer active Harness story units. Use the consolidated
stories above for planning, status, review, and acceptance.

| Retired task packets                                                              | Merged into                           |
| --------------------------------------------------------------------------------- | ------------------------------------- |
| `S-010-01-finance-route-constants/` through `S-010-07-topbar-and-evidence/`       | `FIN-REV-010-finance-staff-workspace` |
| `S-010-08-student-360-status-ledger/` through `S-010-14-student-360-m2-evidence/` | `FIN-REV-010-student-360-full`        |

## Shared Rules

- Portal impact is `none` unless a future slice explicitly changes
  `/api/v1/student/*` or `/api/v1/lecturer/*`.
- Product code implementation must use Docker wrappers in `./scripts/dev.sh`.
- Do not add DB constraints until matching dirty-data checks are zero or a
  human-approved remediation plan exists.
- Do not hide finance data only in Vue when backend queries/actions can still
  operate on the unsafe rows.
- Every implementation slice must run targeted tests and record `finance:audit-invariants`
  evidence when money or DNG state can change.
- `FIN-11` and `DB-09` are P1 Finance core work, not DNG-only work: allocation
  concurrency must be owned by the canonical settlement/allocation story.
- `DB-13`, `DB-14`, and `DB-15` are blockers for leadership aggregates: do not
  build BOD metrics from stale cache columns or by double-counting invoice and
  DNG rails.
- The audit workspace must model Finance as a graph/ledger, not a linear
  `charge -> invoice -> DNG -> payment -> settlement` chain. `Settlement` is a
  derived read model from `SettlementService`, not a persisted entity.
- Lifecycle due exception residuals are split: duplicated acknowledge/resolve
  logic belongs to `E-finance-lifecycle-exceptions`; DNG cancel audit ordering
  is cross-cutting with the DNG hardening story.
- Charge, invoice, payment, and DNG request detail backend routes are not
  legacy-deletable cleanup targets; keep them as detail, deep-link, audit, and
  repair surfaces unless a future accepted story explicitly supersedes them.
- HP/Tuition and EGC standalone generation pages are temporary command surfaces:
  `FIN-REV-015` owns migrating their normal generation workflows fully into
  Batch Studio while preserving existing money logic.
- Scheduling note: `FIN-REV-002` should first try lock-and-check idempotency
  without schema. If the implementation proves an operation token is required,
  pause that slice and let `FIN-REV-003` add the minimal storage before
  continuing.
- Retake/resit reporting note: do not implement missing expected-fee logic for
  `retake_fee` or `exam_resit_fee` until
  `ACAD-RET-001-retake-resit-operations` provides the Academic source/lifecycle
  contract.
- Reporting build note: `FIN-REV-012` is the requirement holder only. Runtime
  work starts with the shell in `FIN-REV-016`, then separate lens stories for
  Fee Monitor, Collection Progress, and DNG/Payment Lifecycle.
- Defer Finance note: `FIN-REV-020` owns item-level full-scope defer backfill
  plus runtime Finance behavior. `FORFEIT` and `PARTIAL` consume only money
  already paid; do not create unpaid forfeit debt or a parallel settlement
  ledger unless a later accepted decision proves existing ledger references are
  insufficient.
