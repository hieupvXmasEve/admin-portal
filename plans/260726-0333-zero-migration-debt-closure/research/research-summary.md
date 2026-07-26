---
title: "Zero Migration Debt Research Summary"
status: complete
owner: Platform Team
last_verified: 2026-07-26
---

# Zero Migration Debt Research Summary

## Observed state

- The live debt guard fails at `shared_model_imports=587` versus maximum `568`.
- Other non-zero counts are 86 services, 76 controllers, 32 routes, 45 direct
  response files, 77 inline-validation files, 178 PHP and 22 route strict-type
  files, 25 filter files, 61 literal-URL files, 23 legacy page directories, and
  seven migration-classified commands.
- Approved frozen-path snapshots contain 31 paths that no longer exist, so a
  removed shell could currently be reintroduced without a path violation.
- `cross_context_concrete_imports=0` and `removed_inertia_apis=0` are invariants.

## Dependency findings

- Shared-model consumers concentrate in Academic 343, Finance 93, AI 33,
  Identity 32, Engagement 23, Upload 20, Notification 19, Institution 14,
  StudentRegistry 8, and Facilities 2.
- Highest-fanout references are User 82, Student 78, Campus 45,
  CourseOffering 38, Semester 33, Lecture 31, StudentActionLog 25, and
  AcademicRecord 19. Identity, Institution, and Student Registry seams must
  therefore precede dependent domain cutovers.
- Thirty-two of 45 raw-response files also validate inline. Thirty-one are
  frozen controllers. They should migrate with their workflow, not via
  disposable repository-wide syntax sweeps.
- The 23 legacy page directories contain 138 files and are referenced by 143
  backend render sites; canonicalize them before later frontend edits.

## Data and release findings

- Latest issue evidence reports 810 Academic exceptions and 18 compatibility
  consumers, while the live audit previously reported 15. Phase 1 must reconcile
  the exact starting manifest before any consumer cutover.
- The Asia dump dated 2026-07-26 is effectively one migration behind; the
  Metropolia dump dated 2026-07-04 is effectively 34 migrations behind.
- Several July Finance pointer-removal migrations are forward-only, while their
  one-time backfill tools are intentionally retired. Metropolia therefore needs
  a fresh-dump rehearsal and possibly a separately approved temporary cutover.
- Production deploy currently targets both campuses but defaults migrations
  off and does not enforce backup, queue/scheduler, data-preflight, health, or
  post-release reconciliation gates.

## Decisions applied to this plan

1. Hold scope at exact zero; never raise a baseline or create a cosmetic allowlist.
2. Separate logical ownership from physical schema deletion.
3. Rename proven-surviving frontend paths first; replace/delete the rest in their owner slices.
4. Perform data backfill and schema retirement in different approved releases.
5. Treat Metropolia and Asia as independent releases with independent evidence
   and choose rollout order only after rehearsal/risk review.
6. Preserve provider-specific envelopes such as DNG through named boundaries.

## Primary evidence

- `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt.php`
- `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt_paths.php`
- `/Users/hunt2412/hieupvdev/project/swinx/app/Support/MigrationDebt/MigrationDebtInventory.php`
- `/Users/hunt2412/hieupvdev/project/swinx/app/Support/MigrationDebt/MigrationDebtGuard.php`
- `/Users/hunt2412/hieupvdev/project/swinx/docs/rules/legacy-migration.md`
- `/Users/hunt2412/hieupvdev/project/swinx/docs/rules/contracts.md`
- `/Users/hunt2412/hieupvdev/project/swinx/docs/deployment-guide.md`
- `/Users/hunt2412/hieupvdev/project/swinx/.scratch/repository-migration-debt-closure/issues/23-close-repository-migration-debt-inventory.md`
- `/Users/hunt2412/hieupvdev/project/swinx/.scratch/repository-migration-debt-closure/issues/24-backfill-reconcile-historical-academic-progression-evidence.md`
