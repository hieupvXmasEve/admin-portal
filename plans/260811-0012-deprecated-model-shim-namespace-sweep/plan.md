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

Measured 2026-08-11, **re-measured during phase 2-5 scaffolding, corrected again
in phase 3**. 30 shim files in `app/Models/`, **93** files referencing them —
`app/Models/Answer.php` was hidden by the guard's old directory-wide skip (see
phase 3, D1) and is now the 93rd entry in `SHIMMED_MODEL_IMPORT_BASELINE`.

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

### Only 20 of 30 shims can be deleted — the shims are load-bearing

<!-- Added: red-team session 1, Critical C1 -->

`cross_context_concrete_imports` (`config/migration_debt.php:75-81`) is
`baseline: 0, mode: exact` — zero tolerance, currently sitting exactly at 0 and
owned by "Platform architecture maintainers". Its detector
(`MigrationDebtInventory.php:349-388`) fires when a file namespaced
`App\Modules\A\` names `App\Modules\B\` on a non-comment line;
`MigrationDebtGuard.php:109` errors on any non-zero.

`App\Models\X` is **namespace-neutral**, so a cross-module model read routed
through a shim is invisible to that rule. Rewriting it to the canonical FQCN
makes it visible and breaks the guard.

**This is the real reason `260809-1557` left the shims behind.** 12 files depend
on that property, keeping 10 shims alive:

```
Academic   -> Facilities    GetClassSessionFormOptionsQuery, LecturerTimetableService (Room)
Academic   -> Facilities    CampusBuildingCountReader (Building)
Academic   -> Engagement    GetCourseOfferingSurveyQuery (FormTarget)
Admissions -> Upload        UpsertCrmApplicationAction, IngestionController,
                            GetApplicantDocumentChecklistQuery, ListApplicationsQuery
