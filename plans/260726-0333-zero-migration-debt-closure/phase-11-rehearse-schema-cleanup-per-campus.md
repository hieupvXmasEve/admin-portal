---
phase: 11
title: "Phase 11: Rehearse schema cleanup per campus"
status: pending
priority: P0
effort: XL
dependencies: [10]
---

# Phase 11: Rehearse schema cleanup per campus

<!-- Rescoped 2026-08-16; campus scope decided by the product owner. -->

## Campus scope

`.github/workflows/deploy.yml` deploys five production targets, not the two this
phase was written for:

| Campus | Deploy path | In scope for schema cleanup |
|---|---|---|
| metropolia | SSH | Yes |
| asia-vn | SSH | Yes |
| knu | Docker | No |
| gachon | Docker | No |
| jinan | Docker | No |

knu, gachon, and jinan were added on 2026-07-30, two days after this plan was
frozen. The product owner confirmed on 2026-08-16 that these three hold no real
production data yet, and scoped them out of schema cleanup on that basis.

They hold no data *today*. They are live deploy targets, so they can accumulate
data before this phase runs, and a forward-only drop is not reversible. The
exclusion therefore has to be re-checked at execution time and enforced in code,
not carried on the strength of an August observation:

- [ ] Re-confirm emptiness immediately before the drop rehearsal, not once at
  planning time. Query each of the three for rows in the tables and columns the
  cleanup migrations touch and record the result with a date. If any of them has
  taken on data by then, the exclusion is void and the decision returns to the
  product owner.
- [ ] The cleanup migrations carry a campus preflight that aborts on an
  out-of-scope target, so an accidental deploy cannot execute them.
- [ ] The deployment workflow gates cleanup migrations behind an explicit
  per-campus approval rather than running them for every matrix entry.

## Overview

Build independent release dossiers from fresh Asia and Metropolia backups kept in
encrypted operator-controlled storage outside the repository and workspace.
Restore-test on the production engine and rehearse the exact data and schema chain.

## Requirements

- [ ] Obtain fresh immutable backups and schema/migration status for both in-scope campuses.
- [ ] Prove the out-of-scope campuses are still data-free at execution time and cannot be
  reached by any cleanup migration.
- [ ] Prove Metropolia's older Finance state reaches canonical data before pointer drops.
- [ ] Treat `2026_07_09_120000_drop_active_source_key_from_finance_charges_table`
  as the first Finance hard stop. Verified 2026-08-16: `active_source_key` has no
  application consumer, only the two migrations that add and drop it, so the
  remaining risk is data rather than code.
- [ ] Require reader, writer, job, config, provider, and portal consumer count zero
  before each drop.
- [ ] Exercise restoration and forward-fix recovery; never rely on rollback after a drop.

## File Inventory

| Path | Action |
|---|---|
| Operator-supplied encrypted path outside workspace | Fresh campus inputs; never persist new production dumps under `backups/` |
| `/Users/hunt2412/hieupvdev/project/swinx/database/migrations` | Rehearse the additive and forward-only cleanup sequence |
| `/Users/hunt2412/hieupvdev/project/swinx/scripts/reset-local-asia-db.sh` | Preserve repeatable Asia restore and migrate checks |
| `/Users/hunt2412/hieupvdev/project/swinx/scripts/deploy-ubuntu.sh` | Add backup, preflight, health, and queue gates if approved |
| `/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml` | Separate campus approvals and migration toggles; exclude the three out-of-scope campuses from cleanup |

## Interface Checklist

- DDL is tested against the actual production MariaDB version, not assumed MySQL behavior.
- Mixed-version application and schema compatibility is proven for expand and cutover releases.
- Queues and schedulers can be paused and drained; provider callbacks are buffered or preserved.
- Every drop migration has a preflight, stop condition, post-check, and recovery build.
- Every drop migration refuses to run against an out-of-scope campus.
- Each in-scope campus has an ordered migration manifest and hash with additive, data,
  cutover, and cleanup checkpoints; any extra, missing, or reordered migration stops execution.
- Release artifacts include Swinx plus both portal SHAs and hashes and a schema
  compatibility matrix.

## Dependency Map

`fresh backup → restore test → additive schema → compatible code → backfill → consumer zero → separately approved drop rehearsal`

## Implementation Steps

1. Query knu, gachon, and jinan for rows in the tables and columns the cleanup touches;
   record the result and the date. The owner confirmed them empty on 2026-08-16, so this
   step is a re-confirmation, not a discovery. Escalate if any has since taken on data.
2. Take fresh backups of the two in-scope campuses into access-controlled storage outside
   the workspace; verify checksums, encryption, retention, restore, and maximum dossier age.
3. Restore each in-scope campus into an isolated production-version database.
4. Capture schema hash, migration batches, row counts, routes, schedules, queues, and audit baselines.
5. Inventory pending migrations by DDL and data behavior. Split create-plus-backfill work
   or prove partial-state detection and statement-boundary repair or restore.
6. Add the campus preflight guard to every cleanup migration and prove it aborts.
7. Rehearse the ordered manifest with a pause at every checkpoint. For Metropolia, require
   obligation mapping, totals, dual idempotency, and zero exceptions before the July 9
   `active_source_key` drop.
8. For every destructive change compare source and target stable IDs, expected, missing,
   and duplicate counts, and field hashes; in-migration guards are a fallback only.
9. Rehearse an actual DNG ingress strategy: compatible old app, load-balancer drain,
   measured provider retry, or durable external buffer; reconcile window events.
10. Define Redis, queue, and outbox recovery: producer pause, depths, age, payload
    compatibility, watermarks, restore behavior, and exactly-once replay.
11. Prebuild candidate, expand-compatible recovery, and forward-fix artifacts; boot and
    smoke them at each schema checkpoint and record RPO and RTO.
12. Produce signed dossiers with freshness and a numeric threshold, action, owner, and
    deadline matrix.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Restore fresh backup | Verified complete and queryable |
| Cleanup migration run against knu, gachon, or jinan | Preflight aborts before any DDL |
| Migration interrupted then resumed | Safe convergence or documented stop and restore |
| Pending migration hash differs | Immediate stop before any DDL |
| Old and new app against expand schema | Compatible during the rollout window |
| Forward-only drop failure | Stop and deploy a post-cutover-compatible forward fix |
| Queue or provider callback during pause | Durable and replayable exactly once |
| Metropolia and Asia evidence | Independent passing dossiers |

## Success Criteria

- [ ] The out-of-scope campuses are re-proven data-free for the touched columns at
  execution time, or the exclusion is escalated rather than assumed.
- [ ] Both in-scope campus restores and exact migration rehearsals pass with signed evidence.
- [ ] Every superseded column and table has zero consumers and an approved cleanup migration.
- [ ] Deployment automation enforces backup, approval, migration, health, and stop gates.
- [ ] No production data is mutated; all one-time tools and tests remain retained.

## Risks and Security

- Dumps contain production data; never place fresh copies in this repository or workspace.
  Isolate, encrypt, restrict access, and delete according to policy.
- Forward-only cleanup makes database restore the last resort, so callback recovery is mandatory.
- Excluding three production campuses from cleanup leaves them on the superseded schema
  indefinitely. That is a deliberate, owner-accepted divergence, and it must be recorded in
  the deployment guide so a future release does not silently assume one schema everywhere.
- The exclusion rests on those campuses being empty. Empty is a current state, not a
  property; they are live deploy targets and onboarding one of them turns a safe skip into
  a silent data-loss path. The preflight guard, not the plan text, is what keeps that safe.
