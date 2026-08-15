# Phase 4 — Unified login pipeline (parent / student / lecturer)

## Context

**8 token actions** (verified inventory 2026-08-15; no
`StudentRefreshTokenAction` exists):
`ParentLogin`, `ParentGoogleLogin`, `ParentRefreshToken`, `StudentLogin`,
`StudentGoogleLogin`, `LecturerLogin`, `LecturerGoogleLogin`,
`LecturerRefreshToken`. Each hand-rolls: rate limit, credential check,
account-active, type check, profile check, revocation/eligibility, token
issue. Checks drift (refresh verifies revocation; siblings differ). Status
gates hardcode lists instead of `Student::BLOCKED_STATUSES` (root of the
260815 deferred 403).

**Out of scope** (validated decision): generic staff web `LoginAction` —
session guard, different transport/exception style, stays as-is.
<!-- Updated: Validation Session 1 - 9→8 actions, staff LoginAction excluded -->

## Files

- `app/Modules/Identity/Support/LoginPipeline.php` — new (or
  `Support/Login/` if >1 class needed: actor-specific gate objects)
- All 8 token actions under `app/Modules/Identity/Actions/` — slim to
  actor-config + pipeline call
- `app/Http/Middleware/ParentStudentAccess.php` — status whitelist replaced by
  shared gate reading `Student::BLOCKED_STATUSES` complement
- `tests/Feature/Identity/LoginPipelineTest.php` — new matrix test

## Steps

1. Inventory exact check-order per action (8 files, table in PR description).
2. Extract shared steps: `rateLimit(key)`, `credentials`, `accountActive`,
   `actorType`, `profileActive`, `accessEligibility` (parent → grants;
   lecturer → LecturerAccessGrant; student → status gate), `issueToken`,
   `auditLogin`.
3. One canonical student-status gate helper (single source:
   `Student::BLOCKED_STATUSES`) used by both login and `ParentStudentAccess`
   middleware — deletes the drifting whitelist pattern permanently.
4. **FE error-handling audit FIRST** (validated decision: messages WILL be
   standardized, not frozen): grep `FE/student-nuxt` + parent/lecturer FE
   surfaces for string-matching on login error messages. Produce mapping
   old→new message. Any FE string-match found → coordinated FE change in the
   same phase.
   <!-- Updated: Validation Session 1 - standardize messages, FE audit gate -->
5. Rewrite actions thin; public signatures unchanged; messages standardized
   per approved mapping from step 4. Also remove the now-redundant
   `whereKey($activeStudentIds)` children filters left in Parent login actions
   by Phase 2 step 4 (relation already grant-scoped).
6. Matrix test: 3 actors × {ok, wrong password, inactive account, wrong type,
   revoked access, rate-limited} — assert NEW standardized messages (matrix
   written against the approved mapping, not old snapshots).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/`
- Login smoke all 3 portals on dev (password + Google + refresh).

## Risk / rollback

- Risk: behavior (semantics/check-order) parity — mitigate with the step-1
  check-order inventory table + matrix test. Message WORDING changes are
  deliberate (validated); message-dependent FE breakage mitigated by step-4
  FE audit + coordinated FE update.
- Risk: Google flows have provisioning side effects
  (`AuthenticateSocialUserAction`) — pipeline wraps, does not reorder.
- Rollback: actions are self-contained; revert commit restores old bodies.
