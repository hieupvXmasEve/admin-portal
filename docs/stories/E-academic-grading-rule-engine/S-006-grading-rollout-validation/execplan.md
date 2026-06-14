# Exec Plan

## Goal

Add final read-only rollout validation for a Metropolia pilot database.

## Scope

In scope:

- Read-only validation query.
- Rollout validation command.
- JSON report writer.
- Runbook and checklist.
- Portal verification evidence.

Out of scope:

- Data mutation.
- New grading formulas.
- Cross-database orchestration.

## Risk Classification

Risk flags:

- Audit/security
- External systems
- Public contracts
- Existing behavior
- Weak proof
- Multi-domain

Hard gates:

- Final rollout evidence for a multi-surface academic feature.

## Work Phases

1. Add report builder tests.
2. Add command tests.
3. Implement read-only report builder and command.
4. Add rollout runbook.
5. Run backend and portal validation commands.
6. Record Harness trace with report evidence.

## Stop Conditions

Pause for human confirmation if:

- Pilot database access is required but not available in the current workspace.
- A failing rollout check would need data repair instead of validation.
- Cross-school orchestration is requested.
