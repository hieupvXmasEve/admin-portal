---
title: "Phase 6-9 completion report — auth legacy single-source"
date: 2026-08-16
plan: plans/260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup/plan.md
phases: [6, 7, 8, 9]
---

# Phase 6-9 completion — auth legacy single-source

## Status

Phases 6-9 status → `completed`. Plan.md phase table + success criteria updated. Full diff uncommitted — awaiting user go-ahead to commit.

## What shipped

| Phase | Summary |
|---|---|
| 6 | `EloquentGuardianAccessGrantReader` grants-only (legacy `parent_student` dual-read removed) |
| 7 | `ParentProfile::students()` / `Student::parentProfiles()` repointed to `guardian_access_grants`; dead global `ParentResource` deleted; `CleanupParentDataCommand` explicit + transactional |
| 8 | `EitherMiddleware` deleted, replaced by explicit `ParentOrStudentAccess` at all 3 `either:` sites |
| 9 | 8 login/refresh actions (Parent/Student/Lecturer × password/google/refresh) unified onto `LoginPipeline`; `Student::BLOCKED_STATUSES` single-sourced for `ParentStudentAccess` |

## Verification

- Independent `code-reviewer` + `tester` subagents (background, this session).
- Tester: 182 tests, 0 unexpected failures.
- Code-reviewer: 3 HIGH, 4 MEDIUM, 4 LOW findings. HIGH1/HIGH3 + MEDIUM7 fixed; re-verified 163/164 green (1 pre-existing unrelated `StudentProfileReaderTest` failure, confirmed via `git stash` against clean HEAD alongside 5 other pre-existing Architecture-suite failures unrelated to this session).
- New tests: `LoginPipelineTest.php` (13 cases), `ParentOrStudentAccessTest.php` (7 cases, incl. logout regression case), extended `GuardianAccessGrantTest.php`.

## Fixes applied from review

1. **HIGH** — `/auth/logout` moved out of `parent_or_student` group (`app/Modules/Identity/routes/api.php`): the new middleware correctly runs full `StudentApiAuthorization` (active + holds) for students, where the old buggy `EitherMiddleware` silently skipped it entirely. Logout has zero business logic and must never be hold-gated.
2. **HIGH** — `LecturerAuthController::refresh()` missing `instanceof AuthenticationException` branch — was silently 500ing for inactive/restricted lecturers after the exception-type unification. Added, matches the sibling fix already applied to `loginWithGoogle()`.
3. **MEDIUM** — `CleanupParentDataCommand`'s Case 1 wrapped in `DB::transaction()` — was leaving grants/pivot deleted with the profile still alive on mid-loop failure.
4. **LOW** — `LoginPipeline::verifyGoogleIdToken()` catch widened to `GoogleException|\UnexpectedValueException` — Firebase JWT's `ExpiredException`/`SignatureInvalidException` extend `UnexpectedValueException`, not `Google\Exception`, and were leaking as uncaught/500 for Lecturer.
5. Pre-existing pint spacing fixed in the touched region of `CleanupParentDataCommand.php`.

## Live decisions (asked, not guessed)

1. **`Student::BLOCKED_STATUSES` scope** — approved adding `suspended`, `admission_deferred`, `pending_course_opening` (unifies the old drifting parent-proxy allowlist into the single source).
2. **Should login gate on the same source?** — declined. `StudentLifecyclePortalCompatibilityTest` proved raw-status `'pending'` students must still log in (displayed via a projected friendlier status). `StudentLoginAction`/`StudentGoogleLoginAction` stay gate-free on `Student.status`.

## Unresolved / flagged, not fixed

- **`GrantGuardianAccessAction`/`RevokeGuardianAccessAction`** (`app/Modules/Identity/Actions/`) still read/write the legacy `parent_student` table — not in phases 6-9's file list, left alone. `grep -rn parent_student app/` still shows hits beyond migrations + `CleanupParentDataCommand` (the plan's stated exception).
- **`BLOCKED_STATUSES` fan-out** — the 3 new statuses flow into every pre-existing `Student::isActive()`/`scopeActive()` consumer, not just login/parent-proxy: `EloquentStudentImpersonationTokenIssuer` (staff support impersonation now also blocked for these 3 statuses), `EloquentStudentPortalTokenRefresher`, Engagement `FormController`/`EventParticipationOperations`, `MerchandiseCatalogNotificationPublisher`, `can_register_courses` projection. All pre-existing consumers of the same constant — approved in principle ("block everywhere"), but not re-confirmed call-site-by-call-site. Worth a prod status-distribution check before deploy, especially the impersonation path (support staff may need to impersonate a suspended student to help them).
- **`isStudentAccessible()` simplification** loosens parent-proxy access for status `intake_major` (previously blocked by a stale/drifted allowlist that never included it; the new single-source `Student::isActive()` doesn't block it either since it was never in `BLOCKED_STATUSES`). Reasoned as a staleness-bug fix (grouped with `intake_course`/`intake_pre_uni_gc` in `FINANCIAL_STATUSES`), not re-confirmed with the user.
- **Rate-limit key** (`login:{ip}`) shared between parent and student portals, not actor-scoped — pre-existing, unchanged.
- **`StudentGoogleLoginAction`'s `$ip` parameter** is dead (never consumed) — pre-existing, left as-is to avoid an unnecessary public-signature/controller-call-site churn.

## Next

- User to review + approve commit (nothing committed this session).
- Phase 10 (notification/email soak) and phases 1-5 shim track remain independent, untouched.
