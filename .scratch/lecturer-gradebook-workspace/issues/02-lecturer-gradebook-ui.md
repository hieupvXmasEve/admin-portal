# 02 - Lecturer Gradebook UI

Status: done
Portal impact: lecturer

## Goal

Replace the split `Assessments` + `Grades` tabs with a single `Gradebook` workspace in the lecturer portal.

## Requirements

- Top-level course tabs include `Gradebook` only; remove top-level `Assessments` and `Grades`.
- `Gradebook` defaults to `To Grade`.
- Use query state `?tab=gradebook&view=to-grade|matrix`.
- No backward query compatibility for `assessments` or `grades`.
- `To Grade` is a compact Gradable Items list, not large cards:
  - Assessment detail
  - Component/type
  - Max points
  - Weight
  - Graded / total
  - Pending
  - Average
  - Action: `Grade`
- `Matrix` uses the new Gradebook payload and supports inline point editing for detail score cells.
- Editable cells are only assessment detail scores.
- Component totals/final grades/pass-fail are read-only.
- Dirty cells are highlighted.
- Sticky save bar appears when there are unsaved changes.
- `Save changes` groups dirty cells into the new save endpoint payload.
- Save success auto-refreshes the Gradebook payload and clears dirty state.
- Save failure keeps dirty state and shows cell-level errors plus a short toast.
- Do not show Export in this phase.
- Keep English labels.

## Done

- Lecturer portal types/composable updated for new Gradebook API.
- Course detail page renders one `Gradebook` tab.
- `To Grade` and `Matrix` are usable from the new workspace.
- Inline save works against the new backend endpoint.
