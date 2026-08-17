# PM Report: Retire legacy students.status reads — plan complete

Plan: `plans/260817-0017-retire-legacy-studentsstatus-reads/`. All 6 phases done, sync-back verified (39/39 requirement checkboxes across phase files, phases table, acceptance criteria — all `[x]`).

## Phase status

| Phase | Status | Key result |
|---|---|---|
| 0 Inventory & contracts | Done | 48-file classification table, `StudentLifecycleProjection` Shared class, `a4-baseline.json` (235 total, 15 drift) |
| 1 Flip at source | Done | `EloquentStudentRegistryStore` flipped, ~20 consumers fixed for free, H3 delta 175→180 measured, N+1 fix beyond H7's own list |
| 2 Finance SQL filters | Done | `LifecycleDueItemPredicate`/`ReasonResolver` + 2 queries migrated, H1 unedited-test proof green |
| 3 Roster/auth/dashboards | Done | C1 auth-bypass fixed centrally (0 direct auth-file edits needed), H4 SQL-only, H5 self-healing by_status |
| 4 Display/API/AI readers | Done | Bucket A/C verified untouched, 2 extra sites found in inventory migrated |
| 5 Guard + drift probe | Done | 18-entry whitelist set-equal to real scan, caught 1 real unfixed gap, drift probe clean |

## Verification

- New/updated tests: 9 files, all green on first or second real run.
- Regression sweep across Registry/RetakeCourse/Delivery/Progression/Finance-Operations/Reporting/Egc/Mcp/Engagement/Architecture: 448 passed, only 5 pre-existing failures (confirmed via `git stash` against clean `dev` HEAD before touching anything — settlement-snapshot, 2 EGC migration schema tests, 1 order-dependent flake, 1 unrelated StudentProfileReaderTest).
- Drift probe vs dev `asia` (read-only): all SQL-vs-PHP symmetric diffs empty, dashboard tally sums to 235, drift = exactly the 15 known students.
- Regression found and fixed mid-session (pre-existing WIP, not this plan's own code): `StudentRegistryFinanceBoundaryArchTest` broken by an errant `use App\Models\Student;` import — fixed by extracting `StudentLifecycleStatusPresenter` to `app/Shared/`.
- Regressions found and fixed (caused by this plan's own phase 1): 2 query-budget tests needed a documented +2 bump (batched, not per-row).

## Docs

ADR-0033 amended: canonical-status source + binding highest-id tie-break. No docs-site page mentions `transfer_count`/`active_count` — skipped per "no surface exists" rule.

## Unresolved / residual

- Guard test still can't run automatically — no CI in this repo (stated ceiling, not a gap in this plan).
- `EventParticipantResource.php:47` — documented accepted gap, not migrated (no batching seam in a Resource).
- `LifecycleDueExceptionRowMapper.php` per-row status lookup — documented out-of-scope N+1, pre-existing, bounded by pagination.
- Not yet committed to git — pending user decision.
