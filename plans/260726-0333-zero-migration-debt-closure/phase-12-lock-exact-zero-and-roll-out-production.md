---
title: "Phase 12: Lock exact zero and roll out production"
status: todo
priority: P0
effort: L
dependencies: [11]
---

# Phase 12: Lock exact zero and roll out production

## Overview

Qualify one immutable multi-repository release manifest, roll out the campuses
separately, then retire the controlled one-time tools and lock all fourteen
categories at exact zero. Signed dossier risk selects rollout order.

## Requirements

- [ ] Immutable 14-rule and runtime-root sets are present; final closure is exact zero.
- [ ] Full checks pass for immutable Swinx/student/lecturer SHAs and artifact hashes.
- [ ] First campus completes and soaks before independent approval for the second.
- [ ] Close repository issues 23 and 24 and update current-state canonical docs.

Terminal rule IDs: `shared_model_imports`, `frozen_services`,
`frozen_controllers`, `frozen_routes`, `direct_json_responses`,
`inline_request_validation`, `missing_strict_types`,
`missing_route_strict_types`, `legacy_filter_stacks`,
`literal_frontend_urls`, `legacy_page_directories`, `migration_commands`,
`cross_context_concrete_imports`, and `removed_inertia_apis`.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt.php` | Set every rule to exact zero |
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt_paths.php` | Remove retired snapshots |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Lock repository-wide zero |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/system-architecture.md` | Record final owners and physical schema state |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/deployment-guide.md` | Record campus release/recovery gates |
| `/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml` | Execute campus-separated rollout |

## Interface Checklist

- An independent audit across modules, policies, middleware, jobs, commands,
  providers, and other runtime roots proves no unapproved cross-context Eloquent.
- All APIs use canonical response/validation boundaries except documented provider protocols.
- All frontend application navigation/API calls use named routes and canonical composables.
- All production schedules, queues, outboxes, webhooks, imports/exports, and portals are observed.

## Dependency Map

`exact-zero release manifest → full qualification → dossier-selected campus/soak
→ independent approval → second campus/soak → tool retirement and closure`

## Implementation Steps

1. Run inventory, shadow occurrence search, all-runtime ownership audit, and
   exact key/root coverage tests.
2. Lock the 13 code-structure rules at exact zero and pin the one-time command
   manifest. Freeze/remove ownership/path/response exception maps. Internal
   `ApiResponse::compatible()` use is zero; provider protocols use named classes.
3. Run full serialized backend suite; root lint/typecheck/build; both portal suites; docs checks.
4. Verify routes, schedules, caches, queues, outboxes, health, auth, campus scope,
   guardian proxy, lecturer access, DNG, notification, representative reports/imports/exports.
5. Immediately before every write/drop, require dossier freshness, exact live
   schema/migration/artifact hashes, writer/scheduler/queue freeze, and live zero
   consumer/exception/Finance/Academic preflights.
6. Deploy the dossier-selected first campus; reconcile and soak using signed numeric
   thresholds for errors, locks, queues, outbox, callbacks, auth/portals, and data deltas.
7. Obtain independent approval, deploy the second campus, and repeat all gates.
8. After both soaks, delete one-time implementations from runtime source, retain
   retirement tests/signed artifacts, set `migration_commands=0` exact, update
   docs/issues, and retain historical migrations/evidence.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Any debt occurrence introduced | CI fails immediately |
| Full backend/frontend/portal/docs suite | One terminal green result each |
| First-campus smoke/reconciliation | Passes before second-campus approval |
| Health/queue/outbox/provider threshold breach | Rollout stops; forward recovery invoked |
| Second-campus smoke/reconciliation | Passes independently |
| Final repository scan | Every tracked category exactly zero |

## Success Criteria

- [ ] All debt metrics are exact zero in code, guard, and live scan.
- [ ] Both campuses complete independent rollout and soak with reconciled data.
- [ ] Current-state documentation is accurate and no unresolved migration issue remains.

## Risks and Security

- Do not deploy both campuses as one automatic matrix. Dossiers define numeric stop,
  forward-fix, and restore branches with named owners and maximum decision times.
  After a forward-only drop,
  roll back only to a schema-compatible build or forward-fix; restore only with
  a tested recovery point and callback/queue replay plan.
