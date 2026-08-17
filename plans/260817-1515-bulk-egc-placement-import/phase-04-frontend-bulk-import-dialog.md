---
phase: 4
title: "Frontend bulk import dialog"
status: done
priority: P2
effort: "4h"
dependencies: [3]
---

# Phase 4: Frontend bulk import dialog

## Overview

Give staff a "Bulk Import" entry point on the Placement Worklist page:
upload CSV → preview table (valid/skip/error counts + per-row reasons) →
confirm → execute → result summary. Styled after
`resources/js/Pages/Admin/Reports/StudentActionsImport.vue`, but as a Dialog
(like `ClassifyStudentDialog.vue`) instead of a standalone page, since this
plan's routes are POST-only actions on the worklist page rather than a
separate Inertia page.

## Requirements

- Functional:
  - Trigger: new "Bulk Import" `Button` next to the existing search/filter
    row in `Index.vue`.
  - File picker accepts `.csv` (and `.xlsx`/`.xls` for parity with the
    backend's mime rule).
  - Preview step shows: total rows, valid count, per-skip-reason counts
    (`student_id_missing`, `student_not_found`, `level_missing`,
    `level_excluded_gcs`, `level_unmapped`), and a scrollable table of rows
    with `student_id`, `full_name` (when resolved), `level_raw`, `status`,
    `reason`.
  - Execute step disabled until a successful preview; re-uses the same file
    + `preview_token`, matching `StudentActionsImport.vue`'s flow.
  - Success/failure toasts via `vue-sonner` (already used in
    `StudentActionsImport.vue`).
  - On successful execute, close the dialog and reload the worklist list
    (`router.reload({ only: ['students'] })`) so placed students drop off
    the unclassified list immediately.
- Non-functional: reuse `FileUpload.vue` component already used by
  `StudentActionsImport.vue`; no new upload primitive.

## Related Code Files

- Create: `resources/js/Pages/Academic/PlacementWorklist/BulkImportEgcPlacementDialog.vue`
- Modify: `resources/js/Pages/Academic/PlacementWorklist/Index.vue` (add
  trigger button + dialog mount, alongside the existing
  `ClassifyStudentDialog` wiring)
- Reference (pattern source, no changes): `resources/js/Pages/Admin/Reports/StudentActionsImport.vue`

## Implementation Steps

1. `BulkImportEgcPlacementDialog.vue` props: none required beyond an
   `open`/`close` emit, matching `ClassifyStudentDialog`'s
   `@close="classifyTarget = null"` convention. Internal state:
   `file`, `previewToken`, `previewResult`, `isUploading`, `isExecuting`.

2. Preview call: `POST route('students.placement-worklist.bulk-import.preview')`
   with `FormData` (`file`), same `axios`/fetch pattern as
   `StudentActionsImport.vue`'s `runPreview()`. `ziggy-js`'s `route()`
   already exposes every named Laravel route automatically — confirmed via
   validate interview (verification pass found no manual registration step
   anywhere else in this codebase), so Phase 3's route names need no
   companion frontend wiring.

3. Execute call: `POST route('students.placement-worklist.bulk-import.execute')`
   with `FormData` (`file`, `preview_token`).

4. Render preview table: valid rows in default styling, `level_excluded_gcs`
   / `level_unmapped` / `student_not_found` rows visually de-emphasized
   (muted text, per this repo's design-quality rule against flat
   undifferentiated rows) with a `Badge` showing the skip reason.

5. In `Index.vue`: add
   `const bulkImportOpen = ref(false);` and a `Button` in the header row
   (`variant="outline"`, `Upload` icon from `lucide-vue-next`, already
   imported pattern in `StudentActionsImport.vue`) that sets it `true`;
   mount `<BulkImportEgcPlacementDialog v-if="bulkImportOpen" @close="bulkImportOpen = false" />`.

## Success Criteria

- [x] Uploading the real `student_egc.csv` in the dialog shows the same
      counts Phase 2/3 validated server-side.
- [x] Execute reloads only the `students` prop, not a full page navigation.
- [x] Dialog closes cleanly on both success and cancel without leaving a
      stale `preview_token` usable after close (drop local state on close).
- [x] Visual states for valid/skip/error rows are distinguishable at a
      glance (not just a text label) — satisfies this repo's UI review
      checklist for designed states, not raw table dumps.

## Risk Assessment

405-row preview table rendered inline could feel heavy; if performance is an
issue in testing, paginate or virtualize the preview table client-side
before shipping — not expected to matter at this row count based on
`StudentActionsImport.vue` already handling up to 1000 rows without special
virtualization.
