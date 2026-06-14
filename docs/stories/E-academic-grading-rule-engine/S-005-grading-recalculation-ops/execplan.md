# Exec Plan

## Goal

Add a safe operational recalculation workflow for existing academic records.

## Scope

In scope:

- Dry-run preview command.
- Confirmed commit command.
- Snapshot JSON reports.
- Runbook.

Out of scope:

- Admin UI trigger.
- Background job orchestration.
- Portal contract changes.

## Risk Classification

Risk flags:

- Data model
- Audit/security
- Existing behavior
- Weak proof
- Multi-domain

Hard gates:

- Mutates finalized academic records in commit mode.

## Work Phases

1. Add dry-run diff tests.
2. Add recalculation action.
3. Add command filter and confirmation tests.
4. Add snapshot writer.
5. Add runbook.
6. Run targeted verification.
7. Record Harness trace.

## Stop Conditions

Pause for human confirmation if:

- Operators request all-database recalculation without filters.
- A rollback command becomes a requirement.
- Recalculation changes credit or GPA behavior outside selected records.