Engagement -> Upload        FormResponse, QueryReply (UploadRecord)
Engagement -> Merchandise   EventParticipationOperations (GoldTransaction)
Upload     -> Engagement    UploadRecord (FormResponse, QueryReply, QueryTicket)
```

| Module | Deletable | Blocked |
|---|---|---|
| Upload | **0 / 3** | `ApplicationDocument`, `ApplicationDocumentType`, `UploadRecord` |
| Facilities | 2 / 4 | `Room`, `Building` |
| Engagement | 12 / 16 | `FormResponse`, `FormTarget`, `QueryReply`, `QueryTicket` |
| Merchandise | 6 / 7 | `GoldTransaction` |
| **Total** | **20 / 30** | 10 |

Consequences that reshaped this plan:

- **Upload deletes nothing**, so the original "sweep Upload first, it is the
  cleanest" phase was removed.
- `Room` — the highest-caller shim (27) and highest morph-row holder (87) — is
  blocked.
- The natural phase boundary is deletable-vs-blocked, not per-module.
- Removing the 10 survivors is a **module-boundary refactor** (route 12 reads
  through `App\Shared\Contracts\*`), not a namespace sweep. Different owner,
  different risk, different sign-off. Spun out to a follow-up plan in phase 5
  rather than smuggled in as an import swap.

**Decision (V3-c): sweep the unblocked 20; leave the 10 and their 12 callers
untouched.** Files holding both a deletable and a blocked shim get a *partial*
sweep and end up with mixed namespaces — intended, not half-finished work.

### Nothing can write a shimmed FQCN anymore — so the migration goes first

<!-- Added: red-team session 1; supersedes the same-PR ordering constraint -->

An earlier revision claimed rows "keep accruing until the callers stop writing
the old FQCN, since the logger records `get_class($model)`", and derived a
same-PR ordering from it. **The causal claim is false.** Traced:
`ActivityLogger.php:50` → `MorphTo.php:248` `getMorphClass()` →
`HasRelationships.php:1006-1020` → returns `static::class`. `class_alias` does not
create a second class, so `static::class` is always the canonical name no matter
which alias the caller imported. `BusinessActionLogger.php:233` agrees.

Confirmed by data — last old-FQCN write per model, all predating the 2026-08-09
model move (`50ed50a4`): `Room` 2026-05-22, `RoomBooking` 2026-07-22, `Club`
2026-04-17, `Building` 2026-01-22, `ClubMember` never.

**The 142 rows are frozen residue, not an accruing set.** The same-PR constraint
was unnecessary — and it was actively dangerous, because
`scripts/deploy-ubuntu.sh` runs `migrate` (:120) long after the new code is live
(`optimize:clear` :92, `git reset --hard` in `deploy.yml:70-77`), with no
`artisan down`. Deleted shim files would have gone live minutes before the
backfill, and a request touching one of the 142 rows would hit the stale
optimized classmap (`autoload_classmap.php:613`) for a deleted file →
`require(): Failed opening required`, an uncatchable E_COMPILE_ERROR.

**Decision (V3-d): the backfill runs first, as its own deploy, with every shim in
place** (phase 2). Deletion follows only after it is verified in production.

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

<!-- Updated: red-team session 1 - goals 1/2 reduced to 20 of 30 shims; goal 4 qualified with a known behavior change -->

| # | Goal | Priority |
|---|------|----------|
| 1 | No code references the **20 unblocked** shims' FQCNs | P1 |
| 2 | **20 of 30** shim files deleted. The other 10 are load-bearing for a zero-tolerance boundary rule and are spun out to a follow-up plan — see "Only 20 of 30 shims can be deleted" | P1 |
| 3 | An arch test fails if a shim or a shim import reappears — including a subclass-style reintroduction, and including after a model leaves the allow-list | P1 |
| 4 | No logic or schema change. Data edits limited to rewriting persisted morph strings. **One known behavior change is accepted and must be declared:** saved/bookmarked Activity Logs `?subject_type=App\Models\X` links return zero rows after the backfill | P1 |
| 5 | Previously-logged activity still resolves its subject after the sweep | P1 |

## Non-Goals

- No model refactoring, renaming, or logic change of any kind.
- No migration of models still legitimately living in `app/Models/` that were
  never part of `260809-1557`.
- No changes to the module boundaries themselves.

## Architecture

<!-- Updated: red-team session 1 - resequenced; data first, guard second, sweep third -->

Three separable concerns, not one repeated per-module procedure. Sequencing is
load-bearing, so the phases are ordered by dependency rather than by module:

**Stage 1 — data (phase 2).** One migration rewrites the remaining 142 morph
rows, deployed alone with every shim in place. Safe in any order because nothing
can write a shimmed FQCN anymore, and running it first removes the deploy-window
fatal that the old same-PR ordering created.

**Stage 2 — guard (phase 3).** Fix the four evasions in phase 1's
`DeprecatedModelShimArchTest` *before* anything relies on it to prove a deletion
is safe. As shipped it would have allowed deleting `app/Models/UploadRecord.php`
while `app/Models/Answer.php` still imported it.

**Stage 3 — code (phase 4).** Per PR, for the 20 unblocked shims:

1. Record the pre-sweep baseline for the suites the PR touches.
2. Re-measure callers with the real detector — **without** `grep -v app/Models/`,
   which is what hid `Answer.php`:
   `grep -rlP '\bApp\\{1,2}Models\\{1,2}<Model>\b' app tests database routes config --include='*.php'`
3. Rewrite `use` statements **and** inline/string FQCN references (docblocks and
   arch-test negative assertions both count). On files that also hold a blocked
   shim, rewrite only the deletable names.
4. Delete the shim file.
5. **Invert** the module's placement arch test per V3-a — `file_exists` →
   `toBeFalse()`, keeping `not->toContain("class X extends")`. Do not delete the
   block; that assertion is the duplicate-model guard and phase 1's test does not
   replace it.
6. Shrink `SHIMMED_MODELS` only. `ALL_MIGRATED_MODELS` stays at 30 so the importer
   regex keeps watching a model after its shim is gone.
7. Prove the deletion behaviorally: `class_exists('App\Models\<Name>')` is false
   in-process. Grep alone cannot see a runtime-assembled FQCN.
8. Run the PR's suites and compare against the step-1 baseline.

**Never** edit `config/migration_debt.php` or `MigrationDebtContract` (V3-b).

**Stage 4 — close (phase 5).** Annotate the 10 survivors with what blocks them,
and spin the boundary refactor out to its own plan.

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

### Morph strategy

**Decision (V1-a, retained): strategy A — data migration.**

```sql
UPDATE activity_log
   SET subject_type = 'App\Modules\<Owner>\Models\<X>'
 WHERE subject_type = 'App\Models\<X>';
