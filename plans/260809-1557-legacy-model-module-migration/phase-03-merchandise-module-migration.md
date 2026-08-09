---
phase: 3
title: "Merchandise module migration"
status: pending
priority: P1
effort: "1.5h"
dependencies: [2]
---

# Phase 3: Merchandise module migration

## Overview

Move 7 store/redemption models into `app/Modules/Merchandise/Models/`. Standalone
domain per [merchandise-store-decisions memory] — no hold, instant-gift transfer,
manual overdue. Reuses Upload's dir-creation pattern from Phase 2.

## Requirements

- Functional: all 7 FQCNs keep resolving via shim.
- Non-functional: placement arch test guards all 7 classes as a group.

## Architecture

`app/Modules/Merchandise` already exists with a full feature layer (Queries,
Actions, Policies, Exceptions, Support, Http, `MerchandiseServiceProvider`) —
only `Models/` is missing. Create `app/Modules/Merchandise/Models/` only.
In-module callers get repointed to the new namespace in this phase (decided —
see `plan.md` Validation Log). `GoldTransaction` is confirmed Merchandise-owned
(redemption-currency ledger, store-owned per prior product decisions) —
decision finalized, not an open question.

## Related Code Files

- Create: `app/Modules/Merchandise/Models/Merchandise.php`
- Create: `app/Modules/Merchandise/Models/MerchandiseImage.php`
- Create: `app/Modules/Merchandise/Models/MerchandiseVariant.php`
- Create: `app/Modules/Merchandise/Models/RedemptionOrder.php`
- Create: `app/Modules/Merchandise/Models/RedemptionOrderItem.php`
- Create: `app/Modules/Merchandise/Models/StockMovement.php`
- Create: `app/Modules/Merchandise/Models/GoldTransaction.php`
- Modify (→ shim): `app/Models/Merchandise.php`
- Modify (→ shim): `app/Models/MerchandiseImage.php`
- Modify (→ shim): `app/Models/MerchandiseVariant.php`
- Modify (→ shim): `app/Models/RedemptionOrder.php`
- Modify (→ shim): `app/Models/RedemptionOrderItem.php`
- Modify (→ shim): `app/Models/StockMovement.php`
- Modify (→ shim): `app/Models/GoldTransaction.php`
- Modify (namespace repoint, no logic change): all `app/Modules/Merchandise/**/*.php`
  files found referencing any of the 7 FQCNs in step 5 (full list from step 5's grep)
- Create: `tests/Feature/Architecture/MerchandiseModelPlacementArchTest.php`

## Implementation Steps

1. Read all 7 current files fully before moving.
2. `git mv` each into `app/Modules/Merchandise/Models/`.
3. Update namespace `App\Models` → `App\Modules\Merchandise\Models`.
4. Confirm/add explicit `$table` per model (check migration, don't guess).
5. Grep repo-wide for each of the 7 FQCNs. Split into in-module
   (`app/Modules/Merchandise/*`) vs cross-module; note cross-module count for PR description.
5b. Repoint in-module references to `App\Modules\Merchandise\Models\*` (mechanical
   find/replace). Cross-module callers stay on the shim.
6. Write `class_alias()` shim at each old path.
7. Write `tests/Feature/Architecture/MerchandiseModelPlacementArchTest.php`.
8. `./scripts/dev.sh artisan test --filter=Merchandise` (narrow), then full suite (broad).

## Success Criteria

- [ ] 7 models live under `app/Modules/Merchandise/Models/`, old paths shim-only
- [ ] `MerchandiseModelPlacementArchTest.php` passes
- [ ] Full suite green
- [ ] All in-module callers repointed; zero legacy `App\Models\{Merchandise,
      MerchandiseImage,MerchandiseVariant,RedemptionOrder,RedemptionOrderItem,
      StockMovement,GoldTransaction}` references remain inside `app/Modules/Merchandise/*`

## Risk Assessment

- 7 models is the upper edge of the 3-8 batch target — decided to ship as one PR
  (see `plan.md` Validation Log); if review pushes back at PR time, fall back to
  Merchandise-core (Merchandise, MerchandiseImage, MerchandiseVariant, StockMovement)
  + Redemption (RedemptionOrder, RedemptionOrderItem, GoldTransaction).
- Same class_alias/reflection caveat as Phase 1.
