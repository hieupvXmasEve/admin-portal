---
title: "Legacy Model Module Migration"
description: "Move legacy app/Models/*.php into owning app/Modules/<Owner>/Models — mechanical, no behavior change"
status: pending
priority: P1
effort: "5 PRs, ~1-2h each"
tags: [modularization, arch-test, php]
created: 2026-08-09
---

# Legacy Model Module Migration

## Overview

`app/Models/` holds 110 legacy models outside the module boundary (`app/Modules/<Owner>/...`)
required by [development-rules.md](../../.claude/rules/development-rules.md). Move them into
their owning module, one PR per domain group (3-8 models), **no logic change**. Each move:

1. `app/Models/X.php` → `app/Modules/<Owner>/Models/X.php` (namespace updated, `declare(strict_types=1)` kept)
2. `app/Models/X.php` becomes a `class_alias()` deprecated shim pointing at the new class
3. Explicit `protected $table = '...';` added if not already present (read current table name from the model/migration before writing it — do not guess)
4. Placement arch test added (Pest, pattern in `tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php`)

Order: **least-coupled domain first** (per-model reference count from repo-wide grep) — Facilities → Upload →
Merchandise → Engagement → Academic (Academic scoped separately, phase 5 is planning-only: 48 models,
Student alone has 1,340 references, needs its own follow-up plan).

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Every migrated model resolves under its owning module namespace | P1 |
| 2 | Zero behavior change — old FQCN still works via shim, callers untouched in this plan | P1 |
| 3 | Placement arch test locks the boundary so it can't silently regress | P1 |
| 4 | Academic (heaviest, 48 models) gets a scoped follow-up plan, not attempted here | P2 |

## Non-Goals

- No **cross-module** caller updates (`use App\Models\X` stays valid via shim for callers outside
  the owning module; a separate follow-up plan can sweep those and drop the shim). **In-module**
  callers (the owning module's own Queries/Actions/Support/Http) DO get repointed to the new
  namespace in the same phase — see Validation Log decision below.
- No schema changes, no relationship/query logic changes, no renaming beyond namespace.
- Academic domain migration itself — phase 5 only produces the scoping plan.
- Finance/Identity-adjacent legacy models not covered by the 4 named domains
  (`TuitionPlan`, `BillingCycle`, `VoucherDefinition`, `VoucherApplication`, `ScholarshipDefinition`,
  `GraduationRequirement`, `IeltsCertificate`, `GraduationApplication`, `ProgramChangeRequest`, `DeferCase*`,
  `EgcBlock`, `EgcRetakeDiscountLink`, `CanvasCourseMapping`, `CanvasIntegration`, `EmailConfiguration`,
  `EmailLog`, `User`, `Role`, `Permission`, `RolePermission`, `CampusUserRole`, `UserEmailPreference`,
  `AuditableModel`, `StudentAuditableModel`, `UserAuditableModel` — abstract/base classes, and the
  remaining ~48 core-academic models) — out of scope, flagged as open question below.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Facilities module migration](./phase-01-start.md) | Pending |
| 2 | [Phase 2: Upload module migration](./phase-02-upload-module-migration.md) | Pending |
| 3 | [Phase 3: Merchandise module migration](./phase-03-merchandise-module-migration.md) | Pending |
| 4 | [Phase 4: Engagement module migration](./phase-04-engagement-module-migration.md) | Pending |
| 5 | [Phase 5: Academic module scoping (deferred)](./phase-05-academic-module-scoping-deferred.md) | Pending |

## Success Criteria

- [ ] Phases 1-4 each ship as an independent PR; narrow test (`./scripts/dev.sh artisan test --filter=<Domain>`)
      green before broad (`./scripts/dev.sh artisan test`)
- [ ] `app/Models/` contains only shim files for the 15 migrated models + the untouched out-of-scope ones
- [ ] Placement arch tests exist for Facilities, Upload, Merchandise, Engagement and pass in CI
- [ ] No caller-facing behavior change (all existing tests pass unmodified)
- [ ] Phase 5 produces a written scoping note, not a code change

## Open Questions

1. Out-of-scope legacy models (list above) — confirm they stay in `app/Models/` untouched for now,
   or should any (e.g. `ScholarshipDefinition`, `VoucherDefinition`) be routed to Finance instead of Academic
   in a later plan? Needs owner decision, not blocking phases 1-4.
2. Should the shim (`class_alias`) be dropped once **cross-module** callers are swept in a later plan,
   or kept permanently as a compat layer? Deferred — cross-module sweep is out of this plan's scope.

## Validation Log

### Verification Results (2026-08-09)
- Tier: Full (5 phases)
- Claims checked: composer.json PSR-4 root, existence of all 30 target model files,
  existence/contents of `app/Modules/{Facilities,Upload,Merchandise,Engagement}`, arch-test example file.
- Verified: all 30 model files exist under `app/Models/`; `"App\\": "app/"` PSR-4 root confirmed
  (covers both `app/Models` and `app/Modules` under one namespace, no extra composer entry needed);
  `ScholarshipAdjustmentModulePlacementArchTest.php` exists and matches the described arch-test pattern.
- **Failed (plan corrected)**: Phases 1-4 originally claimed target modules "do not exist yet." Verified
  false — `Facilities`, `Upload`, `Merchandise`, `Engagement` all already exist as full feature modules
  (Queries/Actions/Support/Http/routes/ServiceProvider); only `Models/` subdir is missing in each. All
  4 phase files corrected to reflect this. Sample check: 17+ files in `app/Modules/Facilities/*` already
  reference `App\Models\Room` directly.
- Unverified: exact reference counts per model in Upload/Merchandise/Engagement modules (spot-checked
  Facilities only) — each phase's implementation step 5 re-greps at execution time, not a plan-blocking gap.

### Interview Decisions (2026-08-09)
1. **In-module callers get repointed to the new namespace in the same phase** (not left on shim).
   Rationale: a module's own code should not depend on a deprecated shim for its own model — the
   17+ `App\Models\Room` references inside `app/Modules/Facilities/*` get updated to
   `App\Modules\Facilities\Models\Room` as a mechanical find/replace within each phase's move step.
   Cross-module callers (outside the owning module) remain untouched per Non-Goals.
2. **Merchandise ships as one 7-model PR**, no split — within the stated 3-8 batch range.
3. **`GoldTransaction` is Merchandise-owned**, not Finance — confirmed, no longer a flagged judgment
   call in Phase 3 (redemption-currency ledger, store-owned per prior product decisions).

### Whole-Plan Consistency Sweep (2026-08-09)
- Re-read `plan.md` + all 5 phase files after propagation.
- Confirmed no phase file still claims a target module "does not exist" (all 4 corrected).
- Confirmed Phase 3's GoldTransaction wording no longer says "flag this call... judgment call" as an
  open item — decision is now final per Interview Decisions above (see phase-03 edit below).
- No stale terms, renamed APIs, or contradicting embedded drafts found across plan.md/phase files.
- Zero unresolved contradictions. Plan eligible for `/ak:cook`.

<!-- slug: legacy-model-module-migration -->
