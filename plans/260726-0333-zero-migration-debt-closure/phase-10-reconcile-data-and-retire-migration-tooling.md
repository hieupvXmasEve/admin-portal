---
phase: 10
title: "Phase 10: Prepare data reconciliation and migration tooling"
status: pending
priority: P0
effort: XL
dependencies: [5, 6, 9]
---

# Phase 10: Prepare data reconciliation and migration tooling

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Overview

Prepare and rehearse historical Academic and Finance reconciliation using local or
isolated restored data only. Classify the seven commands by explicit lifecycle
metadata. Do not mutate production or delete rehearsal and backfill tools here.

## Measured scope (2026-08-16)

`migration_commands` is 7, unchanged since the plan was written, and the seven are
the same files the original draft named:

| Command | Disposition in the draft |
|---|---|
| `app/Console/Commands/Academic/BackfillFailureReasonCommand.php` | One-time |
| `app/Console/Commands/Academic/BackfillProgramEnrollmentsCommand.php` | One-time |
| `app/Console/Commands/Academic/MigrateStudentProgressionEventsCommand.php` | One-time |
| `app/Console/Commands/Academic/PreflightTranscriptBackfillCommand.php` | One-time |
| `app/Console/Commands/Admissions/BackfillApplicationsCommand.php` | One-time |
| `app/Console/Commands/RebuildInvoiceSnapshots.php` | Rehome as permanent Finance rebuild |
| `app/Console/Commands/ReconcileLecturerAccessEligibilityCommand.php` | Rehome as permanent Faculty sync |

The draft's "810 Academic findings" figure is a 2026-07 measurement taken before
phase 4's ownership moves and before the August subsystems landed. Treat it as
historical; the first implementation step re-measures it.

Thirty-eight migrations were added between 2026-07-28 and 2026-08-16, including
new tables for scholarship adjustment dossiers, restoration proposals, merchandise,
redemption orders, and stock movements, plus integer-ledger conversions for gold
transactions and student wallets. The reconciliation reports must cover the
schema as it stands, not as the draft described it.

## Requirements

- [ ] Re-measure the Academic disposition workload before building the workflow around it.
- [ ] Build a deterministic disposition workflow for every Academic finding found by that measurement.
- [ ] Build deterministic Finance reconciliation reports for each in-scope campus.
- [ ] Cover the August schema additions in the reconciliation reports.
- [ ] Prove backfills are dry-runnable, bounded, idempotent, restart-safe, and no-op on rerun.
- [ ] Preserve user-approved override-pass and deleted-lecturer orphan dispositions.
- [ ] Classify commands by owner, lifecycle, and behavior metadata, never by keyword renaming.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/Academic` | Register lifecycle; rehearse one-time tools |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/Admissions` | Rehearse the owner-backed applications backfill |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/RebuildInvoiceSnapshots.php` | Rehome as a permanent Finance rebuild if accepted |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/ReconcileLecturerAccessEligibilityCommand.php` | Rehome as permanent Faculty synchronization |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/console.php` | Replace legacy schedules and names |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture` | Test the lifecycle registry and terminal retirement contract |

## Interface Checklist

- Data reports distinguish an explained disposition from an unresolved exception.
- `MigrationDebtInventory` distinguishes one-time tools from permanent owned operations
  through explicit metadata, not signature substrings.
- Permanent repair, rebuild, and sync commands have owner, runbook, idempotency, and
  observability tests while retaining accurate terminology.
- Approval records the exact campus, command, selection, counts, totals, recovery, and operator.
- Backfill approval and cleanup approval are separate decisions.

## Dependency Map

`consumer zero → local/restored tooling → phase 11 fresh-dump rehearsal →
phase 12 production execution/soak → one-time tool deletion`

## Implementation Steps

1. Re-measure the Academic reconciliation workload and record the new figure with its date.
2. Verify phase 1's reconciled consumer manifest has reached zero supported readers.
3. Produce read-only Academic evidence for transcript, GPA, standing, best-attempt, EGC,
   and graduation.
4. Record explicit dispositions, including the approved pass override and the manually
   deleted lecturer.
5. Produce Finance obligation, discount, cash, credit, collectible, and pointer mapping
   reports per in-scope campus, covering the August schema additions.
6. Rehearse tools on local or currently restored data; phase 11 repeats on fresh secured dumps.
7. Rehome the invoice snapshot rebuild and lecturer access reconciliation as permanent
   operations only with explicit metadata, runbook, and tests.
8. Disable one-time tools from normal runtime registration after rehearsal, but retain
   their source, tests, and artifacts through both production rollouts and soak.
9. Make `migration_commands` count one-time lifecycle entries; terminal zero is phase 12.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Dry run | Exact rows and totals; no writes |
| Interrupted then restarted backfill | Converges without duplicate evidence |
| Immediate rerun | Zero additional writes |
| Override-pass record | Explained and preserved |
| Deleted lecturer orphan | Explained without a fabricated relationship |
| Academic and Finance local rehearsal | Deterministic report; production proof deferred |

## Success Criteria

- [ ] The Academic workload figure is re-measured and dated, not inherited from July.
- [ ] Tooling can prove retirement eligibility and exact Finance mappings without production writes.
- [ ] Permanent commands have explicit operational metadata; one-time tools remain
  available only to the controlled release process through phase 12.

## Risks and Security

- Data correction is irreversible in practice. Require backup, least-privilege operators,
  immutable evidence, bounded selection, audit logs, and separate human approvals.
- Local rehearsal against the dev database is not a substitute for restored campus data;
  the dev database is not campus-shaped.
