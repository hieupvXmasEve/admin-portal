---
phase: 1
title: "Facilities module migration"
status: pending
priority: P1
effort: "1.5h"
dependencies: []
---

# Phase 1: Facilities module migration

## Overview

Move 4 room/booking models into `app/Modules/Facilities/Models/`. Least-coupled
non-Academic domain (Room 132 refs, Building 45 — still lowest of the candidate
domains) — proves the move+shim+arch-test pattern before touching busier domains.

## Requirements

- Functional: `App\Models\Building`, `App\Models\Room`, `App\Models\RoomBooking`,
  `App\Models\RoomBookingAction` keep resolving (shim), no relationship/query logic edited.
- Non-functional: placement arch test fails CI if any of the 4 classes reappear under
  `app/Models` as a real class (not shim) or under any other module.

## Architecture

`app/Modules/Facilities` already exists with a full feature layer (Queries,
Actions, Support, Http, routes, `FacilitiesServiceProvider`) — only `Models/` is
missing. Create `app/Modules/Facilities/Models/` only; do not touch the existing
layers. **Verified:** 17+ files inside `app/Modules/Facilities/*` already
reference `App\Models\Room` (e.g. `Queries/ListAvailableRoomsQuery.php`,
`Support/RoomService.php`) — the shim keeps them working unchanged, but see
Open Question in `plan.md` on whether in-module callers should be repointed to
the new namespace as part of this phase instead of staying on the shim.

Module namespace: `App\Modules\Facilities\Models\<ClassName>` — matches the
existing convention in `app/Modules/Finance/Models/*` and `app/Modules/Identity/Models/*`.

## Related Code Files

- Create: `app/Modules/Facilities/Models/Building.php`
- Create: `app/Modules/Facilities/Models/Room.php`
- Create: `app/Modules/Facilities/Models/RoomBooking.php`
- Create: `app/Modules/Facilities/Models/RoomBookingAction.php`
- Modify (→ shim): `app/Models/Building.php`
- Modify (→ shim): `app/Models/Room.php`
- Modify (→ shim): `app/Models/RoomBooking.php`
- Modify (→ shim): `app/Models/RoomBookingAction.php`
- Modify (namespace repoint, no logic change): all `app/Modules/Facilities/**/*.php` files
  found referencing `App\Models\{Building,Room,RoomBooking,RoomBookingAction}` in step 5
  (confirmed sample: `Queries/ListAvailableRoomsQuery.php`, `Support/RoomService.php`,
  `Support/EloquentSpaceReservationService.php`, `Support/EloquentSpaceReferenceReader.php`,
  `Queries/PreviewRoomBookingSeriesAvailabilityQuery.php` — full list from step 5's grep)
- Create: `tests/Feature/Architecture/FacilitiesModelPlacementArchTest.php`

## Implementation Steps

1. For each of the 4 models: read current file fully (relationships, casts,
   fillable, any existing `$table`) before moving — content must be byte-identical
   except namespace + `$table`.
2. `git mv app/Models/<X>.php app/Modules/Facilities/Models/<X>.php` (preserves history).
3. Update namespace `App\Models` → `App\Modules\Facilities\Models` in the moved file.
4. If `protected $table` is absent, confirm the actual table name (check migration
   under `database/migrations/` or Laravel's default pluralization) and add it explicitly.
5. Grep repo-wide (excluding vendor/node_modules) for every `use App\Models\<X>` and
   `App\Models\<X>::` reference. Split results into **in-module** (inside
   `app/Modules/Facilities/*`) and **cross-module** (everywhere else). Note cross-module
   count for the PR description (untouched, covered by shim).
5b. Repoint every **in-module** reference from `App\Models\<X>` to
   `App\Modules\Facilities\Models\<X>` (mechanical find/replace: `use` statement +
   any fully-qualified `\App\Models\<X>` usage). Cross-module callers stay untouched.
6. Write shim at old path:
   ```php
   <?php

   declare(strict_types=1);

   /**
    * @deprecated Use \App\Modules\Facilities\Models\Room instead. Kept for
    * backward compatibility until callers are swept to the new namespace.
    */
   class_alias(\App\Modules\Facilities\Models\Room::class, \App\Models\Room::class);
   ```
   (repeat per model, no other content in the shim file)
7. Write `tests/Feature/Architecture/FacilitiesModelPlacementArchTest.php` following
   the `ScholarshipAdjustmentModulePlacementArchTest.php` pattern: assert each new
   path `file_exists()`, assert `App\Modules\Facilities\Models\<X>` is the real class
   (not alias) via `(new \ReflectionClass(...))->getFileName()` pointing at the module path.
8. Run `./scripts/dev.sh artisan test --filter=Facilities` (narrow), then full
   `./scripts/dev.sh artisan test` (broad — shared behavior touched: model autoloading).

## Success Criteria

- [ ] 4 models live under `app/Modules/Facilities/Models/`, old paths are shim-only
- [ ] `FacilitiesModelPlacementArchTest.php` passes and fails if a model is moved back
- [ ] Full test suite green
- [ ] All in-module callers repointed to `App\Modules\Facilities\Models\*`; zero
      `App\Models\{Building,Room,RoomBooking,RoomBookingAction}` references remain
      inside `app/Modules/Facilities/*`
- [ ] PR description lists cross-module reference counts found in step 5 (untouched, informational)

## Risk Assessment

- **Autoload edge case**: `class_alias()` needs the target class loaded before/when
  alias is registered — PSR-4 resolves `App\Modules\Facilities\Models\Room` lazily on
  first use inside the shim file, so no eager-load needed; confirmed pattern works
  under Composer PSR-4 (`"App\\": "app/"` covers both trees, per `composer.json`).
- **Serialized/cached model class names** (queue payloads, cache, session) referencing
  `App\Models\Room` — shim keeps the class resolvable, but if anything does strict
  `get_class() === 'App\Models\Room'` comparison it will now report the alias name
  differently under reflection in some PHP versions. Low risk (no such comparison
  found in scout pass) — confirm via grep for `get_class(` near these 4 model names
  before merging; rollback is `git mv` back + delete arch test if this surfaces.
