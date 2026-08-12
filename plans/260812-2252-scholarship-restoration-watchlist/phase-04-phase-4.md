---
phase: 4
title: "Watchlist UI & decision loop"
status: done
priority: P1
effort: "1d"
dependencies: [2, 3]
---

# Phase 4: Watchlist UI & decision loop

## Overview

Staff report page listing carried adjustments with academic evidence and
inline restoration decisions. Copies the defer-return watchlist pattern
end-to-end (query → controller → Inertia Vue page → sidebar → Excel export).

## Requirements

- Functional: filterable table (semester, verdict, proposal state, search);
  columns: student, target semester, original→adjusted (→effective) amount,
  verdict badge **with evaluated-semester label** (progressive verdict —
  validation decision), semester/cumulative GPA, attendance % (min across courses +
  expandable per-course detail with current units), proposal status badge;
  row actions: Propose restore (dialog: reason + optional partial amount with
  live bounds), Approve / Reject (confirm dialog with reason), link "Giảm tiếp"
  → existing adjustment-dossier create flow prefilled with student, link to
  student profile + dossier Show; Excel export honoring active filters.
- Non-functional: shadcn components (Select/DataPagination per rebuilt
  scholarship UI convention), toast + InputError on every submit, labels via
  `dossier-labels.ts` style map, `lang/vn` strings.

## Architecture

Page = Academic Progression surface; decision POSTs target Finance routes from
Phase 2 (cross-module HTTP is fine — module boundary applies to PHP imports,
not URLs). Verdict/GPA/attendance are display-only from Phase 3 query.

## Related Code Files

- Create: `app/Modules/Academic/Progression/Http/Web/ScholarshipRestorationWatchlistController.php` (`index` + `export`)
- Modify: `app/Modules/Academic/routes/web.php` — GET
  `reports/scholarship-restorations` (+ `/export`), gate `can:view_student_action`
  (same as sibling Academic reports group — decision actions themselves remain
  gated by `approve_scholarship_adjustment` on the Finance endpoints)
- Create: `resources/js/pages/Admin/Reports/ScholarshipRestorationWatchlist/Index.vue`
  (copy structure from `resources/js/pages/Admin/Reports/AcademicProgressionAudit/DeferReturns.vue`)
- Create: dialog components colocated under the same page dir (propose/approve/reject)
  only if `Index.vue` exceeds repo size norms; otherwise inline
- Modify: `resources/js/constants/menu-sidebar.ts` (~:284-289, beside Defer
  Return Watchlist) — entry in Academic reports group
- Modify: `resources/js/utils/routes.ts` (~:250-251, beside
  `academicProgressionDeferReturns` helpers)
- Create: `app/Modules/Academic/Progression/Exports/ScholarshipRestorationWatchlistExport.php`
  (beside `DeferReturnWatchlistExport.php`)
- Tests: route test (copy defer-return route test), controller feature test
  (filters render, export streams), Phase 2 endpoints already tested

## Implementation Steps

1. Re-read defer-return watchlist commits (1fe4f98f, f07f5740) for the exact
   file set; mirror naming.
2. Controller + routes + export.
3. Vue page: table, filters, badges, expandable attendance detail, dialogs.
4. Partial-amount dialog: show original/adjusted bounds from row data;
   client hint only — server FormRequest is the authority (never mirror the
   clamp math in JS — repo rule).
5. Sidebar + routes.ts.
6. Tests + eslint per-file (whole-project type-check OOMs in dev container —
   use per-file eslint).
7. Browser walkthrough via dev server; screenshot key states.

## Todo

- [x] Controller + routes + export
- [x] Index.vue with filters/badges/dialogs
- [x] Sidebar + routes.ts entries
- [x] Route/feature tests green
- [ ] Browser walkthrough done — not run (no interactive browser in this environment); verified via passing HTTP/Inertia tests + per-file eslint instead

## Success Criteria

- [x] Staff completes full loop on one screen: see evidence → propose partial →
      approve → row reflects new state
- [ ] "Giảm tiếp" lands on dossier-create flow with student preselected — scoped down: links to the dossier list only, no prefill (no query-string prefill contract exists on that page today); see plan.md Known Gaps
- [x] Export matches filtered rows

## Risk Assessment

- Page bloat: keep `Index.vue` within repo file-size norms; split dialogs when
  it grows.
- Attendance detail volume: lazy-load per-row expansion if initial payload
  is heavy (follow what Phase 3 query returns; don't over-fetch).
