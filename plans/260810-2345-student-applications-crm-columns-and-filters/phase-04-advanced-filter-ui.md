---
phase: 4
title: "Advanced filter UI"
status: done
priority: P1
effort: "1.5d"
dependencies: [2, 3]
---

# Phase 4: Advanced filter UI

<!-- Updated: Red Team Session 1 - sentinel contract corrected, debounce over immediateFields, export search parity, scope=all -->

## Overview

Replace the 3-input filter card with a collapsible advanced-filter panel covering
the phase-2 filter groups, built from the existing
`resources/js/components/filters/*` components.

## Requirements

- Functional: the three current filters stay visible and behave identically when
  the panel is collapsed.
- Functional: advanced filters expand into grouped fields; each is server-side
  and URL-synced.
- Functional: an active-filter count while collapsed, and one action clears all.
- Functional: the export honors the same filters as the list.
- Non-functional: no new filter component library. Reuse `FilterPanel`,
  `FilterSelect`, `FilterSearchInput`, `FilterDateRange` (9 consumer pages
  today; closest precedent `Admin/Notifications/Ops/Deliveries.vue`).

## Architecture

### The sentinel: fix the contract, not the comparisons

The earlier plan said "adopt `''` and update the two existing comparisons". That
is wrong twice.

**`''` never reaches the server.** `useDataTable.buildParams()` skips any value
that is `''`, `null`, `undefined`, or equal to its `defaultValues` entry
(`useDataTable.ts:68,70`). After normalizing to `''`, the backend receives **no
`status` key at all**, and `$request->validated('status')` is `null`. A literal
`!== ''` comparison then produces `where('status', null)` → **zero rows on the
unfiltered page**. Existing tests pass `status` explicitly, so they would not
catch it.

**There are three PHP sentinel sites, not two:** `ListApplicationsQuery.php:26`,
`:29`, and `ExportApplicationsAction.php:28`. (`ExportApplicationsRequest`'s
`in:filtered,all` is the unrelated `scope` param — a careless sweep will hit it.)

**Corrected approach:** state the contract as **absent / null / `''` / `'all'` =
no filter**, keep every backend comparison null-safe, and normalize the frontend
only. Two extra comparisons remove the whole deploy-skew class: a stale bundle
sending `status=all` keeps working, as do bookmarked and emailed URLs.

### Selects go on debounce, not `immediateFields`

`useDataTable.navigate()` early-returns whenever a request is in flight
(`useDataTable.ts:118-123`) — no queue, no trailing re-fire — while `setFilter`
mutates local state first (`:141-145`). Today only 2 fields are immediate, so
collisions are rare. Putting ~12 selects in `immediateFields` makes dropped
navigations routine: pick Province, then Graduation Year within the round-trip,
and the second request is discarded while both dropdowns show a selection and
the badge counts 2 — results contradicting visible filter state, no error.

Put the new selects on the default 300ms debounce. Fixing `useDataTable` to
re-fire on `onFinish` is the alternative; it is a shared composable used by many
pages, so prefer the debounce unless the dropped-request bug is being fixed
deliberately.

### Export parity is already false today

The list searches 5 columns (`ListApplicationsQuery.php:24`: `full_name`,
`email`, `student_code`, `national_id`, `phone`); the export searches 4
(`ExportApplicationsAction.php:25` — no `student_code`). Searching a student code
lists rows and exports none.

Also, `ExportApplicationsAction` applies filters **only** inside
`if ($data['scope'] === 'filtered')` (`:23`). `scope` is a required user-supplied
GET param, so `scope=all` returns the whole campus regardless of filters. Campus
scoping is outside that branch and still applies, so it is a full-campus dump,
not full-database — but a "parity" test written only against `scope=filtered`
would pass green while the bypass stands.

<!-- Updated: Validation Session 2 - D11 removes scope=all -->

**D11: remove `scope=all` entirely.** The UI only ever sends `scope=filtered`
(`Index.vue:183`), so nothing legitimate depends on it. Drop `scope` from
`ExportApplicationsRequest` and from `ExportApplicationsAction`; filters always
apply. That closes the URL-edit bypass and makes the parity criterion
achievable — with one code path there is only one behavior to assert.

