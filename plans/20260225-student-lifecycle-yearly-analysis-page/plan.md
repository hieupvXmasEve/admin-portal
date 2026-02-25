---
title: "Student Lifecycle Yearly Analysis Page"
description: "Plan to add yearly lifecycle analysis table page, Academic module route, and sidebar menu entry."
status: pending
priority: P2
effort: 14h
branch: dev
tags: [academic, reports, yearly-analytics, inertia, vue]
created: 2026-02-25
---

## Goal
Ship one new web page showing yearly student lifecycle metrics table, reachable from Academic module web route and from `Reports & Analytics` sidebar.

## Scope
In scope:
- Web route in Academic module routes file
- Controller + query/use-case to provide yearly table data
- Inertia Vue page for yearly table
- Sidebar menu entry under `Reports & Analytics`
- Route helper update used by sidebar

Out of scope:
- New data warehouse/snapshot pipeline
- Changing finalized metric definitions
- Reworking existing report pages

## Constraints and Alignment
- Keep implementation in existing monolith pattern: route -> controller -> query/action -> Inertia page.
- Follow existing Admin reports naming/layout patterns.
- Keep route in `app/Modules/Academic/routes/web.php` (Academic module-owned web routes).
- Keep permissions simple: reuse existing report permission `view_student_action`.

## Finalized Data Contract Mapping (must be encoded as-is)
- Year bucket: `students.intake_semester_id -> semesters.start_date -> academic year bucket`.
- `NE`: students with `students.user_id IS NOT NULL`.
- Rates: divide by `NE`.
- `Graduated`: `students.status = 'graduated'`.
- `DO-Transfer`: `students.status = 'dropout_transfer'`.

## File-Level Scope
Planned files to add/update:
- Update: `app/Modules/Academic/routes/web.php`
  - Add yearly analysis index route under reports prefix, named route like `reports.student-lifecycle-yearly.index`.
- Add: `app/Modules/Academic/Http/Web/Admin/StudentLifecycleYearlyAnalysisController.php`
  - Validate filters, call query, return Inertia page.
- Add: `app/Modules/Academic/Queries/Reporting/GetStudentLifecycleYearlyAnalysisQuery.php`
  - Build grouped yearly dataset, enforce metric definitions and rate formula.
- Add: `resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue`
  - Render yearly table and filters (year range/campus/program if existing pattern supports).
- Update: `resources/js/utils/routes.ts`
  - Add route helper for new yearly analysis page.
- Update: `resources/js/constants/menu-sidebar.ts`
  - Add menu item under existing `Reports & Analytics` group.
- Add tests (expected):
  - `tests/Feature/Modules/Academic/Web/StudentLifecycleYearlyAnalysisPageTest.php`
  - `tests/Unit/Modules/Academic/Queries/GetStudentLifecycleYearlyAnalysisQueryTest.php`

## Phased Tasks

### Phase 1 - Route and Navigation Wiring (2h)
Tasks:
1. Add Academic module route in `app/Modules/Academic/routes/web.php`.
2. Add route helper in `resources/js/utils/routes.ts`.
3. Add sidebar menu entry in `resources/js/constants/menu-sidebar.ts` under `Reports & Analytics`.

Acceptance:
- Route name resolves via Ziggy.
- Sidebar item visible for users with intended permission.
- Clicking menu opens the new page route.

### Phase 2 - Backend Data Delivery (5h)
Tasks:
1. Create web controller returning Inertia page + dataset payload.
2. Create query class for yearly aggregate rows by intake academic year.
3. Encode finalized mappings for `NE`, rates denominator, `Graduated`, `DO-Transfer`.

Acceptance:
- Response payload contains stable table schema per metric contract.
- Rate columns compute as `metric / NE` with zero-division safe handling.
- Intake year derived only from `intake_semester_id -> semesters.start_date`.

### Phase 3 - Frontend Table Page (4h)
Tasks:
1. Build `Index.vue` for yearly analysis table in Admin Reports section style.
2. Add loading/empty/error states consistent with existing report pages.
3. Show all required columns and formatted rates.

Acceptance:
- Page renders from server data without client-side recomputation drift.
- Table column labels match agreed business names.
- Empty-state behavior explicit when no yearly rows.

### Phase 4 - Verification and Guardrails (3h)
Tasks:
1. Feature test for route access, permission gate, and Inertia component.
2. Query unit tests for each mapping rule and rate calculation.
3. Regression check that existing report menus/routes remain unaffected.

Acceptance:
- Tests pass for happy path + edge cases (`NE = 0`, missing intake semester).

## Risks and Mitigations
- Route placement confusion between `routes/web/academic.php` and module routes.
  - Mitigation: keep this feature route in `app/Modules/Academic/routes/web.php` only.
- Historical distortion if intake year is computed from other fields.
  - Mitigation: force single derivation path via `intake_semester_id -> semesters.start_date`.

## Definition of Done
- New page is reachable from sidebar and route.
- Yearly table returns all required metrics with finalized formulas.
- Tests cover route + query contract.

## Unresolved Questions
None.
