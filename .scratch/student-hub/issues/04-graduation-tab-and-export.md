# Hub: enable Graduation tab + real Export action

Status: ready-for-agent

## Parent

`.scratch/student-hub/PRD.md`

## What to build

Finish the two half-built pieces of the Hub, end-to-end.

End-to-end behavior:

- **Graduation tab:** enable the currently-commented Graduation view and show graduation requirements and progress toward the degree for the Student.
- **Export action:** make the context-bar Export quick action real — produce an academic-summary export for the Student. The dead "coming soon" toast is removed.

## Acceptance criteria

- [ ] The Graduation tab is visible and shows requirements + progress toward the degree.
- [ ] The Export action produces an academic-summary export (file download); the "coming soon" behavior is gone.
- [ ] Feature tests cover the Graduation Query data contract and the export output, following prior art GPA/summary queries and the existing Excel exports.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
