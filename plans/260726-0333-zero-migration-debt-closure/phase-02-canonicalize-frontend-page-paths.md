---
title: "Phase 2: Canonicalize frontend page paths"
status: completed
priority: P1
effort: L
dependencies: [1]
---

# Phase 2: Canonicalize frontend page paths

## Overview

Assign all 23 legacy page directories to owner batches. Rename only pages proven
to survive; pages being replaced or deleted move inside their workflow package.
This avoids a global 138-file rename followed by immediate rework.

## Requirements

- [x] Freeze an exact source/destination/delete ledger with one owning phase per file.
- [x] Rename stable survivors and six lower-case Vue filenames through temporary paths.
- [x] Update only their render strings, imports, resolver checks, and test assertions.
- [x] Preserve named routes, props, layouts, authorization, and lazy/deferred behavior.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages` | Move legacy directories into canonical PascalCase owner paths |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/app.ts` | Update exact resolver/auth-layout paths |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Update legacy render names |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules` | Update module render names |
| `/Users/hunt2412/hieupvdev/project/swinx/tests` | Update component and partial-component assertions |

## Interface Checklist

- Every backend render string resolves to one exact `.vue` file on a case-sensitive build.
- Authentication layout behavior remains unchanged after `auth/Login` moves.
- No duplicate or case-folded destination exists.
- Portal repositories are unaffected unless an API contract changes unexpectedly.

## Dependency Map

`phase 1 file-owner ledger → stable survivor batches`; workflow-owned moves depend
only on their relevant batch, not completion of every phase-2 batch.

## Implementation Steps

1. Generate a terminal disposition manifest for all 138 files: survive/move,
   replace, or delete; assign exactly one owner package and debt delta.
2. Rename only stable survivors in owner-sized batches using intermediate paths.
3. Update backend render strings, imports, partial reload headers, tests, and `app.ts`.
4. Run file-scoped lint/format and affected Inertia tests after each batch.
5. Run the complete frontend build after each owner batch; later workflow packages
   delete their remaining directories and phase 8 audits the terminal zero.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Linux/case-sensitive production build | All page modules resolve |
| Direct and partial Inertia visit | Same component and props |
| Login page | Correct auth layout |
| Nested Student Academic Summary tab | Import resolves and behavior matches |
| Debt inventory | Batch delta equals its frozen manifest; no new legacy path |

## Success Criteria

- [x] Every legacy directory/file has exactly one terminal-disposition owner.
- [x] Stable survivor batches have no stale render/import/test string.
- [x] Targeted backend tests, lint, format, typecheck, and production build pass.

## Risks and Security

- macOS case folding can hide broken production imports. Temporary-path moves and
  case-sensitive builds are mandatory; a phase cannot claim another owner's files.