Therefore: unify the search predicate as its own step and remove `scope`. Best
shape is one shared filter-application method both callers use, rather than a
parallel `foreach` whitelist.

### Layout

Primary row (search + status + intake) always visible. The rest behind a "More
filters" `Collapsible`, each group in a `FilterPanel :columns="4"`, fed by the
lazy `filterOptions` prop from phase 2. Badge shows the active advanced-filter
count.

## Related Code Files

- Modify: `resources/js/pages/StudentApplications/Index.vue`
- Modify: `app/Modules/Admissions/Queries/ListApplicationsQuery.php` (null-safe sentinels; shared filter application)
- Modify: `app/Modules/Admissions/Actions/ExportApplicationsAction.php` (search parity, sentinel, `scope`)
- Modify: `app/Modules/Admissions/Http/Requests/Admissions/ExportApplicationsRequest.php` (whitelist new filters)
- Test: `tests/Feature/Admissions/ListApplicationsFilteringTest.php` (extend)

## Implementation Steps

1. Make all three backend sentinel sites null-safe (absent/null/`''`/`'all'` = no
   filter). Land isolated; add a regression test for a **no-parameters** request
   returning the full campus set — the case existing tests never exercise.
2. Unify the list/export search predicate (adds `student_code` to export).
3. Remove `scope` from `ExportApplicationsRequest` and `ExportApplicationsAction`
   (D11). Filters always apply. Confirm no caller other than `Index.vue:183`
   sends it.
4. Extend the `Filters` interface, `initialFilters`, `defaultValues`. Put new
   selects on the default debounce, **not** `immediateFields`.
5. Build the collapsible panel from `FilterPanel` + `FilterSelect` +
   `FilterSearchInput`, one panel per phase-2 group.
6. Add numeric range inputs for `gpa`, `overall`, `crm_paid_amount` — two plain
   `Input type="number"` fields each; no new shared component for three uses.
7. Add the active-filter badge; wire `FilterPanel`'s `clear` to reset every
   filter including the primary row.
8. Refactor `exportExcel()` to reuse the table's filter object; whitelist the new
   fields in `ExportApplicationsRequest`.
9. Verify filter + sort + pagination + campus scoping compose in the running app.

## Validation

```bash
./scripts/dev.sh artisan test tests/Feature/Admissions
./scripts/dev.sh npm exec eslint -- resources/js/pages/StudentApplications/Index.vue
```

Manual: apply filters, confirm URL carries them, reload, page through, export and
compare row sets. Also load `/student-applications` with **no** query string.

## Success Criteria

- [ ] A request with **no** filter parameters returns the full campus set
- [ ] A stale `?status=all` URL still returns all statuses (no deploy-skew break)
- [ ] Every phase-2 filter group returns correct rows
- [ ] Rapidly changing two selects leaves the result set consistent with the
      visible filter state (no dropped navigation)
- [ ] Export row set matches the on-screen filtered set — one code path, no
      `scope` param remains
- [ ] Searching a student code returns the same rows in list and export
- [ ] Active-filter count accurate; Clear resets every field
- [ ] Filters persist in the URL and restore on reload
- [ ] Sorting and pagination work with filters applied
- [ ] A filter cannot widen the visible set beyond the user's campus, and a null
      campus returns zero rows (phase-2 fail-closed still holds here)

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Sentinel change empties the list for stale bundles / bookmarked URLs | Backend accepts all four forms permanently; frontend-only normalization; no-params regression test |
| ~12 immediate-fire selects drop concurrent requests | New selects use the default debounce |
| Export and list drift | Shared filter application; one code path after `scope` removal; parity asserted by test |
| `scope=all` was a full-campus PII dump by URL edit | Removed outright (D11); no permission gate needed because nothing legitimate used it |
| Filter panel becomes a wall of 20 inputs | Collapsible, 3 primary filters visible by default |
