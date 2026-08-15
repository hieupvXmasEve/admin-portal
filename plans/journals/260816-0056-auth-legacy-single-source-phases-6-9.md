---
date: 2026-08-16 00:56
plan: plans/260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup/
severity: medium
status: resolved
---

# Auth Legacy Single-Source: Phases 6-9

## Context

Continuing the legacy-shim decommission plan. This session covered phases 6-9:
kill the `parent_student` pivot dual-read, repoint `ParentProfile`/`Student`
relations onto `guardian_access_grants`, delete `EitherMiddleware`, unify the
8 Identity login/refresh actions onto a shared `LoginPipeline`.

## What Happened

- Phase 6: `EloquentGuardianAccessGrantReader` went grants-only. Prod audit
  found 0 divergence between the pivot and grants table — the dual-read was
  pure dead weight, no migration risk.
- Phase 7: relations repointed off the pivot, dead `ParentResource` deleted,
  `CleanupParentDataCommand` rewritten transactional.
- Phase 8: `EitherMiddleware` deleted. It was an OR-combinator that swallowed
  every exception and faked `$next` — real errors were getting masked behind
  undebuggable 403s. Replaced with explicit `ParentOrStudentAccess` at all 3
  `either:` route sites.
- Phase 9: unified 8 login/refresh actions (Parent/Student/Lecturer x
  password/google/refresh) onto `LoginPipeline`. Failure paths now uniformly
  throw `AuthenticationException` — previously a mix of plain `Exception`,
  `AuthenticationException`, `ValidationException` depending on which actor
  code path you hit.

Two decisions needed a live human call, not guessable from code (asked via
AskUserQuestion):
1. Approved: fold `'suspended'`, `'admission_deferred'`,
   `'pending_course_opening'` into `Student::BLOCKED_STATUSES` — a
   parent-proxy status allowlist had drifted separately from this for a
   while.
2. Declined: gating login itself on that same status list. Tried it, broke
   `StudentLifecyclePortalCompatibilityTest` — a student in raw status
   `'pending'` must still log in and see a friendlier projected status.
   Reverted; login stays gate-free on `Student.status`.

## The Brutal Truth

The independent code-reviewer subagent caught a real regression I would have
shipped: replacing `EitherMiddleware` with `ParentOrStudentAccess` correctly
runs the full `StudentApiAuthorization` chain (active status + academic
holds) — which the *old buggy* middleware's try-order bug had been silently
skipping for student tokens the whole time. Fixing the bug meant blocked
students would now also get locked out of `/auth/logout` and `/auth/refresh`.
`StudentLogoutAction` has zero business logic, it just deletes a token — it
must never be gated. Classic "fix one bug, discover the code was depending on
the other bug." Moved `/auth/logout` out of the guarded middleware group.

## Technical Details

Other correctness bugs the reviewer flagged and I fixed before calling this
done:
- `LecturerAuthController::refresh()` was missing the
  `instanceof AuthenticationException` branch its sibling
  `loginWithGoogle()` had — would've silently 500'd instead of 401'd for
  inactive/access-restricted lecturers.
- `CleanupParentDataCommand` Case 1 wasn't transactional — mid-loop failure
  could leave grants deleted with the profile still alive.
- `LoginPipeline::verifyGoogleIdToken()` catch widened to also catch Firebase
  JWT's `UnexpectedValueException` family, not just `Google\Exception`.

Two background subagents (code-reviewer, tester) verified before finalize.
Tester: 182 green, zero regressions. Code-reviewer: 3 HIGH + 4 MEDIUM + 4 LOW;
fixed the HIGH/MEDIUM ones affecting correctness (above), re-ran at 163/164
green. The 1 remaining failure
(`tests/Feature/Registry/StudentProfileReaderTest.php`) plus 5 Architecture
suite failures confirmed pre-existing via `git stash` against clean HEAD.

## Root Cause Analysis

`EitherMiddleware` existed because nobody wanted to write two explicit route
guards. The convenience abstraction ate exceptions to make the OR-logic work,
which is exactly the kind of thing that hides a broken auth chain for
however long it's been live. Explicit code paths per actor cost more lines,
but you can read what they actually gate.

## Lessons Learned

- An "either this or that" middleware/guard combinator is a smell: it has to
  swallow the failure of the branch it didn't pick, and that swallowing is
  where real bugs go to hide.
- When you fix a bug in shared gating logic, immediately audit every
  downstream endpoint the gate now covers for endpoints that must stay
  ungated (logout, health checks, refresh-token revocation) — the old bug may
  have been load-bearing for them.
- Independent review + test subagents running in the background caught two
  separate real issues before merge; worth the wall-clock cost every time
  auth/session code is touched.

## Next Steps

Flagged to user, explicitly out of phase 6-9 scope, not fixed this session:
- `GrantGuardianAccessAction` / `RevokeGuardianAccessAction`
  (`app/Modules/Identity/Actions/`) still read/write the legacy
  `parent_student` table for a business-rule check and a "transitional
  compatibility projection" write.
- The `BLOCKED_STATUSES` widening fans out into consumers beyond login
  (student impersonation, portal token refresh, Engagement forms/events,
  course-registration eligibility) that weren't individually re-confirmed
  with the user — only approved in principle.

6 commits on `dev`, unpushed. Full completion report:
`plans/reports/pm-260816-0034-phase-6-9-auth-completion.md`.
