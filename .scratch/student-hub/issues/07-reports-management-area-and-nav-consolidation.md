# Reports management area + one-way links into the Hub + nav consolidation

Status: ready-for-agent

## Parent

`.scratch/student-hub/PRD.md`

## What to build

Separate the aggregate audits from per-student work and consolidate the scattered student navigation. Respect ADR-0007 (per-student = Hub, cross-student = Reports, one-way link).

End-to-end behavior:

- Regroup the aggregate audits/reports under a single **management nav group** for Directors/Heads, separate from the Student Hub: student-actions audit, academic-progression audit (incl. its missing-documents view), lifecycle-yearly, performance dashboard, course ranking, academic report, and the **missing-decision report** from issue 02.
- Each report row **deep-links one-way into the relevant Student's Hub tab**.
- Consolidate the previously scattered student menu items so student work is reached through the single "Student" entry, not four top-level menus.
- **No hub → report back-link** (the Hub already holds the full per-student timeline).

## Acceptance criteria

- [ ] The aggregate audits/reports are grouped in one management nav area, separate from the Student Hub.
- [ ] Each report row deep-links into the correct Student's Hub tab (one-way).
- [ ] The previously scattered student menu items are consolidated under the single "Student" entry.
- [ ] The missing-decision report lives in the management area.
- [ ] There is no hub → report back-link.
- [ ] Feature/smoke tests confirm the report → Hub link targets resolve to the right student and tab.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
- Soft (link targets): `.scratch/student-hub/issues/02-decision-model-and-missing-decision-report.md`, `.scratch/student-hub/issues/05-lifecycle-tab-unified-timeline.md`
