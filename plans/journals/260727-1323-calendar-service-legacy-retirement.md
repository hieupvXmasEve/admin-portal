---
title: "Calendar Service Legacy Retirement"
date: 2026-07-27
session: zero-migration-debt-closure-phase-four-calendar-services
status: completed
authority: work-history-only
---

# Journal: 2026-07-27 — Calendar Service Legacy Retirement

## Context

This Phase 4 cleanup retired dead Calendar delivery service owners after their
live responsibilities had moved to the Academic module.

## What Happened

- Deleted the unreachable `SemesterManagementService` and
  `AutomatedEnrollmentService` legacy shells.
- Preserved every existing route and API contract; this batch changed no
  user-facing endpoint or payload.
- Lowered the frozen services migration-debt ratchet from 68 to 66.
- Verified the focused migration-debt and architecture/cutover checks, route
  and config cache rebuild, Pint, diff check, and documentation check.

## Decisions

| Decision | Rationale |
| --- | --- |
| Retire only dead services | Avoid broad ownership moves while removing proven obsolete shells. |
| Lower only the services baseline | Keep unrelated debt visible in the guard. |

## Next

- Continue the remaining approved Phase 4 ownership and parity batches.
