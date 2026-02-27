# Verification Checklist

Run before marking table/filter/pagination work complete.

## Backend

- [ ] Request validates all filter/sort/pagination params.
- [ ] Sort key uses allowlist mapping; no raw `orderBy` user input.
- [ ] `per_page` is bounded server-side.
- [ ] Query uses eager loading for columns needing relations.
- [ ] Response includes normalized `filters` and paginator data.

## Frontend

- [ ] Shared composable owns query state.
- [ ] Search is debounced; sort/page/per-page immediate.
- [ ] Non-page filter change resets `page` to `1`.
- [ ] Defaults/empty values omitted from URL.
- [ ] `DataTable` emits only intent (`sort-change`, `selection-change`).
- [ ] `DataPagination` emits page intents; no `window.location` assembly.
- [ ] Date filter changes update URL immediately (no Apply required for date-only changes).
- [ ] Date clear from DatePicker popover also updates URL filters.
- [ ] Date filter component uses explicit model update wiring when object `v-model` causes stale URL sync.

## Behavior

- [ ] URL is shareable and rehydrates same table state after reload.
- [ ] Browser back/forward preserves expected list state.
- [ ] Clear filters returns canonical defaults.
- [ ] Pagination still honors active filters.

## Tests

- [ ] Feature tests cover key filter combinations.
- [ ] Feature tests cover sort fallback/allowlist.
- [ ] E2E/manual check for search -> sort -> paginate flow.
- [ ] Typecheck/lint passes for touched files.

## Migration Safety

- [ ] Existing query parameter names remain backward-compatible.
- [ ] Old deep links still work or redirect safely.
- [ ] Rollout notes documented for affected pages.
