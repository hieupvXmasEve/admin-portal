---
title: "Phase 1 Work Packages"
status: completed
last_verified: 2026-07-26
source_sha: 7c19f67090f8e328522f0635d012020491307112
candidate_artifact_sha256: aae649e3fdb79e2132db38d3ed3533e0882c41af4d1806b44a31c45fb1a081dc
---

# Phase 1 Work Packages

## Baseline

- Branch: `dev`
- Inventory: 14 rules; `shared_model_imports=587`; guard failed only at `587 > 568`.
- Route snapshot: 984 routes; normalized SHA-256
  `8209387b46e255723e0952bbf3cf032cf98be0f877a882babad4e6c3e4ca002d`.
- Schedule snapshot: 11 entries; normalized SHA-256
  `5e844718806298513eb1021622167d1252a06290953d8c7271047a2e01f0fa54`.
- Production data/schema impact: none. Production migrations remain opt-in and disabled by default.
- Finding ownership: 1,200/1,200 assigned, zero unassigned; normalized manifest
  SHA-256 `416d3823601d0bad3260489ab66d9420dc11217131878255d73fef4a98f6bfaf`.
  Each inventory finding exposes a stable `id` and exactly one `work_package`;
  `coverage.finding_ownership.package_counts` is the executable grouped ledger.
- Candidate implementation artifact after Pint: SHA-256
  `aae649e3fdb79e2132db38d3ed3533e0882c41af4d1806b44a31c45fb1a081dc`
  over the nine ordered Phase 1 implementation/evidence files.

## Concurrent accepted checkpoint

The dynamic system-configuration work landed in the shared worktree while
Phase 1 was under review. It added two named Platform routes and replaced one
literal frontend URL with a named route. Phase 1 did not modify or absorb that
feature; the following checkpoint records the resulting repository state so
the final gate is reproducible:

- Route snapshot: 986 routes; normalized SHA-256
  `16ee70531c207f33c473c6c57e12cd73d26267dd02a1bc1729ef6003c76debc9`.
- Schedule snapshot: 11 entries; normalized SHA-256 remains
  `5e844718806298513eb1021622167d1252a06290953d8c7271047a2e01f0fa54`.
- `literal_frontend_urls`: 61 → 60.
- Finding ownership: 1,199/1,199 assigned, zero unassigned; normalized manifest
  SHA-256 `d3e3ff142d7f0c613ab186198e1606bbb971b5e8cdf5351cbfa968f4a2a4035a`.
- Phase 1's nine-file candidate artifact remains
  `aae649e3fdb79e2132db38d3ed3533e0882c41af4d1806b44a31c45fb1a081dc`.

### Candidate artifact reproduction

Ordered files:

1. `app/Support/MigrationDebt/MigrationDebtContract.php`
2. `app/Support/MigrationDebt/MigrationDebtInventory.php`
3. `app/Support/MigrationDebt/MigrationDebtGuard.php`
4. `config/migration_debt.php`
5. `config/migration_debt_paths.php`
6. `app/Modules/Academic/Delivery/Http/Api/Lecturer/StudentController.php`
7. `app/Modules/Academic/Delivery/Http/Api/Lecturer/TimetableController.php`
8. `tests/Feature/Architecture/MigrationDebtInventoryTest.php`

Reproduce from the repository root:

```bash
for f in app/Support/MigrationDebt/MigrationDebtContract.php app/Support/MigrationDebt/MigrationDebtInventory.php app/Support/MigrationDebt/MigrationDebtGuard.php config/migration_debt.php config/migration_debt_paths.php app/Modules/Academic/Delivery/Http/Api/Lecturer/StudentController.php app/Modules/Academic/Delivery/Http/Api/Lecturer/TimetableController.php tests/Feature/Architecture/MigrationDebtInventoryTest.php; do
    shasum -a 256 "$f"
done | shasum -a 256
```

## Package ledger

| Package | Owner/workflow | Exact manifest | Rule before → delta → after | Contract and merge gate |
|---|---|---|---|---|
| P1-WP1 | Platform / inventory contract | `MigrationDebtContract.php`, `migration_debt.php`, `MigrationDebtInventory.php`, `MigrationDebtGuard.php`, architecture test | Rules 14 → 0 → 14; findings 1,200→0 unassigned→1,200 assigned; guarded counts unchanged | Immutable rule/root/surface/ownership/baseline ceilings; shadow metrics only; exact ownership manifest; targeted tests and guard green |
| P1-WP2 | Platform / frozen path snapshots | `migration_debt_paths.php` | services 93→-7→86; controllers 95→-19→76; routes 37→-5→32 | Current path set equals approved set; stale/new/duplicate paths fail |
| P1-WP3 | Academic Delivery / lecturer actor annotations | Lecturer `StudentController.php`, `TimetableController.php` | shared imports 587→-19→568 | PHPDoc uses existing `LecturerTeachingActor`; no runtime/API change; lecturer architecture/API tests pass |
| P1-WP4 | Academic Progression / consumer evidence | reconciliation query evidence | consumers 18→-3→15 | Commit `157e2ea7` explains three removed Finance defer readers; read-only audit remains 810 exceptions/15 consumers |
| P1-WP5 | Platform / execution ledger | this file and Phase 1 plan state | unowned findings → exact package ownership | No production write; every later finding is hydrated into the shared work-package template |

## Verification commands

```text
./scripts/dev.sh artisan migration-debt:inventory --check --format=json
./scripts/dev.sh artisan test --compact tests/Feature/Architecture/MigrationDebtInventoryTest.php
./scripts/dev.sh artisan test --compact tests/Feature/Architecture/LecturerApiCutoverTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/check-docs.sh
```

## Rollback

All Phase 1 changes are source-only. Revert the package if the normalized route
or schedule snapshot changes, an API/schema contract changes, a guarded count
other than the intended shared-import delta changes, or any targeted test fails.
