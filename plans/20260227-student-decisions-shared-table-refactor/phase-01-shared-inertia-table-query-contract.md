# Phase 01 - Shared Inertia Table Query Contract

## Context Links

- `resources/js/composables/useInertiaFilters.ts`
- `resources/js/components/DataTable.vue`
- `resources/js/components/DataPagination.vue`
- `resources/js/pages/students/Index.vue`

## Overview

- Priority: P1
- Status: pending
- Description: define minimal reusable abstractions for server-side filter/sort/pagination without breaking existing patterns.

## Key Insights

- Current StudentDecisions page duplicates query sync logic (`router.get`) and custom HTML table/pagination.
- `DataTable` already emits `sort-change`; `DataPagination` already emits page and per-page events.
- `useInertiaFilters` already supports sort/page/per_page; wrapper composable should compose, not replace.

## Requirements

- Functional: expose typed query state for search/date range/sort/page/per_page and handlers usable by `DataTable` + `DataPagination`.
- Functional: provide reusable filter UI block for search + date range + apply/clear controls.
- Non-functional: low API surface, no domain-specific props, no behavior changes for existing pages.

## Architecture

- `useServerTableQuery` wraps `useInertiaFilters` with thin defaults for table pages (`only`, `debounce`, defaultValues).
- `ServerDateRangeFilters` renders common controls and emits `apply` + `clear`; parent owns filter model.
- `ServerPaginatedDataTable` composes `DataTable` + `DataPagination`, forwarding sort/navigate/per-page events.

## Related Code Files

- Create: `resources/js/composables/useServerTableQuery.ts`
- Create: `resources/js/components/filters/ServerDateRangeFilters.vue`
- Create: `resources/js/components/tables/ServerPaginatedDataTable.vue`

## Implementation Steps

1. Add `useServerTableQuery` with generic `TFilters` and return passthrough handlers from `useInertiaFilters`.
2. Add `ServerDateRangeFilters` with controlled props (`search`, `from`, `to`, `isDirty`) + emits.
3. Add `ServerPaginatedDataTable` to wire `DataTable` sort state and `DataPagination` handlers in one component.
4. Keep each new file small; avoid embedding StudentDecision-specific labels/columns.

## Todo List

- [ ] Define composable API and typing.
- [ ] Build reusable filter component.
- [ ] Build reusable table+pagination composition component.
- [ ] Add brief usage docs in component comments/props.

## Success Criteria

- New shared blocks compile and can be dropped into StudentDecisions without adapter hacks.
- No regression in `DataTable` sort event contract.
- No regression in `DataPagination` navigate/page-size contract.

## Risk Assessment

- Risk: over-generic abstractions hard to adopt.
- Mitigation: keep props narrow and model after existing StudentDecisions needs only.

## Security Considerations

- Ensure UI only emits whitelisted sort keys expected by backend validation.
- Avoid exposing internal URLs beyond standard Inertia navigation.

## Next Steps

- Proceed to StudentDecisions migration with backend sort validation alignment.
