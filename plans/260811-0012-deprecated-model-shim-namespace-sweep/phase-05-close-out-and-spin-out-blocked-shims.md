---
phase: 5
title: "Close out and spin out the 10 blocked shims"
status: pending
priority: P2
effort: "1h"
dependencies: [1, 2, 3, 4]
---

# Phase 5: Close out and spin out the 10 blocked shims

<!-- Rewritten after red-team session 1: was "Merchandise sweep and final shim removal". Merchandise moved into phase 4; this phase now closes the plan honestly at 20/30 -->

## Overview

Close this plan at its real endpoint — 20 of 30 shims deleted — and hand the
remaining 10 to a separate plan, because removing them is a module-boundary
refactor, not a namespace sweep. Documentation and one guard adjustment; no code
sweep, no migration.

## Requirements

- Functional: the plan's goals and success criteria state 20/30, not 30/30.
- Functional: the 10 blocked shims each carry a comment naming what blocks them.
- Functional: a follow-up plan exists for the boundary refactor, with the 12 files and the guard constraint recorded.
- Functional: the phase-3 guard permanently protects the 20 deleted names and still allow-lists the 10 survivors.
- Non-functional: no shim deleted, no caller swept in this phase.

## Architecture

### Why this plan stops at 20

`cross_context_concrete_imports` is `baseline: 0, mode: exact`
(`config/migration_debt.php:75-81`) and owned by "Platform architecture
maintainers". Its detector flags a module-namespaced file naming a *different*
module (`MigrationDebtInventory.php:349-388`); `MigrationDebtGuard.php:109`
errors on any non-zero.

`App\Models\X` is namespace-neutral, so a cross-module model read routed through
a shim is invisible to that rule. Sweeping the shim makes it visible and breaks
the guard. **The 10 surviving shims are load-bearing for a zero-tolerance
architectural boundary** — they are not merely un-swept debt.

Deleting them requires routing 12 cross-module reads through
`App\Shared\Contracts\*`, which is real architectural work with a different
owner, different risk profile, and different sign-off. Folding it into a P3
namespace-tidy plan would have smuggled a boundary refactor in as an import swap.

### The 10 survivors and their blockers

| Shim | Blocked by | Direction |
|---|---|---|
| `ApplicationDocument` | `UpsertCrmApplicationAction`, `IngestionController`, `GetApplicantDocumentChecklistQuery` | Admissions → Upload |
| `ApplicationDocumentType` | `UpsertCrmApplicationAction`, `GetApplicantDocumentChecklistQuery`, `ListApplicationsQuery` | Admissions → Upload |
| `UploadRecord` | `Engagement\Models\FormResponse`, `Engagement\Models\QueryReply` | Engagement → Upload |
| `FormResponse` | `Upload\Models\UploadRecord` | Upload → Engagement |
| `QueryReply` | `Upload\Models\UploadRecord` | Upload → Engagement |
| `QueryTicket` | `Upload\Models\UploadRecord` | Upload → Engagement |
| `FormTarget` | `Academic\Delivery\Queries\GetCourseOfferingSurveyQuery` | Academic → Engagement |
| `Room` | `Academic\Delivery\Queries\GetClassSessionFormOptionsQuery`, `Academic\Delivery\Support\LecturerTimetableService` | Academic → Facilities |
| `Building` | `Academic\Support\CampusBuildingCountReader` | Academic → Facilities |
| `GoldTransaction` | `Engagement\Actions\EventParticipationOperations` | Engagement → Merchandise |

Note the Upload ↔ Engagement pair is **circular** (`UploadRecord` ↔
`FormResponse`/`QueryReply`/`QueryTicket`), so those four cannot be untangled one
at a time — a point for the follow-up plan.

Also note `app/Models/Answer.php` imports `App\Models\UploadRecord`. It is a
global model, not module-namespaced, so it does not trip the boundary rule and is
not itself a blocker — but it must be swept whenever `UploadRecord` finally is.

### Guard end state

After phase 4, `DeprecatedModelShimArchTest` holds:

- `ALL_MIGRATED_MODELS` — all 30 names, permanent. Drives the importer regex and
  the `class_exists` assertion. **Never shrinks.**
- `SHIMMED_MODELS` — the 10 survivors. Assertion 1 requires exactly these
  `class_alias` files to exist, so an 11th reappearing fails and a 10th
  disappearing without a plan update also fails.
- The `class_exists('App\Models\<Name>') === false` assertion covers the 20
  deleted names, catching a subclass-style or subdirectory reintroduction that a
  `class_alias(` text grep would miss.

No empty-list early return is needed or wanted: `ALL_MIGRATED_MODELS` is fixed at
30, so the importer regex is never empty. The earlier plan's
`if (SHIMMED_MODELS === []) { return; }` idea is withdrawn — it would have turned
the importer guard into a permanent no-op and left plan Goal 3 unmet.

### Residue accepted, not cleaned

Recorded as known and deliberately out of scope:

- `telescope_entries.content` — ~236 dev rows naming shimmed FQCNs. Debug tool
  data, not a resolution key; Telescope prunes on its own schedule.
- `activity_log.properties` (JSON) — snapshot payloads, not used to resolve a
  class.
- The five columns holding `App\Models\%` for *non-shimmed* models
  (`payment_applications.source_ref_type`, `discount_allocations.source_ref_type`,
  `personal_access_tokens.tokenable_type`, `activity_log.causer_type`,
  `finance_charges.source_type`) — explicitly out of scope per Non-Goals. Verified
  clean of shimmed models, including `lecturer_access_grants.token_subject_type`
  and `faculty_access_eligibility_outbox.token_subject_type`, which earlier
  revisions never mentioned.
