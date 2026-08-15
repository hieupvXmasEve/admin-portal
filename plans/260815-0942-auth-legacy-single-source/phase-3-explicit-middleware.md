# Phase 3 — Explicit ParentOrStudentAccess middleware

## Context

`EitherMiddleware` (generic OR-combinator) swallows every exception
(`catch → continue`), fakes `$next` with a mock closure, and returns the LAST
failing middleware's response — masking real errors and making 403s
undebuggable. Used as `either:parent.student.access,student.api.auth` at
**3 sites** (verified 2026-08-15):

- `routes/api/v1/student.php:38`
- `app/Modules/Identity/routes/api.php:32`
- `app/Modules/Engagement/routes/api.php:56`

## Files

- `app/Http/Middleware/ParentOrStudentAccess.php` — new (single explicit class)
- `app/Http/Middleware/EitherMiddleware.php` — delete (after route swap)
- `app/Http/Middleware/ParentStudentAccess.php` — keep (parent-proxy logic
  reused by new class or called directly)
- `bootstrap/app.php:73-76` — alias swap: add `parent_or_student`, drop
  `either` once unused
- `routes/api/v1/student.php:38` — swap group middleware
- `app/Modules/Identity/routes/api.php:32` — swap group middleware
- `app/Modules/Engagement/routes/api.php:56` — swap group middleware
- `tests/Feature/Identity/GuardianAccessGrantTest.php` or new
  `tests/Feature/Identity/ParentOrStudentAccessTest.php` — behavior matrix

## Steps

1. Re-confirm `either:` sites before edit: `grep -rn "either:" routes/ app/`
   (3 known sites; Engagement + Identity module route files included).
   <!-- Updated: Validation Session 1 - single-site claim FAILED, 3 sites -->

2. New `ParentOrStudentAccess`: if user instanceof Student → delegate
   `student.api.auth` chain (call `StudentApiAuthorization` logic or keep as
   route-level second middleware); if User (parent) → delegate to
   `ParentStudentAccess::handleParentAccess` path. No exception swallowing —
   let real exceptions propagate; explicit 403 body states which leg failed.
3. Swap route group `either:parent.student.access,student.api.auth` →
   `parent_or_student` at ALL 3 sites (student.php, Identity api.php,
   Engagement api.php).
4. Delete `EitherMiddleware` + alias.
5. Behavior-matrix test: student token ok; parent+grant ok; parent no
   student_id → 422; parent wrong student → 403; revoked grant → 403;
   blocked-status student → 403 (regression from 260815 deferred fix stays
   green).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/ tests/Feature/Api/V1/Student/`
- Manual: parent portal + student portal smoke on dev.

## Risk / rollback

- Risk: `student.api.auth` (`StudentApiAuthorization`) side effects duplicated
  or skipped — read that class fully before writing new middleware; new class
  must preserve its checks for the student leg.
- Rollback: restore alias + route line (2-line revert); EitherMiddleware
  deletion in separate commit for clean revert.
