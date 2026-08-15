---
phase: 9
title: "Auth: unified login pipeline (parent / student / lecturer)"
status: completed
priority: P1
effort: "1-1.5d"
dependencies: [6]
---

# Phase 9: Auth — unified login pipeline

> Merged from `plans/260815-0942-auth-legacy-single-source/` phase 4 (incl. Validation Session 1: 8 actions not 9, staff web `LoginAction` OUT of scope, messages standardized not frozen). Independent of phases 7-8; needs phase 6.

## Overview

**8 token actions** (verified 2026-08-15; no `StudentRefreshTokenAction` exists): `ParentLogin`, `ParentGoogleLogin`, `ParentRefreshToken`, `StudentLogin`, `StudentGoogleLogin`, `LecturerLogin`, `LecturerGoogleLogin`, `LecturerRefreshToken`. Each hand-rolls rate limit, credential check, account-active, type check, profile check, revocation/eligibility, token issue. Checks drift (refresh verifies revocation; siblings differ). Status gates hardcode lists instead of `Student::BLOCKED_STATUSES` (root of the 260815 deferred 403).

**Out of scope** (validated): generic staff web `LoginAction` — session guard, different transport/exception style, stays as-is.

## Related Code Files

- Create: `app/Modules/Identity/Support/LoginPipeline.php` (or `Support/Login/` if >1 class: actor-specific gate objects)
- Modify: all 8 token actions under `app/Modules/Identity/Actions/` — slim to actor-config + pipeline call
- Modify: `app/Http/Middleware/ParentStudentAccess.php` — status whitelist replaced by shared gate reading `Student::BLOCKED_STATUSES` complement
- Create: `tests/Feature/Identity/LoginPipelineTest.php` — matrix test

## Implementation Steps

1. Inventory exact check-order per action (8 files, table in PR description).
2. Extract shared steps: `rateLimit(key)`, `credentials`, `accountActive`, `actorType`, `profileActive`, `accessEligibility` (parent → grants; lecturer → LecturerAccessGrant; student → status gate), `issueToken`, `auditLogin`.
3. One canonical student-status gate helper (single source: `Student::BLOCKED_STATUSES`) used by both login and `ParentStudentAccess` — deletes the drifting whitelist pattern permanently.
4. **FE error-handling audit FIRST** (validated: messages WILL be standardized): grep `FE/student-nuxt` + parent/lecturer FE surfaces for string-matching on login error messages. Produce old→new mapping. Any FE string-match → coordinated FE change same phase.
5. Rewrite actions thin; public signatures unchanged; messages per approved mapping. Remove the now-redundant `whereKey($activeStudentIds)` children filters left by Phase 7 step 4.
6. Matrix test: 3 actors × {ok, wrong password, inactive account, wrong type, revoked access, rate-limited} — assert NEW standardized messages.

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/`
- Login smoke all 3 portals on dev (password + Google + refresh).

## Success Criteria

- [x] 8 actions on shared pipeline; one status-gate source; matrix test green (login intentionally excluded from status gate, see Deviations)
- [x] FE mapping applied — zero FE string-matches on login error messages found (grepped FE/student-nuxt + FE/lecturer-nuxt); no FE changes needed

## Risk / Rollback

- Behavior parity via step-1 check-order inventory + matrix test. Message wording changes deliberate (validated); FE breakage mitigated by step-4 audit.
- Google flows have provisioning side effects (`AuthenticateSocialUserAction`) — pipeline wraps, does not reorder.
- Rollback: actions self-contained; revert restores old bodies.

## Deviations (session 2026-08-16)

- **Login does NOT gate on `Student::isActive()`.** Tried per step 3; broke `StudentLifecyclePortalCompatibilityTest` (raw status `'pending'` students must still be able to log in and see a projected friendlier status). User decided (asked live): keep `StudentLoginAction`/`StudentGoogleLoginAction` gate-free on student status, same as before. `Student::BLOCKED_STATUSES` is the single source for `ParentStudentAccess` (drifting whitelist deleted) and other pre-existing operational gates, but NOT for the student's own login.
- **`Student::BLOCKED_STATUSES` gained `suspended`, `admission_deferred`, `pending_course_opening`** (user-approved, asked live) to unify the old parent-proxy allowlist into the single source. This fans out into every existing `Student::isActive()`/`scopeActive()` consumer beyond login: `EloquentStudentImpersonationTokenIssuer`, `EloquentStudentPortalTokenRefresher`, Engagement `FormController`/`EventParticipationOperations`, `MerchandiseCatalogNotificationPublisher`, `EloquentStudentPortalContextReader::can_register_courses`. All pre-existing consumers of the same constant, not new call sites — flagged to user, not re-confirmed per call site.
- **All 8 actions' failure paths now throw `Illuminate\Auth\AuthenticationException` uniformly** (previously a mix of plain `Exception`/`AuthenticationException`/`ValidationException` per actor). Lecturer's `login()`/`refresh()`/`loginWithGoogle()` catch blocks in `LecturerAuthController.php` updated to add an `instanceof AuthenticationException` branch so business-rule failures return 401 instead of falling through to 500 — this is a real HTTP status change for Lecturer (previously 422 for "inactive"/"access restricted" via `login()`; now 401 for all three lecturer entry points). Confirmed via grep that neither `FE/lecturer-nuxt` nor `FE/student-nuxt` branch on login error status codes or messages.
- **`/v1/student/auth/logout` moved outside the `parent_or_student` middleware group** (`app/Modules/Identity/routes/api.php`) — code review caught that `ParentOrStudentAccess` now correctly runs the full `StudentApiAuthorization` chain (active status + academic holds) for students, where the old buggy `EitherMiddleware` silently skipped it. Logout has zero business logic (`StudentLogoutAction` just deletes the current token) and must never be blocked by academic holds. Note: the outer `api.actor:student_or_parent` gate (`ApiActorPolicy::accessStudentOrParent`, untouched, pre-existing) still independently blocks blocked-status students from this whole route group including logout — that is pre-existing behavior unrelated to this phase, not something this fix resolves or was meant to.
- **Not implemented**: `GrantGuardianAccessAction`/`RevokeGuardianAccessAction` (`app/Modules/Identity/Actions/`) still read/write the legacy `parent_student` table (a business-rule read plus "transitional compatibility projection" writes). Not in this phase's touched-file list; left alone deliberately. Means `grep -rn parent_student app/` still shows hits beyond migrations + `CleanupParentDataCommand` (the plan's stated exception) — flagged, not fixed.
