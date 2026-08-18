---
phase: 5
title: "Remove dead force-delete code, close remaining test gaps"
status: pending
priority: P2
effort: "1.5h"
dependencies: [3]
---

<!-- Updated: Red Team Session 1 - Finding 11 -->

# Phase 5: Remove dead force-delete code, close remaining test gaps

## Overview

`App\Services\StudentService::deleteStudent()` (`app/Services/StudentService.php:546-758`) is unused (grep confirmed: zero callers outside its own file, no tests), force-deletes the student and a partial 8-relation list, and silently swallows every failure via `Log::warning`. It directly contradicts the new soft-delete feature and this repo's error-handling rule (never silently swallow errors). Delete it outright — per dev rules, "if certain something is unused, delete completely," not deprecate-in-place.

**Scope correction (red-team Finding 11):** the core permission/guard/invisibility/revocation/tombstone test matrix was moved into Phase 3, where the boundary is actually created — deferring it here left the whole authorization surface unmerged-and-untested if this P2 cleanup phase slipped. This phase now only removes dead code and adds the couple of tests that specifically depend on the removal (e.g. confirming no leftover force-delete path exists).

## Requirements

- Functional: no behavior regression — confirmed zero callers before deletion.
- Non-functional: repo has no reachable hard-delete-all-student-data code path left except deliberate `forceDelete()` calls already scoped to Phase 1 (`RevokeStudentIdentityAction`) — no new one introduced.

## Related Code Files

- Modify: `app/Services/StudentService.php` — remove `deleteStudent()` method (lines ~546-758) and any now-unused imports (`Exception` if solely used there — check other usages first).
- Verify: `grep -rn "deleteStudent(" app tests routes` returns zero hits pre-removal (already verified during planning) and post-removal.
- Confirm (not create — already added in Phase 3): `tests/Feature/Registry/SoftDeleteStudentTest.php` exists and covers the full matrix from Phase 3 step 6.

## Implementation Steps

1. Re-run `grep -rn "deleteStudent(" app tests routes` to reconfirm zero external callers immediately before removal (catch anything added during Phases 1-4).
2. Delete the method body from `StudentService.php`; remove now-dangling imports if any.
3. Confirm Phase 3's `tests/Feature/Registry/SoftDeleteStudentTest.php` is present and green — this phase does not duplicate that work, only verifies it landed.
4. Run `./scripts/dev.sh artisan test --filter=Registry` (correct path — `tests/Feature/StudentRegistry/` does not exist, actual directory is `tests/Feature/Registry/`) plus the broader Student-related suites touched in Phase 1.

## Success Criteria

- [ ] `StudentService::deleteStudent()` removed
- [ ] `tests/Feature/Registry/SoftDeleteStudentTest.php` (from Phase 3) confirmed present and passing — not re-authored here
- [ ] Full Student-related test suites green (or only pre-existing/known-flaky failures, per this repo's documented baseline)

## Risk Assessment

Low — removing genuinely dead code. Only risk is missing a caller added in a parallel branch; the pre-removal re-grep in step 1 covers that. This phase being P2/last no longer gates the actual security boundary on it, unlike the original plan.
