# Per-session attendance status visible in cockpit sessions view

Status: ready-for-human (implemented 2026-07-03)

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

In the cockpit's sessions view, show each session's attendance status: recorded, not recorded, or auto-system only. This reuses the same underlying data the operational-state read model already derives (unmarked sessions, auto-system-only sessions) so the visible status is guaranteed consistent with the readiness blockers shown elsewhere in the cockpit — do not introduce a second, independently-computed notion of attendance status.

This slice is read-only: it makes attendance readiness visible per session but does not yet let staff record attendance from the cockpit (that's the next slice).

## Acceptance criteria

- [x] Each session row/card in the cockpit sessions view shows one of: recorded, not recorded, auto-system only.
- [x] The per-session status is derived from the same attendance data source as the readiness blockers in the `operational_state` prop (no duplicate computation logic).
- [x] Status updates correctly across the scenarios already covered by the operational-state tests (no attendance, auto-system-only, fully recorded).
- [x] No new write path is introduced in this slice.
- [x] Pest HTTP feature test asserts the cockpit page response includes the correct per-session attendance status for each scenario.

## Blocked by

- Operational state read model + cockpit displays lifecycle, blockers, and gated Finalize button (display only)
