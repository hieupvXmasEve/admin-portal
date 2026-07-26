---
title: "Phase 9: Retire frozen shells and strict type residue"
status: todo
priority: P1
effort: L
dependencies: [4, 5, 6, 7, 8]
---

# Phase 9: Retire frozen shells and strict type residue

## Overview

Audit generated residue and add strict types to surviving PHP files after
structural churn ends. Owner phases must already have removed their shells; any
active workflow discovered here returns to its owner instead of being migrated here.

## Requirements

- [ ] Prove each shell dead, replaced, or intentionally rehomed before deletion.
- [ ] Keep route name/URI/method/middleware and scheduler matrices compatible.
- [ ] Add strict types to every surviving PHP and route file.
- [ ] Empty frozen path snapshots and set all five frozen/strict categories to exact zero.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Delete/rehome all remaining frozen services |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Delete/rehome all remaining frozen controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/routes` | Delete obsolete splits; strict-type permanent roots |
| `/Users/hunt2412/hieupvdev/project/swinx/app` | Add strict types to surviving files by runtime surface |
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt_paths.php` | Empty retired path sets |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Assert exact-zero frozen/strict categories |

## Interface Checklist

- No provider/container binding, route, scheduler, job, config, test, or string reference targets a deletion.
- Surviving commands/jobs/listeners/mail/middleware retain scalar-call behavior under strict mode.
- Permanent root routes declare strict types and still load in every environment.
- No forwarding façade remains solely to preserve an old namespace.

## Dependency Map

`vertical cutovers complete → reference scan → delete residue → strict-type sweep → exact-zero guards`

## Implementation Steps

1. Recompute live frozen/strict manifests and compare every item with its owner package.
2. Remove only proven dead unmounted routes, unregistered controllers, and no-caller services.
3. If residue has an active workflow or required backfill dependency, stop and return
   it to the responsible owner phase; Phase 9 does not implement business cutovers.
4. Snapshot routes/schedules and inspect providers, queues, serialized jobs, tests, and config.
5. Add `strict_types=1` by risk group: values/models, HTTP, events/jobs/listeners, commands/routes.
6. Run owning tests/Pint after each group and resolve real scalar coercion defects.
7. Set frozen and strict-type rules to exact zero and empty path approvals.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Route/schedule before versus after | No unexplained public/runtime difference |
| Container resolution/queued payload | No reference to deleted class |
| Scalar edge under strict caller | Explicit normalization or expected TypeError handling |
| Route cache/config cache | Builds successfully |
| Inventory | services/controllers/routes/strict types all zero |

## Success Criteria

- [ ] `frozen_services`, `frozen_controllers`, `frozen_routes`,
  `missing_strict_types`, and `missing_route_strict_types` are exact zero.
- [ ] No stale path approval or reference to a removed class remains.
- [ ] Targeted tests, route/schedule comparison, cache builds, and Pint pass.

## Risks and Security

- “No caller” searches miss reflection, queued serialization, provider, and config references.
  Require runtime snapshots and explicit searches before each deletion.
