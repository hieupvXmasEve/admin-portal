---
phase: 2
title: "Upload module migration"
status: pending
priority: P1
effort: "1h"
dependencies: [1]
---

# Phase 2: Upload module migration

## Overview

Move 3 upload/document models into `app/Modules/Upload/Models/`. UploadRecord (69
refs) is a fairly isolated feature; ApplicationDocument* rides along since it's a
thin wrapper over UploadRecord for admission documents.

## Requirements

- Functional: `App\Models\UploadRecord`, `App\Models\ApplicationDocument`,
  `App\Models\ApplicationDocumentType` keep resolving via shim.
- Non-functional: placement arch test guards the 3 classes.

## Architecture

`app/Modules/Upload` already exists with a full feature layer (Queries,
Policies, Support, Actions, Jobs, Http, `UploadServiceProvider`) — only
`Models/` is missing. Create `app/Modules/Upload/Models/` only; do not touch
existing layers (same in-module-caller repoint question applies, see `plan.md`
Open Questions). `MerchandiseImage` (also an upload-shaped model) stays out of
this phase — it moves with Merchandise in Phase 3 since it's Merchandise-owned data.

## Related Code Files

- Create: `app/Modules/Upload/Models/UploadRecord.php`
- Create: `app/Modules/Upload/Models/ApplicationDocument.php`
- Create: `app/Modules/Upload/Models/ApplicationDocumentType.php`
- Modify (→ shim): `app/Models/UploadRecord.php`
- Modify (→ shim): `app/Models/ApplicationDocument.php`
- Modify (→ shim): `app/Models/ApplicationDocumentType.php`
- Modify (namespace repoint, no logic change): all `app/Modules/Upload/**/*.php` files
  found referencing `App\Models\{UploadRecord,ApplicationDocument,ApplicationDocumentType}`
  in step 5 (full list from step 5's grep)
- Create: `tests/Feature/Architecture/UploadModelPlacementArchTest.php`

## Implementation Steps

1. Read all 3 current files fully before moving (relationships, casts, fillable).
2. `git mv` each into `app/Modules/Upload/Models/`.
3. Update namespace `App\Models` → `App\Modules\Upload\Models`.
4. Confirm/add explicit `$table` per model (check migration, don't guess).
5. Grep repo-wide for `App\Models\UploadRecord`, `App\Models\ApplicationDocument`,
   `App\Models\ApplicationDocumentType`. Split into in-module
   (`app/Modules/Upload/*`) vs cross-module; note cross-module count for PR description.
5b. Repoint in-module references to `App\Modules\Upload\Models\*` (mechanical
   find/replace). Cross-module callers stay on the shim.
6. Write `class_alias()` shim at each old path (see Phase 1 step 6 for exact pattern).
7. Write `tests/Feature/Architecture/UploadModelPlacementArchTest.php` (same pattern
   as `FacilitiesModelPlacementArchTest.php` from Phase 1).
8. `./scripts/dev.sh artisan test --filter=Upload` (narrow), then full test suite (broad).

## Success Criteria

- [ ] 3 models live under `app/Modules/Upload/Models/`, old paths shim-only
- [ ] `UploadModelPlacementArchTest.php` passes
- [ ] Full suite green
- [ ] All in-module callers repointed; zero `App\Models\{UploadRecord,ApplicationDocument,
      ApplicationDocumentType}` references remain inside `app/Modules/Upload/*`

## Risk Assessment

- `ApplicationDocument`/`ApplicationDocumentType` are admission-flow adjacent —
  double-check no Academic admission code does file-based `require`/`include` on
  the old path directly (would bypass autoload); grep for literal path strings
  containing `Models/ApplicationDocument` before merging. Same class_alias/reflection
  caveat as Phase 1.
