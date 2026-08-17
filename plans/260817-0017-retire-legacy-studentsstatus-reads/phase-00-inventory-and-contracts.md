---
phase: 0
title: "Inventory and contracts"
status: done
priority: P1
effort: 3h
dependencies: []
---

> **Done 2026-08-17.** Classification table (48 files) is in `plan.md` under
> "A1 Classification Table". A2 decision (highest id wins) is encoded in
> `app/Shared/Support/Academic/StudentLifecycleProjection.php`'s docblock. A3
> shipped as that same class. A4 baseline is `a4-baseline.json` in this plan
> directory (235 total, 15 drift, matches the plan's measured table). Arch
> test and the new unit test are green.

# Phase 0: Inventory and contracts

## Overview

Added by red-team review (findings C3, C5, H2, C2). Phases 1–3 were written
against an inventory that was ~40% short, three mutually incompatible
definitions of "the primary enrollment row", and a shared SQL helper placed
where a module-boundary arch test would reject it.

**This phase writes no production behavior.** It produces four artifacts that
phases 1, 2 and 3 consume. Nothing else starts until all four exist.

> **The uncomfortable fact this phase must record up front:**
> dev has **235 students, 235 with a primary enrollment — zero on the fallback
> branch.** The ~288 untouched test files create students with no enrollment,
> so they exercise **only** the fallback. Prod-like data exercises **only** the
> projection. The two never overlap. "We didn't touch the tests and they still
> pass" is therefore **not** evidence that the migration is safe — it is
> evidence that the tests do not test the migration. Every phase needs at least
> one fixture with a materialized enrollment, or it is testing the branch it
> did not change.

## Requirements

- [x] **A1 — Full inventory.** Every `students.status` read in `app/`, `routes/`, `database/`, classified into exactly one of four buckets. No file left unassigned.
- [x] **A2 — Tie-break contract.** One written rule for "which row is the primary enrollment", with the SQL and PHP forms that implement it.
- [x] **A3 — Shared SQL scope.** The enrollment-first projection scope, placed where all three consuming modules may import it.
- [x] **A4 — Recorded drift baseline.** Pre-change JSON committed to the PR body, not a predicted number.

## A1 — Inventory

### Method

Grep is not enough — `isActive()` alone is defined on **≥6 models**
(`AcademicHold:108`, `BillingCycle:84`, `Lecture:263`, `CourseRegistration:218`,
`Semester`, `User`), so a name grep produces 20 hits of which only 5 are
`Student`. **Resolve the receiver type, do not pattern-match the name.**

Run, then classify by reading each hit:

```
rg -n --type php -e '\$student(\?)?->status\b' -e 'students\.status' \
   -e "where(In|NotIn)?\('status'" -e "whereHas\('student'" \
   -e "->select\('status'\)" -e "groupBy\('status'\)" app routes database
```

Then filter out non-Student receivers by hand. Expect **36–45 files**, not 14.

### Four buckets

| Bucket | Meaning | Guard treatment |
|---|---|---|
| **migrate** | Reads the frozen column where the caller wants *current* state | Must be zero after phases 1–4 |
| **whitelist-fallback** | The `?? $student->status` / `whereNotExists(primary) AND …` tail — the fallback mechanism itself | Permanent |
| **whitelist-historical** | Wants *stage at admission / at record creation* — the frozen column is the correct source | Permanent, with a written reason |
| **whitelist-writer** | Writes the column at student creation | Permanent |

### Files the plan previously left unassigned (red-team C3) — classify these explicitly

| File:line | Note |
|---|---|
| `app/Modules/Academic/Progression/Queries/Reporting/GetStudentStatusBySemesterQuery.php:213-237` | **Takes the `Student` model**, not a `StudentReference`. The advisory's "benefits automatically" claim is **false** for this file. `:214-216` returns `pending`/`admission_deferred` verbatim; `:236` is a `?? 'pending'` tail. Likely **historical** (it reconstructs a per-semester timeline) — confirm and record. |
| `app/Modules/StudentRegistry/Support/EloquentSemesterEnrollmentEligibilityReader.php:47-53` | **Live eligibility gate**: `whereIn('status', ELIGIBLE_INTAKE_STATUSES)->where('academic_status','active')`. Almost certainly **migrate**. Assign to a phase. |
| `app/Console/Commands/CreateEgcStudentActionLogsCommand.php:166` | A **write** gate — creates `StudentActionLog` rows. Migrating changes which rows get written. Classify deliberately. |
| `app/Console/Commands/Academic/MigrateStudentProgressionEventsCommand.php:115,142,153` | One-shot migration command. Probably **historical**. |
| `app/Modules/Finance/Support/LifecycleDueExceptionReasonResolver.php:12-15` | `resolve(?Student)` → `forStatus($student?->status)`. **Migrate** — see H1, it must move in the same commit as `LifecycleDueItemPredicate`. |
| `app/Services/StudentAcademicSummaryService.php:1133` | Classify. |
| `?? $student->status` tails in already-migrated files | **whitelist-fallback**. |

### Comment-only false positives (must be excluded, red-team C4)

`app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php:57`,
`app/Modules/Finance/Support/BillingExceptionCollector.php:495`,
`app/Modules/Finance/Support/LifecycleStatusTimeline.php:16`,
`app/Modules/Finance/Queries/.../GetStudentLifecycleCohortMatrixQuery.php:22`
mention `students.status` **in comments only**. The phase-5 guard must strip
comments (`token_get_all`, drop `T_COMMENT` / `T_DOC_COMMENT`) before matching.

### Also record

- `app/Services/StudentService.php:546-556` `updateStudentStatus()` — **0 callers** in `app/`, `routes/`, `tests/`. **Dead code.** Do not label it a writer; schedule deletion (phase 4).
- **Value distribution (measured):** `intake_course` 151, `deferred` 49, `intake_pre_uni_gc` 24, `pending_course_opening` 6, `dropout` 4, `active` 1. **No student has `suspended`, `inactive`, `graduated`, or `pending`.**

## A2 — Primary-row tie-break contract

Three incompatible definitions exist in the plan and code today:

| Where | Effective rule |
|---|---|
| `app/Modules/Academic/Progression/Support/StudentLifecycleStatusReader.php:15-23` | `orderBy('id')` + `mapWithKeys` ⇒ later key overwrites earlier ⇒ **highest id wins** |
| Phase 3's planned `HasOne` relation | "oldest by id" ⇒ **lowest id wins** |
| Phase 2's `whereExists` | matches **any** row ⇒ neither |

Multiple `is_primary = 1` rows are **schema-legal**: the unique index is on a
generated column
`IF(is_primary = 1 AND enrollment_status = 'active', student_id, NULL)`
(`database/migrations/2026_07_18_161517_create_program_enrollments_table.php:23-25`),
so it only constrains **primary + active**. A student may legally hold one
primary-active row *plus* any number of primary-withdrawn/deferred rows. And
`MaterializeProgramEnrollmentAction.php:45-56` demotes only *active* siblings.

**DECISION (binding): highest `id` wins.** Matches the existing reader, so no
current behavior changes.

Implementations that must all encode it:

- PHP relation: `HasOne` + `->latestOfMany('id')` — **not** `oldestOfMany`.
- SQL twin: `pe.id = (SELECT MAX(id) FROM program_enrollments WHERE student_id = students.id AND is_primary = 1)` — **not** a bare `whereExists`.

Dev has **no duplicate primaries today**, so no existing test catches a
divergence. Phase 3's SQL-vs-PHP test **must** include a two-primary-row fixture
or this contract is unenforced.

## A3 — Shared SQL scope placement

Phases 1 and 3 were told to reuse a helper that phase 2 puts in
`app/Modules/Finance/Support/`. `tests/Feature/Architecture/DomainBoundaryArchitectureTest.php:7-35`
rejects any `use App\Modules\<Other>\…` import from a different module — and
that test is phase 5's green gate. The plan as written could not pass its own
acceptance criteria.

The regex only matches `App\Modules\…`, so **`App\Shared\…` is the sanctioned
channel** (same reasoning as `StudentLifecycleStatusReader` already living in
`App\Shared\Contracts\Academic`).

**Deliverable:** a Shared support class exposing the enrollment-first scope and
the projection CASE expression, encoding A2's tie-break, consumed by:

- phase 1 — `EloquentStudentRegistryStore::activeIdsForCampus()`
- phase 2 — `LifecycleDueItemPredicate`, the two lifecycle-exception queries
- phase 3 — `Student::scopeActive()`, `scopeClassRosterActive()`, both dashboards

Exact path resolved at implementation time under `app/Shared/` following the
directory's existing convention — **not** invented. Check what already lives
under `app/Shared/Support/` (or equivalent) first and match it.

> `// ponytail:` one CASE expression, one tie-break subquery, one file. If a
> second copy appears anywhere, phase 5's `rg -c` criterion fails.

## A4 — Drift baseline

Red-team C2: the plan predicted "11 students, `deferred`→`active`". **Measured
reality is 15 students across 5 transition classes**, and `active` is never
produced:

| Transition | Count |
|---|---|
| `deferred` → `intake_course` | 8 |
| `intake_pre_uni_gc` → `intake_course` | 3 |
| `intake_pre_uni_gc` → `deferred` | 2 |
| `intake_pre_uni_gc` → `dropout` | 1 |
| `pending_course_opening` → `dropout` | 1 |

**Deliverable:** run the phase-5 probe **before** phase 1 and paste the raw JSON
into the PR body as the baseline. Phase 5 gates on *diff vs. this recorded
baseline*, not against a predicted list.

## Related Code Files

**Read-only (all of them — this phase writes no production code except A3):**

- `app/Modules/Academic/Progression/Support/StudentLifecycleStatusReader.php:15-23`
- `database/migrations/2026_07_18_161517_create_program_enrollments_table.php:23-25`
- `app/Modules/Academic/Progression/Actions/MaterializeProgramEnrollmentAction.php:45-56`
- `tests/Feature/Architecture/DomainBoundaryArchitectureTest.php:7-35`
- every file surfaced by the A1 grep

**New:** one Shared support class (path per A3).

## Implementation Steps

1. Run the A1 grep. Read every hit. Build the classification table.
2. Resolve receiver types for `isActive()` / `isClassRosterActive()` / `->status` — discard non-`Student` receivers.
3. Paste the classification table into `plan.md` (replacing the placeholder) and into the PR body.
4. Write the A2 decision into this file's header and into the Shared class docblock.
5. Create the Shared scope class (A3). Add a unit test with a **two-primary-row** fixture proving highest-id wins.
6. Run the A4 probe; paste raw JSON into the PR body.
7. Confirm the Shared class placement passes `./scripts/dev.sh artisan test tests/Feature/Architecture/DomainBoundaryArchitectureTest.php`.

## Success Criteria

- Classification table exists in `plan.md`, and **every** file from the A1 grep appears in exactly one bucket. Count recorded (expected 36–45). Zero unassigned.
- `./scripts/dev.sh artisan test tests/Feature/Architecture/DomainBoundaryArchitectureTest.php` → **green** with the new Shared class present.
- Shared-scope unit test green, including the two-primary-row fixture asserting **highest id** wins.
- `rg -c "CASE\s*WHEN.*enrollment_status" app/` → **1**.
- A4 baseline JSON is in the PR body, verbatim, before phase 1 starts.
- A2 decision ("highest id wins") is stated in the Shared class docblock, not only in this plan.

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **Inventory still incomplete** — a 5th bucket of reads nobody grepped | Med × High | Cross-check the grep count against phase 5's guard-test match count. If they disagree, the inventory is wrong, not the guard. |
| **A2 decided but not encoded** — relation says oldest, SQL says any | Med × High | Step 5's two-primary-row test is the only mechanical enforcement. It must exist before phase 3. |
| **Shared class becomes a dumping ground** | Med × Low | One scope + one CASE. The `rg -c == 1` criterion is the ceiling. |
| **Phase 0 is skipped as "just planning"** | High × High | Phases 1, 2, 3 declare `dependencies: [0]`. A3 is a real file they import — they cannot compile without it. |
| **Receiver-type resolution done by grep anyway** | Med × Med | Explicitly called out: `isActive()` is on ≥6 models, 20 hits / 5 real. Step 2 is a reading task. |

### Rollback

A1, A2, A4 are documents — nothing to roll back. A3 is one new file with no
callers until phase 1; deleting it is the full rollback.
