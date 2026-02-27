---
title: 'Refactor Student Decisions Index to Shared Inertia Table Stack'
description: 'Move StudentDecisions Index to reusable server-filter/sort/pagination building blocks with minimal page-specific logic.'
status: pending
priority: P2
effort: 6h
branch: dev
tags: [inertia, vue3, laravel12, datatable, refactor]
created: 2026-02-27
---

# Plan Overview

- Scope: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue` list/filter/table/pagination flow only; keep create/edit dialog behavior unchanged.
- Goal: reuse a single query-state composable + small shared list UI components so future report pages stop re-implementing filter/apply/sort/page glue.
- Constraints: keep compatibility with `DataTable`, `DataPagination`, `useInertiaFilters`; avoid backend contract churn beyond sort validation/query.

## Phases

1. `phase-01-shared-inertia-table-query-contract.md` - define reusable composable + filter panel component contracts.
2. `phase-02-student-decisions-page-migration.md` - migrate StudentDecisions page to shared contracts and `DataTable`/`DataPagination`.
3. `phase-03-validation-and-rollout.md` - validate typing/build/tests and document rollout guardrails.

## Proposed Files

- Create: `resources/js/composables/useServerTableQuery.ts`
- Create: `resources/js/components/filters/ServerDateRangeFilters.vue`
- Create: `resources/js/components/tables/ServerPaginatedDataTable.vue`
- Update: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`
- Update: `app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php`
- Update: `app/Modules/Academic/Queries/ListStudentDecisionsQuery.php`
- Optional update (if needed for type safety): `resources/js/types/student-decision.ts`

## Key Dependencies

- Existing table primitives: `resources/js/components/DataTable.vue`, `resources/js/components/DataPagination.vue`
- Existing query utility: `resources/js/composables/useInertiaFilters.ts`
- Backend filter source: `app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php`

## Non-Goals

- No redesign of StudentDecision create/edit dialog.
- No generic query builder for all pages now.
- No replacing `useInertiaFilters` internals.

## Unresolved Questions

- None.
