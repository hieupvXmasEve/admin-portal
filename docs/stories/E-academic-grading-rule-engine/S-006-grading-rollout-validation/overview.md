# Overview

## Current Behavior

The rule engine, scheme pack, admin UI, portal display, and recalculation
workflow are planned as separate slices. There is no final pilot validation
story proving that a Metropolia database is ready for production rollout.

## Target Behavior

The repo contains a repeatable rollout validation command and runbook. A pilot
database can be checked for scheme coverage, sample student outcomes, Canvas
boundary behavior, GPA source configuration, portal display readiness, and
operator documentation.

## Affected Users

- Academic operations staff approving rollout.
- Engineers performing deployment checks.
- School admins relying on per-database configuration.

## Affected Product Docs

- `docs/runbooks/academic-grading-rollout-validation.md`
- `docs/features/academic/grading-rule-engine.md`

## Portal Impact

Both for validation evidence. This story should not change portal code unless
S-004 evidence reveals a missed display gap.

## Non-Goals

- No new rule engine behavior.
- No mass data mutation.
- No shared-database tenant isolation.
