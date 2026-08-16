---
phase: 9
title: "Phase 9: Retire frozen shells and strict type residue"
status: pending
priority: P1
effort: L
dependencies: [4, 5, 6, 7, 8]
---

# Phase 9: Retire frozen shells and strict type residue

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Overview

Audit generated residue and add strict types to surviving PHP files after
structural churn ends. Owner phases must already have removed their shells; any
active workflow discovered here returns to its owner rather than being migrated here.

## Measured scope (2026-08-16)

181 findings: 133 `missing_strict_types`, 25 `frozen_services`,
11 `missing_route_strict_types`, 8 `frozen_controllers`, 4 `frozen_routes`.

This phase read 241 until 2026-08-16, because `MigrationDebtInventory::workPackage()`
routed every frozen-shell finding here regardless of whether the file still served
a live workflow. Phase 9 does not do business cutovers, so those findings had no
real owner. `frozenShellPhase()` now routes them out:

| Surface | Count | Now tagged |
|---|---:|---|
| `app/Http/Controllers/Api/V1/Student/*` | 8 controllers | Phase 5 |
| `app/Services/V1/Student/*` | 14 services | Phase 5 |
| `routes/api/v1/{student,lecturer}.php` | 2 route files | Phase 5 |
| Scholarship, student-scholarship, tuition-plan, voucher, financial-import controllers | 5 controllers | Phase 6 |
| Their matching `app/Services/*Service.php` | 4 services | Phase 6 |
| `routes/web/{scholarships,student-scholarships,tuition-plans,vouchers}.php` | 4 route files | Phase 6 |
| Email, notification, gold, wallet, guardian, and Admissions shells | 23 findings | Phase 7 |

The remainder is dominated by strict types, which is the correct shape for a
residue phase. Re-measure after phases 5 to 8 land rather than trusting this split;
those phases delete shells as they go.

Strict-type residue by area today: `app/Http/Controllers` 42,
`app/Http/Resources` 13, `app/Modules/Engagement` 13, `app/Http/Requests` 12,
`app/Modules/Identity` 10, `app/Console/Commands` 9, `app/Http/Middleware` 6,
`app/Services/Admissions` 6, and a tail of individual services.

## Requirements

- [x] Retag every live workflow above to its owner phase. Done in the scanner 2026-08-16.
- [ ] Prove each remaining shell dead, replaced, or intentionally rehomed before deletion.
- [ ] Keep route name, URI, method, middleware, and scheduler matrices compatible.
- [ ] Add strict types to every surviving PHP and route file.
- [ ] Empty frozen path snapshots and set all five frozen and strict categories to exact zero.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Delete or rehome the remaining frozen services |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services/AcademicRecordGenerationServiceOptimized.php` | Resolve the cross-boundary ownership move deferred from phase 4 |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Delete or rehome the remaining frozen controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Resources` | Add strict types |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Requests` | Add strict types |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Middleware` | Add strict types |
| `/Users/hunt2412/hieupvdev/project/swinx/routes` | Delete obsolete splits; strict-type permanent roots |
| `/Users/hunt2412/hieupvdev/project/swinx/app` | Add strict types to surviving files by runtime surface |
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt_paths.php` | Empty retired path sets |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Assert exact-zero frozen and strict categories |

## Interface Checklist

- No provider or container binding, route, scheduler, job, config, test, or string reference
  targets a deletion.
- Surviving commands, jobs, listeners, mail, and middleware retain scalar-call behavior
  under strict mode.
- Permanent root routes declare strict types and still load in every environment.
- No forwarding façade remains solely to preserve an old namespace.

## Dependency Map

`vertical cutovers complete → retag → reference scan → delete residue → strict-type sweep → exact-zero guards`

## Implementation Steps

1. Recompute live frozen and strict manifests and compare every item with its owner package.
2. Remove only proven dead unmounted routes, unregistered controllers, and no-caller services.
3. If residue has an active workflow or a required backfill dependency, stop and return it
   to the responsible owner phase; phase 9 does not implement business cutovers.
4. Snapshot routes and schedules and inspect providers, queues, serialized jobs, tests, and config.
5. Add `strict_types=1` by risk group: values and models, HTTP, events, jobs and listeners,
   then commands and routes.
6. Run owning tests and Pint after each group and resolve real scalar coercion defects.
7. Set frozen and strict-type rules to exact zero and empty path approvals.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Route and schedule before versus after | No unexplained public or runtime difference |
| Container resolution and queued payload | No reference to a deleted class |
| Scalar edge under a strict caller | Explicit normalization or expected TypeError handling |
| Route cache and config cache | Builds successfully |
| Inventory | Services, controllers, routes, and strict types all zero |

## Success Criteria

- [ ] `frozen_services`, `frozen_controllers`, `frozen_routes`,
  `missing_strict_types`, and `missing_route_strict_types` are exact zero.
- [ ] No live workflow was retired here instead of being cut over by its owner.
- [ ] No stale path approval or reference to a removed class remains.
- [ ] Targeted tests, route and schedule comparison, cache builds, and Pint pass.

## Risks and Security

- "No caller" searches miss reflection, queued serialization, provider, and config
  references. Require runtime snapshots and explicit searches before each deletion.
- A path-based architecture test fails open when the path moves, so a move can silence
  the guard meant to police it. Re-point those tests as part of every deletion.
- `composer dump-autoload` must run before test results are trusted after any move;
  stale classmap artifacts read as regressions.
