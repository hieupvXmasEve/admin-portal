---
phase: 3
title: "Frontend bulk major import dialog"
status: pending
priority: P2
effort: "3h"
dependencies: [2]
---

# Phase 3: Frontend bulk major import dialog

## Overview

Second "Bulk Import Major" entry point on the Placement Worklist page,
alongside the existing "Bulk Import" (EGC) button. Same upload → preview →
confirm → execute → summary flow as `BulkImportEgcPlacementDialog.vue`, with
a preview table shaped around this feature's fields (score + skip reason)
instead of EGC's (level + skip reason).

<!-- Updated: Validation Session 1 - dropped the predicted-outcome/EGC-fallback
badge concept entirely; below-threshold rows are just another skip reason
(below_threshold_excluded) shown with the score, same as the other 3 skip
reasons. Every row that executes lands in Major, period. -->


## Requirements

- Functional:
  - Trigger: second `Button` next to the existing "Bulk Import" button in
    `Index.vue`'s header row.
  - File picker accepts `.csv`/`.txt`/`.xlsx`/`.xls`, same as the EGC
    dialog's mime rule.
  - Preview step shows: total rows, valid count, per-skip-reason counts
    (`student_id_missing`, `student_not_found`, `application_score_missing`,
    `below_threshold_excluded`), and a scrollable table of rows with
    `student_id`, `full_name`, `overall_score`, `status`, `reason`.
  - Execute step disabled until a successful preview; reuses the same file
    + `preview_token`, matching the EGC dialog's flow.
  - **`handleExecute()` must check `result.global_errors` before toasting
    success** — write this correctly from the start. (The EGC dialog
    shipped without this check, and its code review caught a real bug: an
    unconfigured-intake execute toasted "Import done: 0 placed" as if
    successful while a contradictory error `Alert` also rendered. Mirror
    `handlePreview()`'s existing `global_errors` check in `handleExecute()`
    from commit one here.)
  - Success/failure toasts via `vue-sonner`.
  - On successful execute, close the dialog and reload the worklist list
    (`router.reload({ only: ['students'] })`) so placed students drop off
    the unclassified list immediately.
- Non-functional: raw `<Input type="file">` is acceptable here too (the EGC
  dialog's accepted deviation from reusing `FileUpload.vue` — simpler for a
  single-file, non-drag-drop case; not re-litigating that call here).

## Related Code Files

- Create: `resources/js/pages/Academic/PlacementWorklist/BulkImportMajorPlacementDialog.vue`
- Modify: `resources/js/pages/Academic/PlacementWorklist/Index.vue` (add a
  second trigger button + dialog mount, alongside the existing
  `BulkImportEgcPlacementDialog` wiring)
- Reference (pattern source, no changes): `resources/js/pages/Academic/PlacementWorklist/BulkImportEgcPlacementDialog.vue`

## Implementation Steps

1. `BulkImportMajorPlacementDialog.vue` — same internal state shape as
   `BulkImportEgcPlacementDialog.vue` (`file`, `previewToken`,
   `previewResult`, `isPreviewing`, `isExecuting`), same `open`/`close` emit
   convention.

2. Preview call: `POST route('students.placement-worklist.bulk-import-major.preview')`
   with `FormData` (`file`) — `route()` from `ziggy-js` auto-exposes the new
   named routes from Phase 2, no manual frontend route registration needed
   (confirmed pattern from the EGC plan's validation interview).

3. Execute call: `POST route('students.placement-worklist.bulk-import-major.execute')`
   with `FormData` (`file`, `preview_token`).

4. Render preview table: valid rows in default styling, skip rows muted
   with a reason `Badge` (including `below_threshold_excluded` showing the
   actual score so staff can see it was close-but-not-enough) — same
   visual-differentiation requirement the EGC dialog already satisfies, one
   fewer state to design for than originally planned (no separate
   "predicted EGC fallback" state — that outcome never reaches execute).

5. In `Index.vue`: add `const bulkImportMajorOpen = ref(false);` and a
   second `Button` next to the existing Bulk Import button; mount
   `<BulkImportMajorPlacementDialog v-if="bulkImportMajorOpen" @close="bulkImportMajorOpen = false" />`.

## Success Criteria

- [ ] Preview table shows `overall_score` per row, not just valid/skip.
- [ ] A `below_threshold_excluded` row shows its actual score in the reason
      column, not just the code name.
- [ ] Execute reloads only the `students` prop, not a full page navigation.
- [ ] An unconfigured-intake execute shows the error state (toast +
      `Alert`), never a contradictory "Import done" success toast.
- [ ] Dialog closes cleanly on both success and cancel without leaving a
      stale `preview_token` usable after close.

## Risk Assessment

None specific to this phase — the EGC-fallback-surprise risk that motivated
the original `predicted_outcome` badge design is now moot: below-threshold
rows never execute through this importer at all (excluded in Phase 1), so
there is no fallback outcome for staff to be surprised by.
