---
phase: 8
title: "Auth: explicit ParentOrStudentAccess middleware"
status: completed
priority: P1
effort: "0.5d"
dependencies: [7]
---

# Phase 8: Auth — explicit ParentOrStudentAccess middleware

> Merged from `plans/260815-0942-auth-legacy-single-source/` phase 3 (3 `either:` sites verified 2026-08-15 — source-plan Validation Session 1 corrected the single-site claim).

## Overview

`EitherMiddleware` (generic OR-combinator) swallows every exception (`catch → continue`), fakes `$next` with a mock closure, and returns the LAST failing middleware's response — masking real errors, undebuggable 403s. Used as `either:parent.student.access,student.api.auth` at 3 sites:
- `routes/api/v1/student.php:38`
- `app/Modules/Identity/routes/api.php:32`
- `app/Modules/Engagement/routes/api.php:56`

## Related Code Files

- Create: `app/Http/Middleware/ParentOrStudentAccess.php` (single explicit class)
- Delete: `app/Http/Middleware/EitherMiddleware.php` (after route swap; separate commit for clean revert)
- Keep: `app/Http/Middleware/ParentStudentAccess.php` (parent-proxy logic reused)
- Modify: `bootstrap/app.php:73-76` — alias swap: add `parent_or_student`, drop `either` once unused
- Modify: the 3 route files above — swap group middleware
- Create/extend: `tests/Feature/Identity/ParentOrStudentAccessTest.php` — behavior matrix

## Implementation Steps

1. Re-confirm `either:` sites before edit: `grep -rn "either:" routes/ app/` (3 known sites).
2. New `ParentOrStudentAccess`: user instanceof Student → delegate `student.api.auth` chain; User (parent) → delegate `ParentStudentAccess::handleParentAccess` path. NO exception swallowing — real exceptions propagate; explicit 403 body states which leg failed.
3. Swap route group middleware at ALL 3 sites.
4. Delete `EitherMiddleware` + alias.
5. Behavior-matrix test: student token ok; parent+grant ok; parent no student_id → 422; parent wrong student → 403; revoked grant → 403; blocked-status student → 403.

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/ tests/Feature/Api/V1/Student/`
- Manual: parent + student portal smoke on dev.

## Success Criteria

- [x] `EitherMiddleware` deleted; 3 sites on `parent_or_student`; matrix test green

## Risk / Rollback

- Risk: `student.api.auth` (`StudentApiAuthorization`) side effects duplicated or skipped — read that class fully before writing new middleware; preserve its checks for the student leg.
- Rollback: restore alias + route lines (2-line revert); EitherMiddleware deletion is its own commit.
