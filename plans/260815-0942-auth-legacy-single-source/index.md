# Auth Legacy Single-Source Retirement

> **MERGED 2026-08-15** into `plans/260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup/` (phases 6-9), per user consolidation request. Phase 0 audit results below remain the evidence base. Do not execute from this file.

Retire dual-read (`parent_student` legacy pivot vs `guardian_access_grants`) from
auth/login paths; one truth source for guardian↔student authz; explicit
middleware; unified login checks for parent/student/lecturer.

## Status

| Phase | Title | Status |
|-------|-------|--------|
| 0 | Audit & parity baseline | ✅ done 2026-08-15 |
| 1 | Grants-only reader (kill dual-read) | → merged as 260815-1320 phase 6 |
| 2 | Swap model relations off legacy pivot | → merged as 260815-1320 phase 7 |
| 3 | Replace EitherMiddleware with explicit ParentOrStudentAccess | → merged as 260815-1320 phase 8 |
| 4 | Unify login pipeline (3 actors) | → merged as 260815-1320 phase 9 |

Dependencies: 1→2→3 sequential (each reduces surface for next). 4 independent of
2-3, needs 1.

## Phase 0 results (dev DB, synced from prod)

- `parent_student` 194 rows = 194 distinct pairs; `guardian_access_grants` 194
  all active. **legacy-only pairs: 0, grant-only pairs: 0, orphans: 0.**
- → NO backfill needed. Dual-read code is dead weight; cut with parity guard.
- `is_primary` lives on `student_guardian_relationships` (Registry);
  grants join via `guardian_relationship_id` — replaces `parent_student.is_primary`.
- `students.status` staleness vs `program_enrollments` = separate Academic
  issue, **non-goal** here.

## Contract

- **Outcome:** all auth decision points read guardian link via
  `GuardianAccessGrantReader` (grants-only); no `parent_student` reads in app
  code; middleware failures no longer swallowed; one shared login check chain.
- **Constraints:** no breaking prod login (5 campuses); Identity owns authz
  (GuardianOwnershipBoundaryArchTest); Sanctum token model unchanged.
- **Non-goals:** SSO, token model change, `students.status` sync fix,
  dropping `parent_student` table itself (data stays as history).
- **Acceptance:** grep `parent_student` in `app/` → only migrations +
  `CleanupParentDataCommand` write-side hygiene deletes; parity guard test
  green; Identity + Architecture suites green; parent login smoke
  (login → children list → proxied student API) identical before/after.

## Phases

- [phase-1-grants-only-reader.md](phase-1-grants-only-reader.md)
- [phase-2-swap-relations.md](phase-2-swap-relations.md)
- [phase-3-explicit-middleware.md](phase-3-explicit-middleware.md)
- [phase-4-unified-login-pipeline.md](phase-4-unified-login-pipeline.md)

## Deployment

Validated decision: **phase-per-deploy with soak** — each phase deploys to prod
separately, monitored a few days before the next. No batching.

## Validation Log

### Session 1 — 2026-08-15

**Verification Results**
- Claims checked: 12 | Verified: 9 | Failed: 2 | Dead-code finding: 1
- Tier: Standard (4 phases; Fact Checker + Contract Verifier)
- Failures (both corrected in phase files):
  1. Phase 3 "either: single-site" → actually 3 sites (routes/api/v1/student.php:38,
     app/Modules/Identity/routes/api.php:32, app/Modules/Engagement/routes/api.php:56)
  2. Phase 4 "9 actions" → 8 token actions; no StudentRefreshTokenAction exists
- Finding: `->pivot->` readers confined to dead `app/Http/Resources/Parent/ParentResource.php`
  (zero importers) → Phase 2 deletes it.

**Decisions (interview)**
1. Staff web `LoginAction`: OUT of Phase 4 scope (session guard, different transport).
2. Revoke gate: counts ACTIVE grants only — intended tightening.
3. Login error messages: STANDARDIZE (not freeze) — Phase 4 gains mandatory FE
   error-handling audit + coordinated FE update before message changes ship.
4. Deploy: phase-per-deploy + soak.

### Whole-Plan Consistency Sweep — Session 1
- Phase 3 context/files/steps now consistently list 3 route sites.
- Phase 4 inventory, files, steps, risk all reflect 8 actions + standardize-messages
  decision; stale snapshot-parity wording removed.
- Phase 2 revoke-gate step marked as confirmed decision; pivot risk converted to
  concrete delete action; files list updated.
- Phase 1 untouched by decisions — no stale references found.
- Found + fixed during sweep: (a) index acceptance said "only migrations" while
  phase-2 permits CleanupParentDataCommand write-side deletes — reconciled;
  (b) phase-2 step 4 defers whereKey-filter removal to Phase 4 but Phase 4
  didn't list it — added to Phase 4 step 5.
- Unresolved contradictions: 0.
