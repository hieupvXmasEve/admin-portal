# Exec Plan - S-001

## Goal

Make room booking practical for multi-day operational use without losing conflict safety or creating partial booking sets.

## Scope

In scope:

- Multi-day booking generation for one room.
- Same-time range booking across selected dates.
- Editable per-date time overrides.
- Full-set conflict preview and submit validation.
- All-or-nothing creation; one conflict blocks the full generated set.
- Per-occurrence approval/status using the existing room booking workflow.
- Clone existing booking into a safe draft.
- Availability board for rooms, dates, time windows, buildings, bookings, and class sessions.
- Scoped migration of room booking web routes/controllers from legacy top-level paths into a module-owned structure.

Out of scope:

- Student/lecturer portal changes.
- Cross-room booking plans.
- Waitlists and automatic alternative room suggestions.
- Editing or cancelling a whole series as one operation.
- Full recurrence engine with open-ended schedules.

## Risk Classification

Risk flags:

- Data model.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- None identified, but rollback/all-or-nothing validation is required.

Lane:

- high-risk

## Work Phases

1. Discovery: inspect route order, existing room booking pages, existing flash/form patterns, factories, and permission behavior.
2. Characterization: add focused tests for current single-day conflict behavior before changing creation logic.
3. Backend design: add action/query classes for series creation, availability preview, clone draft, and availability board.
4. Request contract: extend create validation to support `schedule_mode` and `occurrences` without breaking existing single-day submissions.
5. UI planner: refactor create page to Inertia `useForm`, `DatePicker`, `TimePicker`, route helpers, and preview state.
6. Clone: add safe clone entry from show/index to create draft.
7. Availability board: add operational table/timeline with filters and conflict/free windows.
8. Verification: run targeted backend tests, frontend lint/type checks, formatter checks, and browser smoke screenshots if the local app is reachable.
9. Harness update: trace actions, update story evidence, and add backlog items for any deferred series-level edit/cancel behavior.

## Stop Conditions

Pause for human confirmation if:

- Product chooses partial creation instead of all-or-nothing series creation.
- A new series-level database table is required.
- Existing route ordering forces a larger module migration than planned.
- Validation proof must be weakened because local Docker or frontend checks cannot run.
- Approval semantics need to change from occurrence-level to series-level approval.
