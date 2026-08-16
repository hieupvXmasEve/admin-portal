---
phase: 12
title: "Phase 12: Lock exact zero and roll out production"
status: pending
priority: P0
effort: L
dependencies: [11]
---

# Phase 12: Lock exact zero and roll out production

<!-- Rescoped 2026-08-16; campus scope decided by the product owner. -->

## Overview

Qualify one immutable multi-repository release manifest, roll out the in-scope
campuses separately, then retire the controlled one-time tools and lock all
fourteen categories at exact zero. Signed dossier risk selects rollout order.

## Campus scope

Schema cleanup rolls out to Metropolia and Asia only. knu, gachon, and jinan
receive the application release like any other deploy but no cleanup migration,
per the phase 11 exclusion. "Both campuses complete rollout" in this phase means
the two in-scope campuses; the exclusion of the other three is a recorded
divergence, not an oversight, and belongs in the deployment guide.

## Requirements

- [ ] The immutable fourteen-rule and runtime-root sets are present; final closure is exact zero.
- [ ] Full checks pass for immutable Swinx, student, and lecturer SHAs and artifact hashes.
- [ ] The first in-scope campus completes and soaks before independent approval for the second.
- [ ] The three out-of-scope campuses receive the application release with cleanup
  migrations demonstrably skipped, and their divergent schema state is documented.
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
| `/Users/hunt2412/hieupvdev/project/swinx/app/Support/MigrationDebt/MigrationDebtContract.php` | Set `BASELINE_CEILINGS` to zero to match |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Lock repository-wide zero |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/system-architecture.md` | Record final owners and physical schema state |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/deployment-guide.md` | Record campus release and recovery gates, and the three-campus schema divergence |
| `/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml` | Execute the campus-separated rollout |

## Interface Checklist

- An independent audit across modules, policies, middleware, jobs, commands, providers,
  and other runtime roots proves no unapproved cross-context Eloquent.
- All APIs use canonical response and validation boundaries except documented provider protocols.
- All frontend application navigation and API calls use named routes and canonical composables.
- All production schedules, queues, outboxes, webhooks, imports, exports, and portals are observed.
- The phase 1b pull-request check is still required in branch protection at closure; the
  ratchet must not depend on a workflow that was disabled along the way.

## Dependency Map

`exact-zero release manifest → full qualification → dossier-selected campus/soak
→ independent approval → second campus/soak → tool retirement and closure`

## Implementation Steps

1. Run the inventory, shadow occurrence search, all-runtime ownership audit, and exact
   key and root coverage tests.
2. Lock the thirteen code-structure rules at exact zero in both the config and
   `BASELINE_CEILINGS`, and pin the one-time command manifest. Freeze or remove the
   ownership, path, and response exception maps. Internal `ApiResponse::compatible()` use
   is zero; provider protocols use named classes.
3. Run the full serialized backend suite, root lint, typecheck, and build, both portal
   suites, and docs checks.
4. Verify routes, schedules, caches, queues, outboxes, health, auth, campus scope,
   guardian proxy, lecturer access, DNG, notification, and representative reports,
   imports, and exports.
5. Immediately before every write or drop, require dossier freshness, exact live schema,
   migration, and artifact hashes, writer, scheduler, and queue freeze, and live zero
   consumer, exception, Finance, and Academic preflights.
6. Deploy the dossier-selected first in-scope campus; reconcile and soak using signed
   numeric thresholds for errors, locks, queues, outbox, callbacks, auth and portals, and
   data deltas.
7. Obtain independent approval, deploy the second in-scope campus, and repeat all gates.
8. Deploy the application release to knu, gachon, and jinan with cleanup migrations
   skipped, and prove by post-deploy check that no cleanup migration ran there.
9. After both soaks, delete one-time implementations from runtime source, retain retirement
   tests and signed artifacts, set `migration_commands=0` exact, update docs and issues, and
   retain historical migrations and evidence.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Any debt occurrence introduced | The pull-request check fails immediately |
| Full backend, frontend, portal, and docs suite | One terminal green result each |
| First in-scope campus smoke and reconciliation | Passes before second-campus approval |
| Health, queue, outbox, or provider threshold breach | Rollout stops; forward recovery invoked |
| Second in-scope campus smoke and reconciliation | Passes independently |
| Out-of-scope campus deploy | Application updates; zero cleanup migrations executed |
| Final repository scan | Every tracked category exactly zero |

## Success Criteria

- [ ] All debt metrics are exact zero in code, guard, and live scan.
- [ ] Both in-scope campuses complete independent rollout and soak with reconciled data.
- [ ] The three out-of-scope campuses are on the released application with their schema
  divergence recorded in the deployment guide.
- [ ] Current-state documentation is accurate and no unresolved migration issue remains.

## Risks and Security

- Do not deploy campuses as one automatic matrix. Dossiers define numeric stop,
  forward-fix, and restore branches with named owners and maximum decision times.
- After a forward-only drop, roll back only to a schema-compatible build or forward-fix;
  restore only with a tested recovery point and a callback and queue replay plan.
- Running five campuses on two different schemas is a permanent operational cost. Every
  future migration must state which schema it assumes until the divergence is closed.
