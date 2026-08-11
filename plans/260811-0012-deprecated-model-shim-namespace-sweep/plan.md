---
title: "Deprecated model shim namespace sweep"
description: "Repoint all callers from App\\Models\\* class_alias shims to the canonical App\\Modules\\<Owner>\\Models\\* namespace, then delete the shims."
status: in-progress
priority: P3
effort: "4-5 PRs, ~1-2h each"
tags: [modularization, arch-test, php, tech-debt]
created: 2026-08-11
---

# Deprecated model shim namespace sweep

## Overview

Plan `260809-1557-legacy-model-module-migration` (done) moved 30 models into
their owning modules and left `app/Models/X.php` behind as a one-line
`class_alias()` deprecated shim so cross-module callers kept working:

```php
class_alias(\App\Modules\Upload\Models\ApplicationDocumentType::class, ApplicationDocumentType::class);
```

That plan explicitly deferred the caller sweep (line 40: *"a separate follow-up
plan can sweep those and drop the shim"*). This is that plan.

Mostly mechanical — swap import statements, delete shim files, add an arch test
that stops the shims from coming back — **with one stateful complication**:
`activity_log` persists shimmed FQCNs as morph strings, so deleting those shims
requires handling the data too. See Evidence Base.

## Motivation

Not urgent — the shims work. The cost is that `App\Models\X` and
`App\Modules\<Owner>\Models\X` both resolve to the same class, so new code keeps
picking the deprecated one by autocomplete or by copying a neighbouring file.
Every day the shims live, the debt grows slightly.

Trigger for writing this now: plan
`260810-2345-student-applications-crm-columns-and-filters` hit the shim while
adding a migration and had to carve out an explicit "use canonical namespace"
rule to avoid propagating it.

## Evidence Base

Measured 2026-08-11, **re-measured during phase 2-5 scaffolding**. 30 shim files
in `app/Models/`, **92** files referencing them.

<!-- Updated: phase 2-5 scaffolding - exact per-module file sets; 86 -> 92; disjointness and free-deletion claims corrected -->

The original 86 undercounted because it matched `use` statements only. The real
detector (`\bApp\\{1,2}Models\\{1,2}<Model>\b`, also used by
`DeprecatedModelShimArchTest`) additionally catches the escaped
double-backslash form used in string literals, and scans `config/` too.
92 is confirmed three ways: the guard's baseline array, the union of the four
per-module file lists, and a direct repo-wide grep.

**Grouped by owning module** — exact caller counts and exact unique-file totals:

| Module | Shims (callers) | Unique files | Morph rows | Phase |
|---|---|---|---|---|
| Upload | `ApplicationDocumentType` (14), `ApplicationDocument` (13), `UploadRecord` (8) | 25 | 0 | 2 |
| Engagement | `Form` (11), `FormVersion` (11), `FormTarget` (10), `Event` (9), `FormResponse` (9), `QueryTicket` (6), `QueryReply` (5), `FormSection` (4), `ClubMember` (3), `EventParticipant` (3), `Club` (2), `ClubMemberRoleHistory` (1), `FormResultVisibility` (1), `FormSurvey` (1), `QueryAssignment` (1), `QueryTopic` (1) | 23 | 19 | 3 |
| Facilities | `Room` (27), `RoomBooking` (8), `Building` (5), `RoomBookingAction` (1) | 31 | 123 | 4 |
| Merchandise | `GoldTransaction` (10), `Merchandise` (9), `MerchandiseVariant` (8), `RedemptionOrder` (6), `StockMovement` (5), `RedemptionOrderItem` (2), `MerchandiseImage` (0) | 17 | 0 | 5 |

Shim counts overlap within a module (one file may reference several shims), so
per-shim counts sum higher than the unique-file total. 25+23+31+17 = 96 minus
4 files that span two modules = **92**.

### Only ONE shim is a free deletion (correction)

An earlier revision listed 8 zero-code-caller shims and called 6 of them "free
deletions". Both figures are wrong:

- **`MerchandiseImage` is the only shim with zero references anywhere.**
- Five of the others — `QueryTopic`, `QueryAssignment`, `FormSurvey`,
  `FormResultVisibility`, `ClubMemberRoleHistory` — are each referenced once, all
  in the same 16-FQCN negative-assertion string list at
  `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php:47-51`.
  That list must be deleted in phase 3.
- `ClubMember` and `Club` additionally carry persisted morph rows.

Root cause of both errors, twice over: **counting only `use`-statement imports.**
Persisted data is a caller; so is a string literal in a test.

### Phase file sets are NOT disjoint (correction)

An earlier revision claimed phases 2-5 "own disjoint file sets and can run in
any order". False — the shims import each other across module boundaries:

- `app/Modules/Engagement/Models/{FormResponse,QueryReply}.php` import
  `App\Models\UploadRecord` → swept by **phase 2** (Upload), inside the
  Engagement module.
- `app/Modules/Upload/Models/UploadRecord.php` imports three Engagement shims →
  swept by **phase 3**, inside the Upload module.
- `app/Modules/Engagement/Actions/EventParticipationOperations.php` imports
  `App\Models\GoldTransaction` → swept by **phase 5**.

Four files are referenced by two module phases and will merge-conflict if those
phases run in parallel:

| File | Phases |
|---|---|
| `tests/Feature/Api/V1/Student/QueryTicketApiTest.php` | 2 + 3 |
| `tests/Feature/Form/AdminQueryInboxTest.php` | 2 + 3 |
| `tests/Feature/Form/QueryTicketWorkflowTest.php` | 2 + 3 |
| `tests/Feature/Gold/ReclaimGoldRewardTest.php` | 3 + 5 |

Rewriting an import to a canonical FQCN never requires the target model's own
phase to be finished — the canonical class already exists. So cross-module edits
are safe; only the *merge order* matters. Hence phase 3 depends on 2, and phase 5
on 3. Phase 4 is genuinely disjoint and may run in parallel with 5.

### Placement arch tests block shim deletion (correction)

All six per-module placement arch tests assert the shim **still exists**:

```php
expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");
expect($contents)->toContain('class_alias(')
```

`UploadModelPlacementArchTest:34`, `FacilitiesModelPlacementArchTest:34`,
`EngagementClubEventModelPlacementArchTest:35`,
`EngagementQueryTicketModelPlacementArchTest:35`,
`EngagementFormModelPlacementArchTest:51`, `MerchandiseModelPlacementArchTest:51`.

So Architecture step 5 below ("run the module's existing placement arch test") is
incomplete: deleting a shim turns its module's test red by design. Each phase
must **delete that block** in the same PR — not invert it, because phase 1's
`DeprecatedModelShimArchTest` already asserts the exact remaining-shim set
repo-wide. The first `it()` block in each test (real class lives under
`app/Modules/<Owner>/Models`) stays and remains valuable.

One exception: `FacilitiesDeliveryBoundaryArchTest` lines 50/53/63 assert
Academic/Delivery files do **not** contain `App\Models\Room`. Those must be
**retargeted** to the canonical FQCN, not deleted, or the boundary silently
loses its teeth. See phase 4.

### Persisted morph strings — confirmed blocker

`activity_log.subject_type` stores fully-qualified class names as data.

<!-- Updated: Validation Session 1 - full information_schema enumeration; 5 models / 242 rows, confined to one column -->

**Full enumeration** (validation session, 2026-08-11): all 64 `%_type` / `%_class`
columns in the schema were checked. Six hold `App\Models\%` values:
`payment_applications.source_ref_type`, `discount_allocations.source_ref_type`,
`personal_access_tokens.tokenable_type`, `activity_log.subject_type`,
`activity_log.causer_type`, `finance_charges.source_type`.

**Only one of them holds a *shimmed* model.** The other five reference models
that legitimately still live in `app/Models/` and were never part of
`260809-1557`. The blast radius is therefore confined to a single column:

| `activity_log.subject_type` | Rows at plan time | Remaining |
|---|---|---|
| `App\Models\ClubMember` | 100 | **0 — migrated by phase 1** |
| `App\Models\Room` | 87 | 87 (phase 4) |
| `App\Models\RoomBooking` | 31 | 31 (phase 4) |
| `App\Models\Club` | 19 | 19 (phase 3) |
| `App\Models\Building` | 5 | 5 (phase 4) |
| **Total** | **242** | **142** |

<!-- Updated: phase 2-5 scaffolding - phase 1 migration applied on dev asia; ClubMember column now 0 -->

**Phase 1's backfill is already applied to the dev `asia` database.** The 100
`ClubMember` rows now carry the canonical FQCN; 99 resolve their subject and the
single null is a pre-existing orphan (`subject_id=3`, hard-deleted from
`club_members`) that resolved to null before the migration too — the migration
rewrites only the type string, never `subject_id`. Phase 3 must therefore **not**
write a second `ClubMember` backfill; it owns `Club` only.

`jobs` is empty. `failed_jobs` holds 33 rows, 17 mentioning `App\Models\` — none
of them a shimmed model. So no queue-payload migration is needed today, though
the count must be re-checked immediately before deletion.

`Relation::morphMap()` in `AppServiceProvider.php:177` maps only
`course`/`semester`/`department` and the `RoomBooking::BOOKED_BY_*` constants —
**none of these five FQCNs**. So they resolve by literal class name, which today
works *only because the `class_alias` shim exists*.

Deleting the shim for `ClubMember`, `Room`, `RoomBooking`, `Club`, or `Building`
without handling this makes `$activity->subject` throw for those 242 rows.

This is why the sweep is **not** purely mechanical, and why the zero-caller
shims are not a free win. Re-run the measurement before each phase — new rows
accumulate continuously.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | No production or test code imports `App\Models\<migrated model>` | P1 |
| 2 | All 30 shim files deleted | P1 |
| 3 | An arch test fails if a shim or a shim import reappears | P1 |
| 4 | Zero behavior change — no logic or schema edits. Data edits limited to rewriting persisted morph strings | P1 |
| 5 | Previously-logged activity still resolves its subject after the sweep | P1 |

## Non-Goals

- No model refactoring, renaming, or logic change of any kind.
- No migration of models still legitimately living in `app/Models/` that were
  never part of `260809-1557`.
- No changes to the module boundaries themselves.

## Architecture

Mechanical for the code half, stateful for the five morph-bearing models. One
module per phase so each PR is independently reviewable and revertable:

1. Record the pre-sweep baseline for the suites the phase touches.
2. Re-measure callers and morph rows with the real detector:
   `grep -rlP '\bApp\\{1,2}Models\\{1,2}<Model>\b' app tests database routes config --include='*.php'`
3. Rewrite `use` statements **and** inline/string FQCN references
   (`'App\Models\X'` appears in morph maps, docblocks, arch-test literals, and
   potentially factory resolution — **check `Relation::morphMap`, `config/*.php`,
   `database/factories/`, and negative assertions in arch tests**, not just
   `use` lines).
4. Run the module's morph backfill migration, if it has rows — after step 3, same PR.
5. Delete the shim file.
6. **Delete the shim-must-exist block** from the module's placement arch test
   (see the Evidence Base correction — this step is mandatory, not optional).
7. Shrink `SHIMMED_MODELS` + `SHIMMED_MODEL_IMPORT_BASELINE` in
   `DeprecatedModelShimArchTest`, and lower the `shared_model_imports` baseline
   in `config/migration_debt.php` so the two guards do not drift.
8. Run the module's suites and compare against the step-1 baseline.

The repo already has per-module placement arch tests
(`UploadModelPlacementArchTest`, `FacilitiesModelPlacementArchTest`,
`EngagementFormModelPlacementArchTest`,
`EngagementClubEventModelPlacementArchTest`,
`EngagementQueryTicketModelPlacementArchTest`,
`MerchandiseModelPlacementArchTest`). Extend the pattern rather than inventing a
new mechanism. Phase 1 added the repo-wide guard on top.

**Highest-risk step is 3, and it is confirmed live, not hypothetical.** A
`class_alias` keeps *string* class references working, and `activity_log` held
242 such rows across 5 models (142 remaining after phase 1 — see Evidence Base).

**Required handling before any shim deletion.**

<!-- Updated: Validation Session 1 - strategy A chosen, deletion timing chosen -->

**Decision (V1): strategy A — data migration.** Each module phase carries a
migration rewriting its own models' rows:

```sql
UPDATE activity_log
   SET subject_type = 'App\Modules\<Owner>\Models\<X>'
 WHERE subject_type = 'App\Models\<X>';
```

`down()` reverses it. Because rows keep accruing until the callers stop writing
the old FQCN, the migration runs **after** the caller sweep in the same PR, and
the row count is re-measured immediately before it.

Rejected: **B** (permanent `morphMap` alias) would leave the deprecated names in
config forever — the debt moves rather than clears, which defeats the plan's
only goal. **C** (both) is unnecessary: `jobs` is empty and `failed_jobs`
contains no shimmed models, so there is no in-flight-payload window to protect.

**Decision (V1): delete each shim in the same PR as its module's caller sweep.**
Verified safe — no queued job payload references a shimmed model. Keeps each PR
self-contained and independently revertable.

Whichever model is swept, it must stop *writing* the old FQCN before its rows are
migrated — that happens automatically once callers use the canonical namespace,
since the logger records `get_class($model)`.

Re-verify before each deletion (the full enumeration is done, but data moves):

- re-run the `activity_log.subject_type` count for the module's models
- re-check `jobs` / `failed_jobs` payloads (empty / clean as of 2026-08-11)
- `config/*.php`, factory resolution, and any quoted `'App\Models\X'` literal

The other 63 `%_type` / `%_class` columns were enumerated and are clear of
shimmed models — that sweep does not need repeating per phase.

## Phases

| # | Phase | Shims | Files | Morph rows | Status | Depends on |
|---|-------|---|---|---|--------|-----------|
| 1 | [Morph-migration pilot and guard test](./phase-01-start.md) | — | 3 new | 100 migrated | Done | — |
| 2 | [Upload module sweep](./phase-02-upload-module-sweep.md) | 3 | 25 | 0 | Pending | 1 |
| 3 | [Engagement module sweep](./phase-03-engagement-module-sweep.md) | 16 | 23 | 19 (`Club`) | Pending | 1, 2 |
| 4 | [Facilities module sweep](./phase-04-facilities-module-sweep.md) | 4 | 31 | 123 | Pending | 1 |
| 5 | [Merchandise sweep and final shim removal](./phase-05-merchandise-sweep-and-final-shim-removal.md) | 7 | 17 | 0 | Pending | 1, 3 |

<!-- Updated: phase 2-5 scaffolding - all phases now scaffolded; dependency chain corrected for shared files -->

Phase 1 landed the migration pilot and the guard test. The strategy question it
originally carried is settled (V1-a) and its inventory is complete — see the
Validation Log.

**Ordering rationale.** Phase 2 is deliberately next: Upload's three models have
**zero** persisted morph rows, so it proves the mechanical sweep end to end
without the stateful risk. Phase 3 then follows because it shares three test
files with phase 2 (see Evidence Base) and must merge after it. Phase 5 shares
one test file with phase 3 and follows it. **Phase 4 is genuinely disjoint** —
it depends only on phase 1 and may run in parallel with phase 5, but it carries
the largest caller set (31) and the largest morph blast radius (123 rows), so
running it alone is advisable.

Effective order: **1 → 2 → 3 → 5**, with **4** insertable anywhere after 1.

## Success Criteria

- [ ] No `class_alias` shim remains in `app/Models/`
- [ ] No code references `App\Models\<migrated model>` in `app`, `tests`,
      `database`, `routes`, `config` — except the two intentional data-holders
      documented in phase 3 (the ClubMember pilot migration and its test), if
      those are kept
- [ ] No `activity_log.subject_type` (or any other morph column) still holds a
      deleted shim's FQCN — verified by re-running the full `information_schema`
      enumeration and getting zero hits
- [ ] `$activity->subject` resolves for previously-affected rows (spot-check
      Room, RoomBooking, ClubMember), with every remaining null traced to a
      hard-deleted subject row rather than the migration
- [ ] `DeprecatedModelShimArchTest` fails if a `class_alias` shim is reintroduced
      under `app/Models/`, and its `SHIMMED_MODELS` list is empty
- [ ] All six per-module placement arch tests still green with their
      shim-must-exist blocks removed
- [ ] `FacilitiesDeliveryBoundaryArchTest`'s Room negative assertions retargeted
      to the canonical FQCN and proven to still fail on a violation
- [ ] `config/migration_debt.php` `shared_model_imports` baseline lowered to the
      real post-sweep count
- [ ] Full test suite green, with the same pass/fail baseline as before the sweep
- [ ] No diff outside import lines, shim deletions, morph backfill migrations,
      and the arch-test/baseline bookkeeping

## Risks

| Risk | Mitigation |
|---|---|
| **Confirmed:** `activity_log.subject_type` held 242 rows of shimmed FQCNs across 5 models (142 left after phase 1); deleting those shims breaks `$activity->subject` | Strategy A (data migration) proven by phase 1; phases 3 and 4 apply it before deleting their shims |
| A *different* string class reference (docblock, arch-test literal, factory resolver, morph map) breaks silently | Each phase re-greps with the `\\{1,2}` detector that catches escaped literals, and the phase-1 guard fails on anything missed; deletion is the last step of each phase |
| **Confirmed:** deleting a shim reddens its module's placement arch test, which asserts the shim exists | Each phase deletes that block in the same PR — mandatory step 6 in Architecture |
| **Confirmed:** `FacilitiesDeliveryBoundaryArchTest` loses its Delivery-must-not-touch-Room boundary if its negative assertions are deleted rather than retargeted | Phase 4 step 8 retargets them and proves they still fail on a violation |
| Morph rows keep accruing while the sweep is in progress | Re-run the measurement immediately before each deletion, not once at plan time |
| In-flight queued jobs serialized with the old FQCN fail after deploy | Drain or check the queue before deploying a phase; `jobs` empty and `failed_jobs` clean as of 2026-08-11, re-check per phase |
| Large mechanical diff hides a real edit | One module per PR; reviewer greps rather than reads and checks the diff contains only import lines, deletions, and the documented bookkeeping |
| Cross-module edits (phase 2 touching Engagement files, phase 3 touching Upload, phase 5 touching Engagement) look out of scope | Documented per phase with the reason; leaving them would fail the phase-1 guard |
| Parallel phases collide on the 4 shared test files | Dependency chain 1 → 2 → 3 → 5 encodes it; only phase 4 is safe to parallelize |
| `config/migration_debt.php` `shared_model_imports` (baseline 289, mode `max`) drifts from reality as shims go | Each phase lowers it in the same PR — Architecture step 7 |
| Baseline test failures get attributed to this sweep | Record the pre-sweep pass/fail baseline first — the repo has known pre-existing failures in Finance and Academic suites, plus 5 in `tests/Feature/Architecture` |

## Validation Log

### Session 1 — 2026-08-11

**Verification** (Light tier, 1 phase): 12 claims checked. 10 verified, **2
corrected**.

| Claim | Result |
|---|---|
| 30 `class_alias` shims in `app/Models/` | VERIFIED |
| 86 files import them | VERIFIED |
| Per-module caller counts | VERIFIED |
| `260809-1557` line 40 defers this to a follow-up plan | VERIFIED |
| `morphMap` at `AppServiceProvider.php:177` maps none of the shimmed FQCNs | VERIFIED |
| Per-module placement arch tests exist | VERIFIED |
| Morph rows = 3 models / 218 rows | **CORRECTED → 5 models / 242 rows** (added `Club` 19, `Building` 5) |
| 8 zero-caller shims are free deletions | **CORRECTED → 6.** `ClubMember` (100 rows) and `Club` (19) carry morph rows despite zero code imports |
| Only `activity_log` holds shimmed FQCNs | VERIFIED by full enumeration — all 64 `%_type`/`%_class` columns checked; 6 hold `App\Models\%`, only `activity_log.subject_type` holds *shimmed* models |
| Queue payloads hold shimmed FQCNs | VERIFIED ABSENT — `jobs` empty; `failed_jobs` 33 rows, 17 mention `App\Models\`, none shimmed |

The two corrections both came from the same root error: measuring "callers" as
code imports only. Persisted data is also a caller.

**Interview decisions:**

| # | Decision |
|---|---|
| V1-a | **Strategy A** — data migration per module phase. B rejected (moves the debt into config), C rejected (no in-flight-payload window to protect) |
| V1-b | **Delete each shim in the same PR** as its module's caller sweep — verified safe |
| V1-c | **Stay P3, backlog.** Not scheduled. The shims work; pick this up when someone next trips over the deprecated namespace |

### Whole-Plan Consistency Sweep

Re-read `plan.md` and `phase-01-start.md`.

- 218 → 242 and 3 → 5 models reconciled in Overview, Evidence Base,
  Architecture, and the Risks table. No stale `218` remains.
- "Purely mechanical" corrected to "mechanical for the code half, stateful for
  the five morph-bearing models", consistent with the Overview.
- Strategy A/B/C menu replaced by the V1-a decision; no surviving text asks the
  reader to choose.
- Phase-1 still pilots on `ClubMember` — still valid, and now also the largest
  morph-row holder.
- Phase 1's "decide the strategy" step is superseded by V1-a; its inventory step
  is superseded by the completed enumeration. Phase 1 narrows to the pilot
  migration plus the arch test.

Unresolved contradictions: **none**.

### Session 2 — 2026-08-11 (phase 2-5 scaffolding)

Scouted the exact per-module file sets before writing the four phase files.
**4 claims corrected, 3 new blockers found.**

| Claim | Result |
|---|---|
| 90 files reference the shims | **CORRECTED → 92.** Confirmed three ways: guard baseline array, union of per-module lists, repo-wide grep |
| Per-module caller counts (from session 1) | **CORRECTED.** `Room` 26→27, `UploadRecord` 9→8, `Form` 10→11, `FormVersion` 10→11, `FormTarget` 9→10, `FormResponse` 8→9, `Event` 8→9, `QueryTicket` 5→6, `QueryReply` 4→5, `FormSection` 3→4, `EventParticipant` 2→3 |
| 6 shims are free deletions | **CORRECTED → 1.** Only `MerchandiseImage` has zero references. Five others are referenced as string literals in `EngagementQueryTicketModelPlacementArchTest:47-51` |
| Phases 2-5 own disjoint file sets, any order | **CORRECTED → false.** 4 files span two phases; the shims import each other across module boundaries. Dependency chain is now 1 → 2 → 3 → 5, with 4 parallelizable |
| **NEW BLOCKER:** all 6 placement arch tests assert their shim still exists | Deleting a shim reddens its module's own test. Each phase must delete that block — added as mandatory Architecture step 6 |
| **NEW BLOCKER:** `FacilitiesDeliveryBoundaryArchTest` Room negative assertions | Must be *retargeted* to the canonical FQCN, not deleted, or the Delivery boundary silently dies. Phase 4 step 8 |
| **NEW:** phase 1's migration is already applied on dev `asia` | 100 `ClubMember` rows migrated, 99 resolve, 1 pre-existing orphan. Phase 3 must not write a second ClubMember backfill; morph total 242 → 142 remaining |
| `config/`, `resources/`, `bootstrap/` hold shim references | VERIFIED ABSENT — the guard's `config/` scan is precautionary |
| The 2 Merchandise migrations reference shims in executable code | VERIFIED FALSE — docblock comments only, zero behavioral risk |
| `AppServiceProvider` morph-map behavior changes when its `RoomBooking` import is rewritten | VERIFIED SAFE in principle — map keys are constant *values*, not FQCNs. Phase 4 still diffs the resolved map before/after to prove it |

**Decisions taken during scaffolding:**

| # | Decision |
|---|---|
| V2-a | **Delete** the shim-must-exist block from each placement arch test rather than inverting it — phase 1's guard already asserts the exact remaining-shim set repo-wide, so a per-module absence assertion is redundant |
| V2-b | **Retarget** (do not delete) `FacilitiesDeliveryBoundaryArchTest`'s Room negative assertions, because their purpose is the Delivery boundary, not the shim |
| V2-c | **Each phase lowers `config/migration_debt.php` `shared_model_imports`** in the same PR, so the two guard mechanisms cannot drift |
| V2-d | Phase 3 owns `Club` only; **no second `ClubMember` backfill** |

### Whole-Plan Consistency Sweep (session 2)

Re-read `plan.md` and all five phase files.

- 86/90 → 92 reconciled in Evidence Base; per-module table now carries exact
  counts, unique-file totals, morph rows, and owning phase.
- "Disjoint file sets, any order" removed; replaced with the overlap table and an
  explicit dependency chain. Phases table dependency column updated to match
  (3 depends on 1,2; 5 depends on 1,3).
- "Only phase 1 is scaffolded" note removed — all five phase files now exist and
  are linked from the Phases table.
- Morph table now distinguishes plan-time rows from remaining rows; the
  ClubMember row reads 0 with the phase-1 application recorded.
- Architecture procedure grew from 5 steps to 8, adding the arch-test-block
  deletion and the debt-baseline reduction that every phase needs.
- Success Criteria and Risks rewritten to cover the three new blockers.
- Phase 1's "6 free deletions" premise is not referenced by any phase file; the
  corrected count lives only in Evidence Base and phases 3/5.

Unresolved contradictions: **none**.

## Open Questions

1. **Do the two intentional data-holder files stay forever?**
   `database/migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`
   and its test both hold `App\Models\ClubMember` as *data*, so
   `SHIMMED_MODEL_IMPORT_BASELINE` cannot reach empty while they exist. Either
   grandfather them with a comment or retire the pilot migration once every
   environment has run it. Phase 5 must decide; no correct answer from the repo alone.

2. **Is phase 3 (16 shims, 23 files) one PR or three?** It splits cleanly along
   the existing arch-test boundaries — Form / Club-Event / QueryTicket. One PR is
   simpler to sequence; three are easier to review. Reviewer preference, not a
   technical constraint.

The three original questions are resolved by V1-a/b/c above.

<!-- slug: deprecated-model-shim-namespace-sweep -->
