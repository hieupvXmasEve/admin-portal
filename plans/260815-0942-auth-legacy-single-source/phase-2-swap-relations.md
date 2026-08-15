# Phase 2 — Swap model relations off legacy pivot

## Context

Eloquent relations still ride `parent_student`; login actions load children
through them then filter by grant IDs — two sources in one response. After
Phase 1, these are the last app-code readers of the pivot.

## Files (verified consumers)

- `app/Models/ParentProfile.php:38` — `students()` belongsToMany pivot → edit
- `app/Models/User.php:288-298` — `children()` delegates to profile → edit
- `app/Models/Student.php:245` — `parents()` belongsToMany pivot → edit
- `app/Modules/Identity/Actions/ParentLoginAction.php:76` — load children
- `app/Modules/Identity/Actions/ParentGoogleLoginAction.php:100` — same
- `app/Modules/Identity/Actions/RevokeGuardianAccessAction.php:29` —
  `students()->exists()` gate
- `app/Console/Commands/CleanupParentDataCommand.php:64,69` — reads + `detach()`
- `app/Modules/Identity/Queries/GetParentContextQuery.php:22` — children context
- `app/Http/Resources/Parent/ParentResource.php` — DELETE (dead, zero importers,
  last `->pivot->` reader)

## Steps

1. Rewrite `ParentProfile::students()` as belongsToMany through
   `guardian_access_grants` (`parent_id`/`student_id`), constrained
   `wherePivot('status','active')`. Same signature → most consumers untouched.
2. `Student::parents()` mirrored on same pivot table swap.
3. `User::children()` unchanged code path (delegates) — verify only.
4. Login actions: after step 1 the `whereKey($activeStudentIds)` filter is
   redundant (relation already grant-scoped) — keep it one release for safety,
   mark with comment, remove in Phase 4 cleanup.
5. `RevokeGuardianAccessAction:29`: gate now reads ACTIVE grants via relation
   (was: any pivot row). Tightening CONFIRMED by user (Validation Session 1):
   revoking last active grant deactivates the account even when stale legacy
   rows exist. Update test expectations accordingly.
   <!-- Updated: Validation Session 1 - revoke gate = active grants only -->
6. `CleanupParentDataCommand`: `detach()` targets grants pivot after swap —
   redirect deletes to `guardian_access_grants` explicitly + keep legacy
   `parent_student` delete for hygiene (write-side cleanup is allowed to touch
   legacy table).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/`
- Parent portal smoke: login → children list → switch child → proxied
  student API 200 (manual or feature test
  `tests/Feature/Api/V1/Student/` parent-mode cases).
- Grep `parent_student` in `app/` → only migrations + CleanupParentDataCommand
  write-side.

## Risk / rollback

- Pivot-field readers VERIFIED (Validation Session 1): only
  `app/Http/Resources/Parent/ParentResource.php:40-41` reads
  `->pivot->relationship` / `->pivot->is_primary` — class has ZERO importers
  (dead code; login actions use
  `app/Modules/Identity/Http/Resources/Identity/ParentResource.php`, 0 pivot
  reads). Action: DELETE the dead global resource in this phase instead of
  restructuring it.
  <!-- Updated: Validation Session 1 - pivot risk resolved, delete dead file -->
- Rollback: revert commit; no schema change.
