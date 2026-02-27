# Drift Detection Patterns

Use these checks to detect inconsistent table/filter/pagination behavior.

## 1) Page Discovery

Find pages combining all 3 parts:

- `useInertiaFilters` usage
- `<DataTable ...>` usage
- `<DataPagination ...>` usage

Classify by module and route criticality.

## 2) Drift Signals

- Pagination builds URLs with `window.location`.
- Per-page filter defaults differ (`15`, `25`, etc.) without product reason.
- Sentinel mismatch (`all`, `''`, `null`) for same filter type.
- Sort direction defaults differ (`asc` vs `null`) unexpectedly.
- Page reset missing when filters change.
- Page manually computes query string in many places.

## 3) Contract Diff Matrix

Track per page:

- `baseUrl`
- `only` keys
- filter keys/defaults
- query serialization rules
- sort event names
- pagination event names

Mark each field: `same`, `compatible`, `breaking`.

## 4) Risk Scoring

- Low risk: already close to canonical contract.
- Medium risk: event names or defaults differ.
- High risk: custom pagination or custom data loading flow.

Prioritize low risk pages first.

## 5) Quick Compatibility Checks

- Existing deep links still load same result set.
- Browser back button preserves filter state.
- Clear filter behavior unchanged for users.
- Export endpoints still receive expected query keys.

## 6) Common Fix Mapping

- URL building in pagination -> emit page number only.
- Repeated page glue -> centralize in shared composable.
- Ad-hoc search debounce -> use single debounce policy.
- Divergent sort handling -> unify `sort-change(sort, direction)`.