- `shared_model_imports` at 401 vs a 289 ceiling — a pre-existing red gate this
  plan does not own and must not edit.

## Related Code Files

- Modify: `app/Models/{ApplicationDocument,ApplicationDocumentType,UploadRecord,FormResponse,FormTarget,QueryReply,QueryTicket,Room,Building,GoldTransaction}.php` — extend each `@deprecated` docblock with the blocking caller and a link to the follow-up plan
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — docblock only, describing the 20/10 end state
- Modify: `plans/260811-0012-deprecated-model-shim-namespace-sweep/plan.md` — final status
- Create: `plans/<date>-<slug>/plan.md` — the boundary-refactor follow-up

## Implementation Steps

1. **Annotate the 10 survivors.** Each shim's docblock currently says only
   "Kept for backward compatibility until callers are swept". Replace with the
   real reason, e.g.:

   ```php
   /**
    * @deprecated Use \App\Modules\Upload\Models\UploadRecord instead.
    *
    * This shim cannot be deleted yet. App\Models\* is namespace-neutral, so the
    * cross-module reads in App\Modules\Engagement\Models\{FormResponse,QueryReply}
    * route through it without tripping the zero-tolerance
    * cross_context_concrete_imports rule (config/migration_debt.php). Removing
    * this shim requires routing those reads through App\Shared\Contracts first.
    * See plans/<follow-up-plan>.
    */
   ```

   A future reader must not mistake these for forgotten debt.

2. **Verify the guard's end state** — 10 in `SHIMMED_MODELS`, 30 in
   `ALL_MIGRATED_MODELS`, and prove both directions still bite:

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Architecture/DeprecatedModelShimArchTest.php
   ```

   Then, as a one-off proof (revert after): re-create one deleted shim as a
   subclass and confirm the `class_exists` assertion goes red.

3. **Final exact-match morph check.** Not the "zero hits" phrasing an earlier
   revision used — `activity_log.subject_type` legitimately holds ~178k
   `App\Models\%` rows for non-shimmed models, so "zero hits" is unsatisfiable
   and would have been waved through. Query the 30 exact strings instead:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="\$fq = array_map(fn(\$m) => 'App\\\\Models\\\\'.\$m, [<the 30 names>]); foreach (['activity_log.subject_type','activity_log.causer_type','personal_access_tokens.tokenable_type','finance_charges.source_type','discount_allocations.source_ref_type','payment_applications.source_ref_type'] as \$col) { [\$t,\$c] = explode('.', \$col); echo \$col.' = '.DB::table(\$t)->whereIn(\$c, \$fq)->count().PHP_EOL; }"
   ```

   Every count must be 0. Do **not** use `./scripts/dev.sh mysql -e "..."` —
   `scripts/dev.sh:54-57` does not `shift` or forward arguments, so `-e` is
   silently dropped and the command exits 0 with no output, which is
   indistinguishable from "no rows".

4. **Write the follow-up plan** for the boundary refactor. It must carry: the 10
   shims, the 12 files with their direction, the circular Upload ↔ Engagement
   cluster, the `cross_context_concrete_imports` constraint and its owner, the
   `app/Models/Answer.php` caller, and the open decision — route through
   `App\Shared\Contracts\*` (correct, larger) versus raise the ceiling with an
   allow-list (cheap, needs Platform architecture maintainers' sign-off).

5. **Update `plan.md`** to 20/30 in Goals, Success Criteria, and status. Do not
   leave a 30/30 claim anywhere.

## Success Criteria

- [ ] All 10 surviving shims annotated with their blocking caller and a link to the follow-up plan
- [ ] `SHIMMED_MODELS` = 10, `ALL_MIGRATED_MODELS` = 30; guard green
- [ ] Subclass-reintroduction proof performed and reverted
- [ ] All 6 morph columns return 0 for the 30 exact FQCNs, via `whereIn` — not `LIKE`, not `dev.sh mysql -e`
- [ ] Follow-up boundary-refactor plan created, carrying the constraint and the open decision
- [ ] `plan.md` states 20/30 with no surviving 30/30 claim
- [ ] `cross_context_concrete_imports` still `0`; `config/migration_debt.php` and `MigrationDebtContract` untouched by this plan
- [ ] Accepted residue recorded (telescope, `activity_log.properties`, the 5 non-shimmed columns, the 401/289 gate)

## Risk Assessment

| Risk | Mitigation |
|---|---|
| The 10 survivors get mistaken for forgotten debt and swept by someone unaware of the boundary rule | Step 1 puts the reason in each file's docblock, where a developer about to delete it will read it; the follow-up plan carries the full analysis |
| The follow-up plan is never written and the analysis is lost | Step 4 is a success criterion of this phase, not an aspiration |
| "Zero hits" style criteria get waved through | Step 3 uses exact `whereIn` over the 30 FQCNs across all 6 columns and requires a numeric 0, not absence of output |
| A verification command silently no-ops | Step 3 names the `dev.sh mysql` argument-dropping bug explicitly and mandates the `tinker` form |
| Guard degrades into a tautology once most shims are gone | `ALL_MIGRATED_MODELS` is fixed at 30 and drives both the importer regex and the `class_exists` assertion; the empty-list early return is withdrawn |
| Plan closed while claiming a goal it did not meet | Step 5 plus an explicit success criterion; the Goals table is amended to 20/30 |
