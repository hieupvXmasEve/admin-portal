---
phase: 2
title: "Finance SQL filters"
status: done
priority: P1
effort: 3h
dependencies: [0]
---

> **Done 2026-08-17.** H7 decision on `LifecycleDueExceptionRowMapper` per-row
> lookup: **declared out of scope**, documented inline — pre-existing,
> bounded by pagination (max 100/page), batching it means threading a
> resolved-status map through `map()`'s signature and the query's pagination
> flow (larger change than this phase's file ownership). `Unit/Finance/Operations/`
> green unedited (H1 proof). Two pre-existing query-count-budget tests bumped
> with documented reasons (`CollectionProgressViewTest` 17→19,
> `BillingExceptionsPaginationTest` <10→<14) — both are phase 1's
> `StudentReferenceReader::findMany()` now correctly batch-resolving the live
> projection: 2 fixed extra queries, not per-row.

# Phase 2: Finance SQL filters

## Overview

Two Finance lifecycle-exception queries still bucket rows by
`whereHas('student', fn => where('status', ...))`. Their sibling
`LifecycleDueItemPredicate` already has a correct enrollment-first scope.

Red-team corrections: the planned **signature change breaks 4 unlisted call
sites plus a test file at a path this phase never ran** (H1); the **sibling
reason resolver** must move in the same commit or the gate and the persisted
enum disagree (H1); the shared scope lives in **`app/Shared/`** from phase 0,
not in `app/Modules/Finance/` (H2).

## Requirements

- [x] `LifecycleDueItemPredicate::isLifecycleException()` no longer reads `$student->status`.
- [x] **`LifecycleDueExceptionReasonResolver::resolve()` migrates in the same commit** (H1).
- [x] Both lifecycle-exception queries filter on the projection.
- [x] The scope comes from the phase-0 Shared class — **no new copy in `app/Modules/Finance/`** (H2).
- [x] Buckets stay mutually exclusive and exhaustive.
- [x] `tests/Unit/Finance/Operations/` is in the baseline and the success criteria.

## Architecture

### H1 — do not change the signature; overload

`isLifecycleException(?Student)` has **7** call sites, four of them in a test
file this plan forbids editing:

| Caller | Note |
|---|---|
| `app/Modules/Finance/Actions/Operations/ResolveLifecycleDueExceptionAction.php:44` | unlisted in the original plan |
| `app/Modules/Finance/Actions/Operations/AcknowledgeLifecycleDueExceptionAction.php:31` | unlisted |
| `app/Modules/Finance/Http/Web/Admin/LifecycleDueExceptionController.php:134` | unlisted |
| `tests/Unit/Finance/Operations/LifecycleDueItemPredicateTest.php:11,17,22,28` | **path phase 2 never ran** |

**Keep `isLifecycleException(?Student)` and have it resolve through the reader**,
rather than changing it to `?string`. `isLifecycleExceptionStatus(?string)` at
`:18-25` already exists as the source-agnostic seam — callers that have a
resolved status use that one. Zero signature churn, zero test-file edits.

### H1 — the sibling resolver

`app/Modules/Finance/Support/LifecycleDueExceptionReasonResolver.php:12-15`:

```php
public static function resolve(?Student $student): LifecycleDueExceptionReason
{
    return self::forStatus($student?->status);
}
```

Called at `ResolveLifecycleDueExceptionAction.php:48` and
`AcknowledgeLifecycleDueExceptionAction.php:35` — i.e. **immediately after** the
`isLifecycleException()` gate. If only the gate migrates, the gate becomes
projection-based while the **persisted reason enum stays legacy**: a row is
admitted as an exception for one reason and recorded with another.

`forStatus(?string)` at `:17` is already the right seam — migrate `resolve()`
the same way as `isLifecycleException()`, in the same commit.

### Projection scope — from phase 0

Use the Shared class. Its CASE is the SQL twin of
`StudentLifecycleStatusReader::legacyStatus()` (`:65-75`) and encodes phase 0's
**highest-id** tie-break (`pe.id = (SELECT MAX(id) …)`, **not** a bare
`whereExists`, per C5).

> **`dropout_transfer` goes to zero.** The projection collapses `withdrawn` →
> `dropout` and never emits `dropout_transfer`. After this phase `transfer_count`
> is 0 for every materialized student — and dev has **235/235 materialized**, so
> it is 0 outright. Accepted, but it is a **visible Finance metric going to
> zero**. Call it out in the PR; UI copy must not imply the number is
> authoritative.

### H7 (partial) — per-row mapper

`app/Modules/Finance/Support/LifecycleDueExceptionRowMapper.php:80-82` already
does a per-row `forStudentId()` call, driven from
`ListLifecycleDueExceptionsQuery.php:125`. That is a **pre-existing** N+1, not
introduced here.

**Decision required, recorded in the PR:** batch it in this phase, or declare it
explicitly out of scope. Do not leave it unstated — the phase touches the very
query that drives it.

## Related Code Files

**Modified (exclusive):**

- `app/Modules/Finance/Support/LifecycleDueItemPredicate.php` — `isLifecycleException()` :13-16; `materializedFinancialSubquery()` :112-117 → delegate to Shared scope
- `app/Modules/Finance/Support/LifecycleDueExceptionReasonResolver.php:12-15`
- `app/Modules/Finance/Queries/Operations/GetLifecycleDueExceptionSummaryQuery.php` — the three `whereHas('student', fn => where('status', …))` counts and the `whereNotIn('status', …)` other-branch
- `app/Modules/Finance/Queries/Operations/ListLifecycleDueExceptionsQuery.php` — `applyLifecycleReasonFilter()` :~148-163
- `app/Modules/Finance/Actions/Operations/ResolveLifecycleDueExceptionAction.php:44,48`
- `app/Modules/Finance/Actions/Operations/AcknowledgeLifecycleDueExceptionAction.php:31,35`
- `app/Modules/Finance/Http/Web/Admin/LifecycleDueExceptionController.php:134`
- `app/Modules/Finance/Support/LifecycleDueExceptionRowMapper.php:80-82` — only if the H7 decision is "batch now"

**Read-only:**

- the phase-0 Shared scope
- `app/Modules/StudentRegistry/Support/EloquentStudentCollectionEligibilityReader.php:36-40,58-62` — **already enrollment-first**, resolves advisory Q1. Confirm with `rg -n "whereExists|whereNotExists"` and move on. **Do not edit.**
- `app/Modules/Academic/Progression/Support/StudentLifecycleStatusReader.php:65-75`
- `tests/Unit/Finance/Operations/LifecycleDueItemPredicateTest.php` — **must keep passing unedited**

**Tests:**

- `tests/Feature/Finance/Operations/DueCalendarLifecycleStatusTest.php` — extend (exists)
- `tests/Feature/Finance/Operations/LifecycleDueExceptionProjectionTest.php` — new

## Implementation Steps

1. **Confirm phase 0 delivered** the Shared scope.
2. **Baseline** (failing **names**): `tests/Feature/Finance/Operations/`, `tests/Feature/Finance/Reporting/`, **`tests/Unit/Finance/Operations/`** (H1 — previously omitted). Known pre-existing: settlement-snapshot, EGC-only operator.
3. `rg -n "isLifecycleException\(|ReasonResolver::resolve\(" app tests` — confirm the 7 + 2 call sites above; put the list in the PR.
4. **RED** — `LifecycleDueExceptionProjectionTest.php`. **Materialize enrollments in every fixture**:
   - drifted student (column `deferred`, enrollment `active`/`intake_course`) with an open DNG due → **not** in the `deferred` bucket, **is** financial;
   - no-enrollment student, column `dropout` → still in `dropout` (fallback);
   - `withdrawn` enrollment → `dropout`, **not** `transfer`;
   - `deferred + dropout + transfer + other === total_count` on a mixed fixture;
   - **gate/reason agreement**: for a drifted student the `isLifecycleException()` verdict and the persisted `LifecycleDueExceptionReason` derive from the same status (H1).
5. Point `LifecycleDueItemPredicate` at the Shared scope; delete its local CASE/subquery duplication.
6. Migrate `LifecycleDueExceptionReasonResolver::resolve()` — same commit.
7. Point both queries at the Shared scope.
8. Record the H7 decision on `LifecycleDueExceptionRowMapper`.
9. **GREEN.** Re-run all three baselines from step 2.
10. `./scripts/dev.sh composer exec -- pint <modified files>`

## Success Criteria

- `./scripts/dev.sh artisan test tests/Feature/Finance/Operations/` → failing **names** identical to baseline (expect the 2 known). Zero new.
- `./scripts/dev.sh artisan test tests/Feature/Finance/Reporting/` → identical to baseline.
- `./scripts/dev.sh artisan test tests/Unit/Finance/Operations/` → **green, unedited** (H1 — proves the signature was not broken).
- `LifecycleDueExceptionProjectionTest.php` → green, 5 assertions incl. the bucket-sum invariant and gate/reason agreement.
- `rg -c "CASE\s*WHEN.*enrollment_status" app/` → **1** (grep scope is `app/`, not `app/Modules/Finance/` — H2).
- `rg -n "where\('status'|whereNotIn\('status'|whereIn\('status'" app/Modules/Finance/Queries/Operations/GetLifecycleDueExceptionSummaryQuery.php app/Modules/Finance/Queries/Operations/ListLifecycleDueExceptionsQuery.php` → **0 hits**.
- `rg -n "use App\\\\Modules\\\\(?!Finance)" app/Modules/Finance/` → no new cross-module import; `tests/Feature/Architecture/DomainBoundaryArchitectureTest.php` green.
- H7 decision on `LifecycleDueExceptionRowMapper` recorded in the PR (batch or out-of-scope).

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **Signature change breaks a forbidden test file** (H1) | High × High | Overload instead of changing. `tests/Unit/Finance/Operations/` green **unedited** is a hard criterion. |
| **Gate migrates, persisted reason does not** (H1) | High × High | Same-commit requirement + the gate/reason agreement assertion in step 4. |
| **Cross-module import fails the arch test** (H2) | Med × High | Scope comes from `app/Shared/` (phase 0). Explicit `rg` criterion above. |
| **Buckets stop summing to total** | Med × High | Sum invariant written before the refactor. |
| **`transfer_count` visibly 0** | High × Med | Expected. 235/235 materialized on dev ⇒ 0 outright, not "mostly 0". Must be in the PR text. |
| **Bare `whereExists` reintroduces the tie-break divergence** (C5) | Med × High | Shared scope uses `pe.id = (SELECT MAX(id) …)`. Phase 0's two-primary-row unit test covers it; do not hand-roll a local `whereExists`. |
| **Misreading the 2 known failures as regressions** | High × Low | Baseline records names, not counts. |

### Rollback

Revert the 7 modified files. The Shared scope (phase 0) is additive and stays —
`whereFinancial()` / `whereNotFinancial()` keep working whether or not this
phase's delegation landed, so there is no half-state.
