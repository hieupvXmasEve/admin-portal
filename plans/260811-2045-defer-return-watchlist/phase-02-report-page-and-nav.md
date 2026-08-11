---
phase: 2
title: "Report Page And Nav"
status: done
priority: P1
effort: "3h"
dependencies: [1]
---

# Phase 2: Report Page And Nav

## Overview

Inertia page rendering the watchlist with bucket tabs, count band, and deep links
into each student's lifecycle tab. Plus route helper and sidebar entry so đào tạo
can actually find it.

## Requirements

**Functional**
- Count band at top: overdue / upcoming / waiting, overdue styled as the alarm.
- Bucket tabs filtering server-side (round-trip, not client filtering) — pagination
  and counts must stay correct.
- Table columns: Student (name + code), Campus, Action type, Return/anchor semester,
  Date, Days overdue-or-until, Status badge.
- **One explicit action button per row** linking to the student's lifecycle tab.
  The `<tr>` itself is **not** clickable — this deliberately diverges from
  `MissingDecisions.vue:151`, which puts `@click` on the row. Copy that page's layout,
  not its row-click behavior.
- An Export button hitting the Phase 1 export route, carrying the active filters.
- Empty state per bucket, phrased as good news for `overdue` ("nobody overdue").

**Non-functional**
- Reuse existing shadcn primitives already used by the sibling report; no new
  component library, no new design language.
- `DataPagination` bound with `:pagination-data` exactly as the sibling page —
  a prop-name mismatch here previously caused a page crash on Fee Monitor.
- Search input debounced via `useTableFilters`, same as sibling.

## Architecture

Copy the structure of
`resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue`:
`useTableFilters(routeHelper, props.filters, ['rows', 'counts', 'filters'])` drives
search / pagination / page-size, and the bucket tab sets the `bucket` filter field
through the same composable so every control uses one mechanism.

Days column formatting:
- `overdue` → "quá hạn N ngày" with `semester_ended` rendered as a destructive badge,
  `in_semester` as a warning badge.
- `upcoming` → "còn N ngày"
- `waiting` → "đang chờ N ngày"
- null anchor date → `—`

## Related Code Files

- Create: `resources/js/pages/Admin/Reports/AcademicProgressionAudit/DeferReturns.vue`
- Modify: `resources/js/utils/routes.ts` — add `academicProgressionDeferReturns` beside line 249
- Modify: `resources/js/constants/menu-sidebar.ts` — add entry beside line 280 (same group as Missing Decisions)

Read before writing:
- `resources/js/pages/Admin/Reports/AcademicProgressionAudit/MissingDecisions.vue` (whole file — layout, imports, `DataPagination` binding at line 177)
- `resources/js/composables/useFilters.ts` (`useTableFilters` signature)
- `resources/js/constants/menu-sidebar.ts:270-290` (group + permission gating shape)

## Implementation Steps

1. Add the route helper in `routes.ts`.
2. Build `DeferReturns.vue`: `Head`, header with title + total badge + Export button,
   count band, bucket tabs, search card, results table with a per-row action button,
   `DataPagination`. No `@click` on `<tr>`, no `cursor-pointer` on rows.
3. Type the props from the Phase 1 payload — declare a local `DeferReturnRow`
   interface mirroring the query's projected columns; do not widen to `any`.
4. Add the sidebar entry with the same permission gate as the sibling reports.
5. Verify in the browser against dev data: expect ~49 defer rows and 6 waiting rows
   before campus scoping; confirm the campus switch changes counts.
6. Lint the touched files individually (`eslint <file>`); do not run the whole-project
   `vue-tsc` type-check — it OOMs inside `swinx-app-dev`.

## Success Criteria

- [x] Page renders all three buckets with correct counts (route test covers
      the props; **not verified against live dev data in the browser** — this
      dev instance only offers Google OAuth login, no local dev-login bypass
      exists in the repo, so an authenticated click-through wasn't possible)
- [x] Bucket tab + search + pagination compose without losing each other's
      state (no `page` field in the filters interface, matching the sibling
      page — a bucket/search change carries no stale `page` param)
- [x] Row action button lands on the correct student's lifecycle tab; `<tr>`
      carries no `@click`/`cursor-pointer`, single button navigates
- [x] Export button downloads a file matching the on-screen filter
- [x] Overdue empty state reads as "nothing overdue", not as an error
- [x] Sidebar entry visible only with `view_student_action`
- [x] eslint clean on the three touched frontend files

## Risk Assessment

- **Pagination + tab interaction** — switching bucket while on page 3 must reset to
  page 1; `useTableFilters` handles this for other fields, verify it does for `bucket`
  and reset explicitly if not.
- **Count band drifting from the list** — counts come from Phase 1's dedicated
  aggregate, never from `rows.data.length`.
- **Vietnamese labels vs English page chrome** — sibling reports use English headings
  with Vietnamese terms inline (e.g. "quyết định"). Match that, do not introduce a
  second convention.
