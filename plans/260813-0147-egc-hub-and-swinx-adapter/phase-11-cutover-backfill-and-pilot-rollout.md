---
phase: 11
title: "Cutover, Backfill, Pilot Rollout"
status: pending
priority: P1
effort: "2-3w elapsed (low code)"
dependencies: [5, 8, 9, 10]
---

# Phase 11: Cutover, Backfill, Pilot Rollout

## Overview
Bring hub live safely: pilot 1-2 Swinx schools, parallel-run against Excel for one block, verify parity, then roll remaining schools + third parties.

## Requirements
- Functional:
  - In-flight state import: current block's classes/placements/attendance-so-far from Excel into hub (one-off import script or CSV admin import on hub)
  - Parallel run: one full block where Excel process continues alongside hub; weekly parity check (attendance counts, placements) using reconciliation surface
  - Rollout runbook per school: issue key, configure adapter env, backfill roster, register webhook, verify round-trip
  - Kill-switch: adapter env flag disabling inbound materialization (falls back to manual entry) without uninstalling
- Non-functional: no school onboards without passing round-trip smoke test (enrollment→placement→attendance→score→finalize on staging)

## Related Code Files
- Create (egc-hub/): `app/Console/Commands/ImportInFlightBlockCommand.php` or admin CSV import controller
- Create (this repo): `docs/features/academic/egc-hub-integration.md` (operator + rollout runbook)
- Modify (this repo): adapter config — `EGC_HUB_SYNC_ENABLED` flag honored by webhook controller + observers

## Implementation Steps
1. Choose pilot schools with user (1 Swinx + ideally 1 third-party candidate).
2. In-flight import for pilot's current block; EGC staff validate rosters on hub UI.
3. Parallel-run block: hub is source of operations, Excel maintained as shadow; weekly parity reports.
4. Parity gate passed → decommission Excel for pilot; schedule remaining schools in waves (2-3/week).
5. Third-party onboarding via sandbox → production keys.
6. Post-rollout: retire per-school `CanvasCourseMapping` usage for EGC offerings (Swinx side) once hub owns Canvas fully.

## Success Criteria
- [ ] Pilot block completes with zero manual Excel correction into Swinx
- [ ] Parity checks green 3 consecutive weeks
- [ ] All 10+ schools onboarded; EGC staff confirm Excel retired
- [ ] Kill-switch tested on one school (disable → manual mode → re-enable → replay catches up)

## Risk Assessment
- Operator adoption failure → involve EGC staff from phase 4 UI reviews; parallel-run keeps Excel as safety net.
- In-flight data quality (Excel inconsistencies) → import with validation report, human fixes before go-live.
