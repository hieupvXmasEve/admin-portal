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

Measured 2026-08-11. 30 shim files in `app/Models/`, 90 files importing them
(re-counted during phase 1 with an import-detector that also catches
double-backslash string literals, not just `use` statements — the original
86 undercounted that form).

**Grouped by owning module, with caller counts:**

| Module | Shims (callers) | Files |
|---|---|---|
| Facilities | `Room` (26), `RoomBooking` (8), `Building` (5), `RoomBookingAction` (1) | ~34 |
| Upload | `ApplicationDocumentType` (14), `ApplicationDocument` (13), `UploadRecord` (9) | ~30 |
| Engagement | `Form` (10), `FormVersion` (10), `FormTarget` (9), `FormResponse` (8), `Event` (8), `QueryTicket` (5), `QueryReply` (4), `FormSection` (3), `EventParticipant` (2) | ~40 |
| Merchandise | `GoldTransaction` (10), `Merchandise` (9), `MerchandiseVariant` (8), `RedemptionOrder` (6), `StockMovement` (5), `RedemptionOrderItem` (2) | ~26 |

**Zero *code* -caller shims:** `QueryTopic`, `QueryAssignment`,
`MerchandiseImage`, `FormSurvey`, `FormResultVisibility`,
`ClubMemberRoleHistory`, `ClubMember`, `Club`.

> **These are NOT all safe to delete.** `ClubMember` (100 rows) and `Club`
> (19 rows) have zero code imports but **do** have persisted morph rows (see
> below). "No import" does not mean "no reference". Only the remaining **6** are
> free deletions.

Counts overlap (one file may import several shims), so per-module file totals are
approximate and the 86 figure is the deduplicated repo-wide total.

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

| `activity_log.subject_type` | Rows |
|---|---|
| `App\Models\ClubMember` | 100 |
| `App\Models\Room` | 87 |
| `App\Models\RoomBooking` | 31 |
| `App\Models\Club` | 19 |
| `App\Models\Building` | 5 |
| **Total** | **242** |

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

1. `grep -rl 'App\\Models\\<Model>' app tests database routes`
2. Rewrite the `use` statement to the canonical FQCN.
3. Rewrite any inline FQCN references and string references
   (`'App\Models\X'` appears in config, morph maps, and factory resolution —
   **check `Relation::morphMap`, `config/*.php`, and `database/factories/`**,
   not just `use` lines).
4. Delete the shim file.
5. Run the module's existing placement arch test plus the full suite.

The repo already has per-module placement arch tests
(`FacilitiesModelPlacementArchTest`, `EngagementFormModelPlacementArchTest`,
`EngagementClubEventModelPlacementArchTest`,
`EngagementQueryTicketModelPlacementArchTest`, …). Extend the pattern rather than
inventing a new mechanism.

**Highest-risk step is 3, and it is confirmed live, not hypothetical.** A
`class_alias` keeps *string* class references working, and `activity_log` holds
242 such rows today across 5 models (see Evidence Base).

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

| # | Phase | Status | Depends on |
|---|-------|--------|-----------|
| 1 | [Morph-migration pilot and guard test](./phase-01-start.md) | Done | — |
| 2 | Upload module sweep (no morph rows — cleanest first) | Pending | 1 |
| 3 | Engagement module sweep (`ClubMember` + `Club` morph rows) | Pending | 1 |
| 4 | Facilities module sweep (`Room`, `RoomBooking`, `Building` morph rows) | Pending | 1 |
| 5 | Merchandise module sweep + delete remaining shims | Pending | 1 |

> **Only phase 1 is scaffolded as a file.** Phases 2-5 follow one repeated
> procedure (sweep callers → migrate that module's morph rows → delete its
> shims → run the module's arch test), so they are scaffolded with
> `ak plan add-phase` when the sweep is actually scheduled — writing five
> near-identical files for a P3 backlog item is waste. (`ak plan status`
> therefore reports 1 phase, not 5 — expected.)

Phase 1 lands the migration pilot and the guard test; nothing else starts first.
The strategy question it originally carried is already settled (V1-a), and the
inventory it was to produce is complete — see the Validation Log.

Phase 2 is deliberately next: Upload's three models have **zero** persisted morph
rows, so it proves the mechanical sweep end to end without the stateful risk.
Phases 3-5 then apply the proven procedure to the modules that do carry morph
rows. Phases 2-5 own disjoint file sets and can otherwise run in any order.

## Success Criteria

- [ ] No `class_alias` shim remains in `app/Models/`
- [ ] No code imports `App\Models\<migrated model>` in `app`, `tests`, `database`, `routes`
- [ ] No `activity_log.subject_type` (or any other morph column) still holds a
      deleted shim's FQCN — verified by re-running the Evidence Base query and
      getting zero rows
- [ ] `$activity->subject` resolves for previously-affected rows (spot-check
      Room, RoomBooking, ClubMember)
- [ ] Arch test fails if a `class_alias` shim is reintroduced under `app/Models/`
- [ ] Full test suite green, with the same pass/fail baseline as before the sweep
- [ ] No diff outside import lines, shim deletions, and the new arch test

## Risks

| Risk | Mitigation |
|---|---|
| **Confirmed:** `activity_log.subject_type` holds 242 rows of shimmed FQCNs across 5 models; deleting those shims breaks `$activity->subject` | Phase 1 picks a strategy (data migration / morphMap alias / both) and every later phase applies it before deleting a shim |
| A *different* string class reference (config, queued job payload, factory resolver) breaks silently | Grep for quoted `App\Models\X` before deleting any shim; deletion is the last step of each phase, after callers are clean |
| Morph rows keep accruing while the sweep is in progress | Re-run the measurement immediately before each deletion, not once at plan time |
| In-flight queued jobs serialized with the old FQCN fail after deploy | Drain or check the queue before deploying a phase; the shims can also be deleted one deploy *after* the caller sweep rather than in the same one |
| Large mechanical diff hides a real edit | One module per PR; reviewer checks that the diff contains only import lines and deletions |
| Baseline test failures get attributed to this sweep | Record the pre-sweep pass/fail baseline first — the repo has known pre-existing failures in Finance and Academic suites |

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

## Open Questions

None. The three original questions are resolved by V1-a/b/c above.

<!-- slug: deprecated-model-shim-namespace-sweep -->
