---
title: "Calendar Delivery Legacy Cleanup"
date: 2026-07-27
session: zero-migration-debt-closure-phase-four-calendar-delivery
status: completed
authority: work-history-only
---

# Journal: 2026-07-27 — Calendar Delivery Legacy Cleanup

## Context

This Phase 4 batch closed obsolete Calendar and Course Registration delivery
owners as part of the zero-migration-debt migration. This journal is a
chronological record only; current source, contracts, tests, and canonical
documentation remain authoritative.

## What Happened

- Removed the dead legacy `RegistrationService` and unregistered legacy API
  Course and Registration controllers.
- Preserved all route and API contracts; this cleanup removed unreachable
  owners rather than changing behavior.
- Lowered only the frozen migration-debt baselines: services 69 to 68,
  controllers 50 to 48, and direct JSON 36 to 34.
- Targeted validation passed.

## Decisions

| Decision | Rationale |
| --- | --- |
| Remove only dead legacy owners | Keep the batch scoped while retaining live behavior and contracts. |
| Change only affected frozen baselines | Avoid masking unrelated migration debt. |

## Next

- Continue the remaining approved Phase 4 ownership and legacy-cleanup work.
