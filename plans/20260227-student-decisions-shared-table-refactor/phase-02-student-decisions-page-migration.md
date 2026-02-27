# Phase 02 - StudentDecisions Page Migration

## Context Links

- `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`
- `app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php`
- `app/Modules/Academic/Queries/ListStudentDecisionsQuery.php`

## Overview

- Priority: P1
- Status: pending
- Description: replace page-local table/filter/pagination glue with shared contracts and enable server-side sorting.

## Key Insights

- Backend currently validates `search`, `issued_from`, `issued_to`, `per_page`; no `sort`/`direction` yet.
- Query defaults to `issued_at desc, id desc`; can preserve by default when sort absent.
- Page currently hand-renders `<table>` and pagination links; this is migration target.

## Requirements

- Functional: move filter state to `useServerTableQuery` (internally `useInertiaFilters`) with defaults.
- Functional: render rows via `DataTable` columns and `ServerPaginatedDataTable`.
- Functional: support sortable columns at least for `decision_number`, `decision_name`, `decision_signer`, `issued_at`, `expires_at`, `linked_actions_count`, `linked_students_count`.
- Functional: keep create/edit dialog and existing toast behaviors unchanged.

## Architecture

- Frontend: page defines typed column defs and row cell slots, delegates query events to shared composable.
- Backend: controller validates `sort` + `direction` whitelist and passes values in `filters` prop.
- Backend query: apply safe conditional `orderBy` using whitelist map; fallback to existing default ordering.

## Related Code Files

- Update: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`
- Update: `app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php`
- Update: `app/Modules/Academic/Queries/ListStudentDecisionsQuery.php`
- Optional update: `resources/js/types/student-decision.ts`

## Implementation Steps

1. Add `StudentDecisionFilters` type in page: search/date range/sort/direction/per_page/page.
2. Replace local `filters` ref + `applyFilters` router call with `useServerTableQuery` handlers.
3. Build column defs for `DataTable`, including badges and file/action buttons via slots.
4. Replace custom table and manual pagination links with `ServerPaginatedDataTable`.
5. Add backend request validation for `sort` and `direction`; include in Inertia `filters` payload.
6. Extend query class to apply whitelisted sorting; preserve default order when not provided.
7. Verify existing dialog create/update flow still works with preserve state/scroll.

## Todo List

- [ ] Wire composable in page and remove duplicated query logic.
- [ ] Migrate UI from raw table to shared DataTable stack.
- [ ] Add backend sort validation and query ordering.
- [ ] Verify URL query sync for filter/sort/pagination.

## Success Criteria

- StudentDecisions list supports server-side filter, sort, page, and per-page via shared abstractions.
- URL stays source-of-truth for list state and supports refresh/back-forward.
- No regression on create/edit decision flows.

## Risk Assessment

- Risk: sorting by derived counts may need select aliases.
- Mitigation: keep sortable fields mapped to explicit SQL-safe columns/aliases only.

## Security Considerations

- Strict whitelist for sort fields and direction values in controller validation.
- No raw orderBy from untrusted request values.

## Next Steps

- Execute validation suite and document any remaining migration candidates.
