---
title: "Phase 10: Prepare data reconciliation and migration tooling"
status: todo
priority: P0
effort: XL
dependencies: [5, 6, 9]
---

# Phase 10: Prepare data reconciliation and migration tooling

## Overview

Prepare and rehearse historical Academic/Finance reconciliation using local or
isolated restored data only. Classify seven commands by explicit lifecycle
metadata. Do not mutate production or delete rehearsal/backfill tools here.

## Requirements

- [ ] Build a deterministic disposition workflow for all 810 Academic findings.
- [ ] Build deterministic Finance reconciliation reports for each campus.
- [ ] Prove backfills are dry-runnable, bounded, idempotent, restart-safe, and no-op on rerun.
- [ ] Preserve user-approved override-pass and deleted-lecturer orphan dispositions.
- [ ] Classify commands by owner/lifecycle/behavior metadata, never keyword renaming.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/Academic` | Register lifecycle; rehearse one-time tools |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/Admissions` | Rehearse owner-backed applications backfill |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/RebuildInvoiceSnapshots.php` | Rehome as permanent Finance rebuild if accepted |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/ReconcileLecturerAccessEligibilityCommand.php` | Rehome as permanent Faculty synchronization |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/console.php` | Replace legacy schedules and names |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Test lifecycle registry and terminal retirement contract |

## Interface Checklist

- Data reports distinguish explained disposition from unresolved exception.
- `MigrationDebtInventory` distinguishes one-time tools from permanent owned
  operations through explicit metadata, not signature substrings.
- Permanent repair/rebuild/sync commands have owner, runbook, idempotency, and
  observability tests while retaining accurate terminology.
- Approval records exact campus, command, selection, counts, totals, recovery, and operator.
- Backfill approval and cleanup approval are separate decisions.

## Dependency Map

`consumer zero → local/restored tooling → phase 11 fresh-dump rehearsal →
phase 12 production execution/soak → one-time tool deletion`

## Implementation Steps

1. Verify phase 1's reconciled consumer manifest has reached zero supported readers.
2. Produce read-only Academic evidence for transcript/GPA/standing/best-attempt/EGC/graduation.
3. Record explicit dispositions, including approved pass override and manually deleted lecturer.
4. Produce Finance obligation/discount/cash/credit/collectible and pointer mapping reports per campus.
5. Rehearse tools on local/current restored data; phase 11 repeats on fresh secured dumps.
6. Rehome invoice snapshot rebuild and lecturer access reconciliation as permanent
   operations only with explicit metadata/runbook/tests.
7. Disable one-time tools from normal runtime registration after rehearsal, but retain
   their source, tests, and artifacts through both production rollouts and soak.
8. Make `migration_commands` count one-time lifecycle entries; terminal zero is phase 12.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Dry run | Exact rows/totals; no writes |
| Interrupted/restarted backfill | Converges without duplicate evidence |
| Immediate rerun | Zero additional writes |
| Override-pass record | Explained and preserved |
| Deleted lecturer orphan | Explained without fabricated relationship |
| Academic/Finance local rehearsal | Deterministic report; production proof deferred |

## Success Criteria

- [ ] Tooling can prove retirement eligibility and exact Finance mappings without production writes.
- [ ] Permanent commands have explicit operational metadata; one-time tools remain
  available only to the controlled release process through phase 12.

## Risks and Security

- Data correction is irreversible in practice. Require backup, least-privilege operators,
  immutable evidence, bounded selection, audit logs, and separate human approvals.
