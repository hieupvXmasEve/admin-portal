---
title: "Phase 11: Rehearse schema cleanup per campus"
status: todo
priority: P0
effort: XL
dependencies: [10]
---

# Phase 11: Rehearse schema cleanup per campus

## Overview

Build independent release dossiers from fresh Asia and Metropolia backups kept
in encrypted operator-controlled storage outside the repository/workspace.
Restore-test on the production engine and rehearse the exact data/schema chain.

## Requirements

- [ ] Obtain fresh immutable backups and schema/migration status for both campuses.
- [ ] Prove Metropolia's older Finance state reaches canonical data before pointer drops.
- [ ] Treat `2026_07_09_120000_drop_active_source_key_from_finance_charges_table`
  as the first Finance hard stop.
- [ ] Require reader/writer/job/config/provider/portal consumer count zero before each drop.
- [ ] Exercise restoration and forward-fix recovery; never rely on rollback after a drop.

## File Inventory

| Path | Action |
|---|---|
| Operator-supplied encrypted path outside workspace | Fresh campus inputs; never persist new production dumps under `backups/` |
| `/Users/hunt2412/hieupvdev/project/swinx/database/migrations` | Rehearse additive and forward-only cleanup sequence |
| `/Users/hunt2412/hieupvdev/project/swinx/scripts/reset-local-asia-db.sh` | Preserve repeatable Asia restore/migrate checks |
| `/Users/hunt2412/hieupvdev/project/swinx/scripts/deploy-ubuntu.sh` | Add backup/preflight/health/queue gates if approved |
| `/Users/hunt2412/hieupvdev/project/swinx/.github/workflows/deploy.yml` | Separate campus approvals and migration toggles |

## Interface Checklist

- DDL is tested against the actual production MariaDB version, not assumed MySQL behavior.
- Mixed-version application/schema compatibility is proven for expand and cutover releases.
- Queue/scheduler can be paused/drained; provider callbacks are buffered or preserved.
- Every drop migration has a preflight, stop condition, post-check, and recovery build.
- Each campus has an ordered migration manifest/hash with additive, data, cutover,
  and cleanup checkpoints; any extra/missing/reordered migration stops execution.
- Release artifacts include Swinx plus both portal SHAs/hashes and a schema compatibility matrix.

## Dependency Map

`fresh backup → restore test → additive schema → compatible code → backfill → consumer zero → separately approved drop rehearsal`

## Implementation Steps

1. Take fresh backups into access-controlled storage outside the workspace; verify
   checksums, encryption, retention, restore, and maximum dossier age.
2. Restore each campus into an isolated production-version database.
3. Capture schema hash, migration batches, row counts, routes/schedules, queues, and audit baselines.
4. Inventory pending migrations by DDL/data behavior. Split create-plus-backfill work
   or prove partial-state detection and statement-boundary repair/restore.
5. Rehearse the ordered manifest with a pause at every checkpoint. For Metropolia,
   require obligation mapping, totals, dual idempotency, and zero exceptions before
   the July 9 `active_source_key` drop.
6. For every destructive change compare source/target stable IDs, expected/missing/
   duplicate counts, and field hashes; in-migration guards are fallback only.
7. Rehearse an actual DNG ingress strategy: compatible old app, load-balancer drain,
   measured provider retry, or durable external buffer; reconcile window events.
8. Define Redis/queue/outbox recovery: producer pause, depths/age/payload compatibility,
   watermarks, restore behavior, and exactly-once replay.
9. Prebuild candidate, expand-compatible recovery, and forward-fix artifacts; boot/smoke
   them at each schema checkpoint and record RPO/RTO.
10. Produce signed dossiers with freshness and numeric threshold/action/owner/deadline matrix.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Restore fresh backup | Verified complete and queryable |
| Migration interrupted then resumed | Safe convergence or documented stop/restore |
| Pending migration hash differs | Immediate stop before any DDL |
| Old/new app against expand schema | Compatible during rollout window |
| Forward-only drop failure | Stop and deploy post-cutover-compatible forward fix |
| Queue/provider callback during pause | Durable and replayable exactly once |
| Metropolia/Asia evidence | Independent passing dossiers |

## Success Criteria

- [ ] Both campus restores and exact migration rehearsals pass with signed evidence.
- [ ] Every superseded column/table has zero consumers and an approved cleanup migration.
- [ ] Deployment automation enforces backup, approval, migration, health, and stop gates.
- [ ] No production data is mutated; all one-time tools/tests remain retained.

## Risks and Security

- Dumps contain production data; never place fresh copies in this repository/workspace.
  Isolate, encrypt, restrict access, and delete according to policy.
  Forward-only cleanup makes database restore the last resort, so callback recovery is mandatory.
