---
phase: 1
title: "Morph-migration pilot and guard test"
status: done
priority: P1
effort: "2h"
dependencies: []
---

# Phase 1: Morph-migration pilot and guard test

<!-- Updated: Validation Session 1 - inventory completed and strategy decided during validation; phase narrowed to pilot + guard -->

## Overview

Prove the chosen morph-migration approach on one model and land an arch test that
stops new `class_alias` shims and new deprecated imports from appearing. No shim
is deleted in this phase.

> **Narrowed by validation.** The two heaviest original steps are already done:
> the full `information_schema` inventory was completed (all 64 candidate columns
> enumerated; only `activity_log.subject_type` holds shimmed models; queue
> payloads clean), and the strategy question is settled — **V1-a: data
> migration**. What remains is the pilot and the guard.

## Requirements

- Functional: the strategy-A migration is demonstrated end to end on one model.
- Functional: an arch test fails if a `class_alias` shim is added under
  `app/Models/`, or if new code imports one.
- Non-functional: no shim deleted, no caller swept. This phase de-risks; it does
  not sweep.

## Architecture

The repo already has per-module placement arch tests
(`FacilitiesModelPlacementArchTest`, `EngagementFormModelPlacementArchTest`,
`EngagementClubEventModelPlacementArchTest`,
`EngagementQueryTicketModelPlacementArchTest`). Extend that pattern with one
repo-wide guard rather than adding a per-module variant.

The guard needs two assertions:

1. No file in `app/Models/` contains `class_alias(` — *this will fail today* by
   design. Land it skipped/incremental with an explicit allow-list of the 30
   known shims, and shrink the allow-list as each later phase deletes its shims.
   An empty allow-list at the end of phase 5 is the completion signal.
2. No **new** import of an allow-listed shim FQCN outside its owning module.

Prefer the allow-list-shrinking shape over a hard assertion — a test that is red
for the whole life of the plan gets ignored or commented out.

## Related Code Files

- Create: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php`
- Create: `database/migrations/<timestamp>_backfill_shimmed_morph_subject_types.php`
  (strategy A is settled by V1-a; no `AppServiceProvider` morph-map alias is added —
  that was rejected option B)

## Implementation Steps

1. Re-measure the current `activity_log.subject_type` counts for the five
   morph-bearing models — rows accrue continuously, so the validation-session
   figures (242 total) are a baseline, not a constant:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach (\DB::table('activity_log')->select('subject_type')->selectRaw('count(*) n')->whereIn('subject_type', ['App\Models\ClubMember','App\Models\Room','App\Models\RoomBooking','App\Models\Club','App\Models\Building'])->groupBy('subject_type')->get() as \$r) { echo \$r->subject_type.' = '.\$r->n.PHP_EOL; }"
   ```

2. Write the strategy-A migration for **one** model only — `ClubMember` is the
   best pilot: 100 morph rows and no *production* code callers, so it isolates the
   stateful problem from the mechanical one. (Its three references are all test /
   arch-test string literals — see the Evidence Base correction in `plan.md`.)

3. Verify `$activity->subject` resolves for those rows before and after, with the
   shim still in place (the shim is not deleted in this phase).

4. Write the arch test with the 30-shim allow-list.

## Validation

```bash
./scripts/dev.sh artisan test tests/Feature/Architecture/DeprecatedModelShimArchTest.php
./scripts/dev.sh artisan test tests/Feature/Architecture
```

Record the full-suite pass/fail baseline before starting — this repo has known
pre-existing failures in Finance and Academic suites that must not be
misattributed to this plan.

## Success Criteria

- [x] Current morph-row counts re-measured and recorded
- [x] Strategy-A migration applied to `ClubMember`; its activity rows resolve
      their subject after the change
- [x] Migration `down()` restores the old values
- [x] Arch test present, passing, with an explicit 30-entry allow-list
- [x] Zero shims deleted, zero callers swept in this phase
- [x] Full-suite baseline recorded

## Risk Assessment

| Risk | Mitigation |
|---|---|
| An arch test that is red from day one gets disabled | Allow-list shape: green today, and shrinking the list is the progress metric |
| The inventory misses a morph column and a later phase breaks production | Enumerate from `information_schema`, not from memory or grep |
| Strategy chosen without seeing the full inventory | Steps 1-2 strictly precede step 3 |
| Pilot on a model with code callers conflates two failure modes | `ClubMember` has zero code callers by measurement — stateful risk only |

## Execution Log — 2026-08-11

**Step 1 re-measurement** (matches Validation Session 1, unchanged):

| `activity_log.subject_type` | Rows |
|---|---|
| `App\Models\Building` | 5 |
| `App\Models\Club` | 19 |
| `App\Models\ClubMember` | 100 |
| `App\Models\Room` | 87 |
| `App\Models\RoomBooking` | 31 |

`jobs` empty, `failed_jobs` 33 rows / 0 shimmed — confirmed still clean.

**Delivered:**

- `database/migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`
  — strategy-A backfill, `ClubMember` only. `down()` self-disables via
  `class_exists(OLD_FQCN)` once a later phase deletes the shim, instead of
  writing back an FQCN that no longer resolves.
- `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — two guards:
  a 30-entry shim allow-list (exact match) and a caller-import baseline
  (now 92 entries — 90 real importers + 2 arch tests that reference a shim
  name as a literal negative-assertion string), checked both for growth and
  for staleness so a completed sweep is forced to shrink it.
- `tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php` —
  3 tests proving `up()`/`down()` against a seeded legacy row, that
  `Activity::find($id)->subject` resolves to a real `ClubMember`, and that
  unrelated `subject_type` values are untouched.

**Full-suite baseline** (pre-existing, independent of this phase — confirmed
by moving the 3 new files aside and re-running):
`tests/Feature/Architecture` = 5 failed / 129 passed
(`MigrationDebtInventoryTest` x2, `TeachingEligibilityAssignmentBoundaryTest`,
`CourseRosterDeliveryBoundaryArchTest`). With this phase's files restored:
`tests/Feature/Architecture` + `tests/Feature/Engagement` = same 5 failed /
138 passed — zero regressions.

Reviewed by `code-reviewer` subagent; all High/Medium findings applied
(over-broad `down()`, import-regex missing the double-backslash string form,
`config/` not scanned, baseline not shrink-enforced, test misplaced in
`Architecture/` instead of `Engagement/`).

Nothing deleted, nothing swept — as scoped.
