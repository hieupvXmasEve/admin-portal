---
phase: 7
title: "Auth: swap model relations off legacy pivot"
status: completed
priority: P1
effort: "0.5-1d"
dependencies: [6]
---

# Phase 7: Auth — swap model relations off legacy pivot

> Merged from `plans/260815-0942-auth-legacy-single-source/` phase 2 (incl. its Validation Session 1 decisions).

## Overview

Eloquent relations still ride `parent_student`; login actions load children through them then filter by grant IDs — two sources in one response. After Phase 6, these are the last app-code readers of the pivot.

## Related Code Files (verified consumers)

- Modify: `app/Models/ParentProfile.php:38` — `students()` belongsToMany pivot
- Modify: `app/Models/User.php:288-298` — `children()` delegates to profile
- Modify: `app/Models/Student.php:245` — `parents()` belongsToMany pivot
- Touch: `app/Modules/Identity/Actions/ParentLoginAction.php:76`, `ParentGoogleLoginAction.php:100` — load children
- Modify: `app/Modules/Identity/Actions/RevokeGuardianAccessAction.php:29` — `students()->exists()` gate
- Modify: `app/Console/Commands/CleanupParentDataCommand.php:64,69` — reads + `detach()`
- Touch: `app/Modules/Identity/Queries/GetParentContextQuery.php:22` — children context
- Delete: `app/Http/Resources/Parent/ParentResource.php` (dead, zero importers, last `->pivot->` reader — verified Validation Session 1 of source plan)

## Implementation Steps

1. Rewrite `ParentProfile::students()` as belongsToMany through `guardian_access_grants` (`parent_id`/`student_id`), constrained `wherePivot('status','active')`. Same signature → most consumers untouched.
2. `Student::parents()` mirrored on same pivot table swap.
3. `User::children()` unchanged code path (delegates) — verify only.
4. Login actions: after step 1 the `whereKey($activeStudentIds)` filter is redundant (relation already grant-scoped) — keep one release for safety, mark with comment, remove in Phase 9 cleanup.
5. `RevokeGuardianAccessAction:29`: gate now reads ACTIVE grants via relation (was: any pivot row). Tightening CONFIRMED by user (source-plan Validation Session 1): revoking last active grant deactivates the account even when stale legacy rows exist. Update test expectations.
6. `CleanupParentDataCommand`: redirect deletes to `guardian_access_grants` explicitly + keep legacy `parent_student` delete for hygiene (write-side cleanup may touch legacy table).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/`
- Parent portal smoke: login → children list → switch child → proxied student API 200 (`tests/Feature/Api/V1/Student/` parent-mode cases).
- Grep `parent_student` in `app/` → only migrations + CleanupParentDataCommand write-side.

## Success Criteria

- [x] All 3 relations ride `guardian_access_grants`; dead global ParentResource deleted
- [x] Identity + parent-mode student API tests green

## Risk / Rollback

- Pivot-field readers verified: only the deleted dead resource read `->pivot->relationship`/`->pivot->is_primary`.
- Rollback: revert commit; no schema change.