```

Rejected **B** (permanent `morphMap` alias): leaves the deprecated names in config
forever — the debt moves rather than clears.

<!-- Updated: red-team session 1 - V1-b superseded; the jobs-table evidence was invalid -->

**V1-b (same-PR deletion) is superseded by V3-d.** Two independent reasons:

1. The ordering it rested on is derived from a false causal claim — see "Nothing
   can write a shimmed FQCN anymore" above.
2. Its safety evidence was invalid. The "no in-flight queued payload" conclusion
   came from inspecting the MySQL `jobs` / `failed_jobs` **tables**, but
   `QUEUE_CONNECTION=redis` (`.env:65`) — `jobs` is empty because it is unused,
   not because the queue is drained. The per-phase "re-check `jobs`" ritual would
   have reported clean forever, in every environment. `CACHE_STORE` and
   `SESSION_DRIVER` are also redis and were never inspected. (The conclusion
   happens to survive, because `SerializesModels` stores a `ModelIdentifier`
   naming the *declaring* class — but that was never established, so it was luck,
   not analysis.)

**Decision (V3-d): backfill first, as its own deploy, shims untouched. Delete only
after it is verified applied in production.** Where a queue check is still wanted,
check the real backend (`LLEN queues:*` plus the `:delayed` / `:reserved` ZSETs),
not the dead `jobs` table.

### Rollback

`down()` must **not** mirror `up()`. Phase 1's implementation is
`WHERE subject_type = NEW_FQCN`, which rewrites every canonically-named row —
including rows the application wrote canonically on its own — not just the ones
`up()` touched. Its `class_exists` guard made things worse in a different way:
`migrate:rollback` reports success while reverting nothing, leaving an operator
believing state was restored.

**Decision (V3-e): phase 2's `down()` throws.** The recovery path is a code
revert, not a data revert — old code resolves canonical FQCNs correctly while the
shims exist.

### Verification that actually verifies

Three prescribed checks were structurally incapable of failing and are replaced:

- **Never `LIKE` on an FQCN.** MySQL treats backslash as the `LIKE` escape
  character, so `LIKE 'App\Modules\Facilities\Models\%'` matches **nothing** and
  prints a clean-looking `resolved=0 null=0`. Proven: exact `whereIn` returns 100
  where the `LIKE` form returns 0. Use `whereIn` with exact strings, and require a
  `resolved + null == expected count` positive control.
- **Never `./scripts/dev.sh mysql -e "..."`.** `scripts/dev.sh:54-57` does not
  `shift` or forward arguments, so `-e` is silently dropped and the command exits
  0 with no output — indistinguishable from "no rows". Use
  `artisan tinker --execute=`.
- **Never "re-run the enumeration and get zero hits".** `activity_log.subject_type`
  legitimately holds ~178k `App\Models\%` rows for non-shimmed models, so zero
  hits is unsatisfiable and would be waved through. Query the 30 exact FQCNs.

The morph-map before/after diff an earlier revision prescribed is also dropped: it
cannot fail. Both names are the same class object and the map's keys are
`RoomBooking::BOOKED_BY_*` constant *values*, so the diff is clean regardless of
whether the sweep is correct.

### Enumeration scope, stated honestly

The "all 64 `%_type`/`%_class` columns" claim was **name**-filtered, so it could
not see a class string in a generically-named column. Two hold shimmed FQCNs and
are accepted as residue rather than cleaned: `telescope_entries.content` (~236 dev
rows) and `activity_log.properties` (JSON) — neither is a resolution key. Also
verified clean but never previously mentioned:
`lecturer_access_grants.token_subject_type` and
`faculty_access_eligibility_outbox.token_subject_type`.

Every number in this plan was measured on dev `asia`. **Production is unmeasured**
— phase 2 step 1 gates on measuring it.

## Phases

<!-- Updated: red-team session 1 - restructured from per-module to data/guard/code/close -->

| # | Phase | Delivers | Deletes | Status | Depends on |
|---|-------|---|---|--------|-----------|
| 1 | [Morph-migration pilot and guard test](./phase-01-start.md) | pilot migration + guard | 0 shims | Done | — |
| 2 | [Backfill remaining morph rows](./phase-02-backfill-remaining-morph-rows.md) | 1 migration, own deploy | 0 shims | Pending | 1 |
| 3 | [Harden the shim guard](./phase-03-harden-shim-guard.md) | 4 guard fixes | 0 shims | Done | 1 |
| 4 | [Sweep and delete the 20 unblocked shims](./phase-04-sweep-and-delete-unblocked-shims.md) | 3 PRs, 40 files | **20 shims** | Pending | 1, 2, 3 |
| 5 | [Close out and spin out the 10 blocked shims](./phase-05-close-out-and-spin-out-blocked-shims.md) | annotations + follow-up plan | 0 shims | Pending | 1, 2, 3, 4 |

**Strictly sequential: 1 → 2 → 3 → 4 → 5.** No parallelism.

Phases 2 and 3 are logically independent of each other, but every phase from 3
onward edits `tests/Feature/Architecture/DeprecatedModelShimArchTest.php`, whose
two `const` arrays are asserted for **exact** equality in both directions. Two
branches editing them concurrently either conflict or auto-merge into a list that
no longer matches the filesystem — failing with "New class_alias shims must not be
added" while pointing at shims nobody added. An earlier revision's overlap
analysis missed this because it only compared *shim-importing* files.

Phase 4's three PRs (Merchandise → Engagement → Facilities) serialize for the same
reason. Merchandise goes first: 6 shims, zero morph rows, and no blocked shim
among the six.

**The ordering is load-bearing, not stylistic.** Phase 2 must be deployed and
verified in production before phase 4 deletes anything, or the deploy window
described in Architecture produces an uncatchable fatal. Phase 3 must land before
phase 4 because the guard is what proves a deletion is safe, and as shipped it
could not see `app/Models/Answer.php`.

## Success Criteria

<!-- Updated: red-team session 1 - 30/30 reduced to 20/30; unverifiable criteria replaced -->

- [ ] **20 of 30** shims deleted; exactly the 10 documented survivors remain in `app/Models/`
- [ ] `class_exists('App\Models\<Name>')` is false, checked in-process, for all 20 deleted names
- [ ] No code references the 20 deleted FQCNs anywhere in `app`, `tests`, `database`, `routes`, `config` — the `app/Models/` exclusion removed
- [ ] The 10 blocked shims and their 12 cross-module callers byte-identical; each survivor annotated with what blocks it
- [ ] All 6 morph columns return **0** for the 30 exact FQCNs via `whereIn` — not `LIKE`, not `dev.sh mysql -e`, not "zero hits" from a name-filtered enumeration
- [ ] `$activity->subject` resolves for the 142 backfilled rows, with `resolved + null` equal to the pre-migration count and every null traced to a hard-deleted subject row
- [ ] Production measured before the backfill deploy, and the backfill verified applied there before any deletion
- [ ] Backfill `down()` throws rather than reversing; recovery documented as code revert
- [ ] `DeprecatedModelShimArchTest` fails on: a new `class_alias` shim, one in a subdirectory, a subclass-style reintroduction, and an importer inside `app/Models/` — each proven red once, then reverted
- [ ] `ALL_MIGRATED_MODELS` fixed at 30; no configuration of the guard is a tautology
- [ ] All 6 placement arch tests green, **inverted** not deleted, each retaining `not->toContain("class X extends")`
- [ ] `cross_context_concrete_imports` still `0`; `config/migration_debt.php` and `MigrationDebtContract` untouched by this plan
- [ ] The 3 campus-scoped Merchandise tests pass individually, not just within the aggregate
- [ ] Touched suites match their recorded pre-phase baselines
- [ ] The accepted Activity Logs bookmark/filter breakage declared in the phase-2 PR
- [ ] Follow-up boundary-refactor plan created for the 10 survivors
- [ ] No diff outside import rewrites, the 20 deletions, one backfill migration, and arch-test bookkeeping

## Risks

<!-- Updated: red-team session 1 - rewritten; 4 rows were mitigating risks with the mechanisms that caused them -->

| Risk | Mitigation |
|---|---|
| **Confirmed:** sweeping a cross-module read breaks the zero-tolerance `cross_context_concrete_imports` guard | 10 shims and their 12 callers are out of scope (V3-c); phase 4 re-runs `migration-debt:inventory` and requires `0` |
| **Confirmed:** the deploy script puts deleted shims live minutes before `migrate` runs, producing an uncatchable E_COMPILE_ERROR via the stale optimized classmap | Backfill ships as its own deploy with all shims present (V3-d), verified in production before phase 4 deletes anything |
| **Confirmed:** a caller inside `app/Models/` is invisible to the guard and to every phase grep (`app/Models/Answer.php` → `UploadRecord`) | Phase 3 replaces the directory-wide skip with a shim-file skip; baseline corrected to 93; phase 4 scans without the exclusion and checks `class_exists` in-process |
| **Confirmed:** `migrate:rollback` reports success while reverting nothing, and phase 1's `down()` over-writes natively-canonical rows | Phase 2's `down()` throws (V3-e); recovery is code revert, documented |
| **Confirmed:** deleting a shim reddens its module's placement arch test | Phase 4 **inverts** rather than deletes the block (V3-a), preserving the duplicate-model assertion the phase-1 guard does not replace |
| **Confirmed:** the guard is evadable via subdirectory, subclass form, or a shrinking model list | Phase 3 fixes all three: recursive scan, behavioral `class_exists` assertion, and a fixed 30-name `ALL_MIGRATED_MODELS` for the importer regex |
| **Confirmed:** three prescribed verification commands cannot fail or cannot run (`LIKE` on an FQCN, `dev.sh mysql -e`, "zero hits", morph-map diff) | All four replaced — see "Verification that actually verifies". Positive controls required, not absence of output |
| **Confirmed:** every number came from dev `asia`; production never measured | Phase 2 step 1 gates the deploy on production counts and volume |
| **Confirmed user-visible change:** bookmarked Activity Logs `?subject_type=App\Models\X` links return zero rows after the backfill | Declared in the phase-2 PR as an accepted behavior change rather than asserting Goal 4 verbatim. `ActivityLogController.php:79-81` passes the value into an exact `where` with no allow-list |
| A runtime-assembled FQCN survives every grep | Phase 4 step 4 sweeps for the known idiom (`app/Modules/Academic/routes/web.php:66` uses it for a non-shimmed model), and step 7's `class_exists` check catches a resolvable name however it was written |
| Concurrent phases collide on the guard's two `const` arrays | Strictly sequential 1 → 2 → 3 → 4 → 5, and phase 4's three PRs serialize too. No parallelism anywhere |
| Mixed-namespace files read as half-finished and get "tidied" into a boundary violation | Documented as intended; the 12 blocked files are named and must stay byte-identical; the guard's importer baseline records which references are deliberate |
| `shared_model_imports` is red (401 vs 289) and gets "fixed" by this plan | Out of scope and must not be edited (V3-b). The guard requires config baseline == the hardcoded ceiling, so editing config only adds a second error |
| Campus-scope or policy regression masked by known failures | Phase 4 runs the 3 campus-scoped Merchandise tests individually. Red-team **falsified** the policy-bypass and campus-leak hypotheses: all shimmed-model authorization is instance-based and `log_name` derives from `class_basename()`, unchanged by the move |
| Baseline test failures attributed to this plan | Record the pre-phase baseline first — known pre-existing failures in Finance and Academic, plus 5 in `tests/Feature/Architecture` (including `MigrationDebtInventoryTest` ×2, which is the 401/289 gate) |

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

**Decisions taken during scaffolding** — three of the four were overturned by
red-team session 1. Historical record; **do not act on a superseded row**:

| # | Decision | Status |
|---|---|---|
| V2-a | **Delete** the shim-must-exist block from each placement arch test rather than inverting it — phase 1's guard already asserts the exact remaining-shim set repo-wide, so a per-module absence assertion is redundant | ~~SUPERSEDED by V3-a~~ — the block also holds `not->toContain("class X extends")`, the duplicate-model guard, which the phase-1 test does **not** replace. Invert, do not delete |
| V2-b | **Retarget** (do not delete) `FacilitiesDeliveryBoundaryArchTest`'s Room negative assertions, because their purpose is the Delivery boundary, not the shim | ~~DEFERRED by V3-f~~ — reasoning still correct, but `Room` turned out to be blocked, so the assertions stay as-is until the follow-up plan sweeps it |
| V2-c | **Each phase lowers `config/migration_debt.php` `shared_model_imports`** in the same PR, so the two guard mechanisms cannot drift | ~~SUPERSEDED by V3-b~~ — impossible (guard requires baseline == hardcoded ceiling) and wrong-direction (metric already red at 401 vs 289, mode `max`). Touch neither file |
| V2-d | Phase 3 owns `Club` only; **no second `ClubMember` backfill** | Retained — folded into phase 2, which now backfills all 142 remaining rows in one migration |

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

## Red Team Review

### Session 1 — 2026-08-11

**Findings:** 26 raised across 3 hostile reviewers, deduplicated to 16 distinct
(24 accepted, 2 falsified). Every finding carried `file:line` evidence; none was
rejected by the evidence filter. All 4 Criticals were independently re-verified by
the orchestrator before acceptance.

**Severity breakdown:** 4 Critical, 8 High, 4 Medium. Two adversarial hypotheses
were **falsified** and are recorded so they are not re-litigated.

Reviewers: Assumption Destroyer (Scope Auditor role) — BLOCKED; Failure Mode
Analyst (Flow Tracer) — BLOCKED; Security Adversary (Fact Checker) —
DONE_WITH_CONCERNS.

| # | Finding | Severity | Disposition | Applied to |
|---|---------|----------|-------------|-----------|
| 1 | Sweeping cross-module reads breaks `cross_context_concrete_imports` (baseline 0, mode exact). 12 files, 10 shims blocked. The shims are load-bearing for that boundary | Critical | Accept | plan Evidence Base, phases 4 + 5 |
| 2 | "Lower the `shared_model_imports` baseline" is impossible (guard requires baseline == hardcoded ceiling) and the metric is already red at 401 vs 289. Found independently by 2 reviewers | Critical | Accept — V2-c withdrawn → V3-b | plan Architecture, phase 4 |
| 3 | `app/Models/Answer.php` imports `App\Models\UploadRecord`; invisible to the guard and every phase grep. Real count 93, not 92 | Critical | Accept | phase 3 (D1) |
| 4 | Deploy script puts deleted shims live before `migrate`; stale optimized classmap → uncatchable E_COMPILE_ERROR | Critical | Accept — V1-b withdrawn → V3-d | plan Architecture, phase 2 |
| 5 | `getMorphClass()` returns `static::class`, so no caller can write a shimmed FQCN since 2026-08-09. Rows are frozen, not accruing — the same-PR ordering rested on a false premise | High | Accept (this is what makes #4's fix possible) | plan Architecture, phase 2 |
| 6 | V2-a deletes the duplicate-model assertion (`not->toContain("class X extends")`) that the phase-1 guard does not replace | High | Accept — V2-a withdrawn → V3-a (invert, don't delete) | plan Architecture, phases 3 + 4 |
| 7 | Phase 1's `down()` over-writes natively-canonical rows; its `class_exists` guard makes `migrate:rollback` a success-reporting no-op | High | Accept → V3-e (`down()` throws) | plan Architecture, phase 2 |
| 8 | Guard evadable three ways: non-recursive `glob`, `class_alias`-text-only (subclass form passes), and importer regex derived from a shrinking list | High | Accept | phase 3 (D2, D3) |
| 9 | Phase 5's `SHIMMED_MODELS === []` early return makes the importer guard a permanent tautology, leaving Goal 3 unmet | High | Accept | phase 3 (D4), phase 5 |
| 10 | "No in-flight queue payload" verified against MySQL `jobs` while `QUEUE_CONNECTION=redis` — the check reports clean forever. Cache and session are also redis, never inspected | High | Accept | plan Architecture (V1-b rationale corrected) |
| 11 | Every number measured on dev `asia`; production never measured, yet the UPDATEs run there. `activity_log` is 184k rows on dev alone | High | Accept | phase 2 step 1 |
| 12 | Phase 3 shipped a red test: deleting the `ClubMember` shim breaks `ClubMemberShimMorphBackfillMigrationTest`'s `down()` case, and the file was not in the modify list | High | Accept | phase 4 step 5 |
| 13 | "Disjoint file sets, phase 4 parallelizable" false — every phase edits the guard's two exact-equality `const` arrays | High | Accept | plan Phases (strictly sequential) |
| 14 | Phase 4's `LIKE 'App\Modules\...\%'` matches **zero** rows (MySQL backslash escaping) and prints `resolved=0 null=0`, reading as success. Verified: exact `whereIn` = 100, `LIKE` = 0 | High | Accept | plan Architecture, phase 2 step 5 |
| 15 | `./scripts/dev.sh artisan mysql -e "..."` cannot run — no such artisan command, and `dev.sh:54-57` drops arguments, exiting 0 with no output | Medium | Accept | phase 5 step 3; repo bug spun out separately |
| 16 | Morph-map before/after diff cannot fail — both names are the same class object and the map's keys are constant values | Medium | Accept (step deleted) | plan Architecture |
| 17 | "Re-run the enumeration and get zero hits" is unsatisfiable: `activity_log.subject_type` legitimately holds ~178k `App\Models\%` rows for non-shimmed models | Medium | Accept | plan Success Criteria, phase 5 step 3 |
| 18 | Enumeration was column-**name** filtered, so class strings in generic columns were never seen. `telescope_entries.content` holds 236 such rows; `activity_log.properties` unchecked. Candidate columns are 81, not 64 | Medium | Accept — recorded as accepted residue | plan Architecture, phases 2 + 5 |
| 19 | Bookmarked Activity Logs `?subject_type=App\Models\X` returns zero rows post-backfill; `ActivityLogController.php:79-81` has no allow-list. Violates Goal 4 as written | Medium | Accept — declared, not hidden | plan Goal 4 + Risks, phase 2 step 7 |
| 20 | Runtime-assembled FQCNs defeat the grep-based strategy; the idiom exists in-repo at `app/Modules/Academic/routes/web.php:66` | Medium | Accept | phase 4 steps 4 + 7 |
| — | Authorization bypass via policy resolution in a half-swept state | — | **Falsified** | All shimmed-model authorization is instance-based (`get_class()` → canonical); no class-string `authorize`/`can` for any shimmed model. `Gate::getPolicyFor` hits the registered key |
| — | Campus-scope / cross-tenant leak from rewriting morph types | — | **Falsified** | `AuditableModel.php:108-115` builds `log_name` from `class_basename($this)`, unchanged by the namespace move; `ActivityLogController` scopes on `log_name LIKE '%_campus_N'` |

**Also verified clean** (do not re-investigate): no duplicate class basename in a
third namespace for any shimmed model, so the morph rewrite is unambiguous;
`personal_access_tokens.tokenable_type`, `lecturer_access_grants.token_subject_type`,
and `faculty_access_eligibility_outbox.token_subject_type` hold no shimmed model;
no broadcast channel, `Route::model`, `Route::bind`, `authorizeResource`, or `can:`
middleware keyed to a shimmed FQCN; `config/`, `resources/`, `bootstrap/` clean;
the 2 Merchandise migrations reference shims in docblocks only.

**Decision deltas:**

| # | Decision | Supersedes |
|---|---|---|
| V3-a | Placement arch tests: **invert** `file_exists` → `toBeFalse()`, keep `not->toContain("class X extends")`. Do not delete the block | V2-a |
| V3-b | Never edit `config/migration_debt.php` or `MigrationDebtContract`. Record the delta only | V2-c |
| V3-c | Sweep the unblocked 20; leave the 10 blocked shims and their 12 callers untouched. Spin the boundary refactor out | — |
| V3-d | Backfill first, as its own deploy, shims in place. Delete only after production verification | V1-b |
| V3-e | Backfill `down()` throws. Recovery is code revert, not data revert | — |
| V3-f | V2-b (retarget `FacilitiesDeliveryBoundaryArchTest`) is **deferred**, not applied — `Room` is blocked, so those assertions stay as-is until the follow-up plan sweeps it | V2-b |

### Whole-Plan Consistency Sweep (red-team session 1)

Re-read `plan.md` and all five phase files after applying the accepted findings.

- Phases restructured from per-module to data → guard → code → close. Files
  renamed to match; the old "Upload module sweep" phase is gone because Upload
  deletes zero shims.
- Goals 1 and 2 reduced from 30/30 to 20/30. No surviving 30/30 claim; Success
  Criteria and phase 5 both state 20/30.
- Goal 4 qualified with the accepted Activity Logs behavior change rather than
  left as an absolute.
- Architecture procedure replaced: the same-PR ordering, the debt-baseline step,
  the arch-test-block deletion, and the morph-map diff are all gone. Steps that
  cannot fail or cannot run were removed, not reworded.
- Evidence Base: added the load-bearing-shim analysis and the frozen-rows finding.
  The 92 count is superseded by 93 in phase 3 (pending that phase's re-measure);
  Evidence Base still states 92 as the pre-fix measurement and points at phase 3
  for the correction.
- Dependency chain is now strictly linear; the earlier parallelism allowance and
  the 4-file overlap table's "any order" conclusion are removed.
- V1-b, V2-a, V2-b, V2-c are marked superseded in place, each pointing at its
  replacement. No phase file still instructs the withdrawn behavior.
- Phase 1 is `done` and its execution log is historical; its "6 free deletions"
  and "92 entries" statements are left as written with the corrections carried in
  Evidence Base and phase 3, since editing a completed phase's log would rewrite
  history.

Unresolved contradictions: **none**.

## Open Questions

1. **Do the 10 blocked shims get a boundary refactor, or a raised ceiling?**
   Routing the 12 cross-module reads through `App\Shared\Contracts\*` is correct
   and larger; adding an allow-list and raising
   `cross_context_concrete_imports` is cheap but needs sign-off from the rule's
   stated owner ("Platform architecture maintainers"). Phase 5 records the
   question in the follow-up plan; it is not this plan's call.

2. **Who owns `shared_model_imports` at 401 vs a 289 ceiling?** It is red today,
   two of the five known `tests/Feature/Architecture` failures are this gate, and
   no phase here may touch it. Is that accepted debt or unowned drift?

3. **Is the Activity Logs filter breakage acceptable, or does
   `ActivityLogController` need a compatibility mapping** from old FQCN to new for
   bookmarked links? Product call. Phase 2 currently declares it as accepted.

Questions 1-3 from validation session 1 are resolved by V1-a/b/c; V1-b is since
superseded by V3-d. The two scaffolding-session questions are resolved: the
data-holder-baseline question was answered by finding #8 (the regex is built from
a fixed list, so the two files stay visible), and phase 3's PR-splitting question
is moot now that Engagement is one PR inside phase 4.

<!-- slug: deprecated-model-shim-namespace-sweep -->
