---
phase: 2
title: "Backend column and filter contract"
status: done
priority: P1
effort: "1.5d"
dependencies: []
---

# Phase 2: Backend column and filter contract

<!-- Updated: Red Team Session 1 - per-campus distincts, shape-only validation, shared sort list, fail-closed campus, like escaping, lazy filterOptions -->

## Overview

Widen `ListApplicationsQuery` to return every shippable CRM field, expand the
sort allow-list, and add whitelisted multi-column filtering. Pure backend — the
UI consumes this in phases 3-4.

## Requirements

- Functional: every column in the plan's ship-list is present in each row payload.
- Functional: the 6 zero/near-zero columns (`new_province`, `new_street`,
  `new_ward`, `intended_specialization`, `sut_id`, `study_link_status`) are NOT
  added.
- Functional: filters are server-side and composable with search, sort,
  pagination, and campus scoping.
- Non-functional: every **sortable** field is whitelisted against a closed list.
  Data-derived **filter** values are validated for shape only (see below).
- Non-functional: campus scoping fails **closed**.

> Removed the earlier "no N+1" requirement — it was false on arrival.
> `ListApplicationsQuery::handle()` already calls
> `GetApplicationConversionReadinessQuery::handle()` per pending row, and that
> runs an unmemoized `User::where('email')->exists()` each time
> (`GetApplicationConversionReadinessQuery.php:77`), up to `per_page` = 200.
> This phase explicitly keeps that behavior unchanged. Batching it is a separate
> concern; do not claim a property the phase does not deliver.

## Architecture

| File | Change |
|---|---|
| `ListApplicationsRequest` | filter rules; consume shared sort allow-list |
| `ExportApplicationsRequest` | consume the **same** shared sort allow-list |
| `ListApplicationsQuery::handle()` | new projection fields; filter clauses; fail-closed campus |
| `ListApplicationsQuery::filters()` | campus-scoped option lists |
| `StudentApplicationController` | pass `filterOptions` lazily |

### Sort allow-list — one source, two consumers

The list `in:` string is duplicated **verbatim** in two files:
`ListApplicationsRequest.php:18` and `ExportApplicationsRequest.php:18`.
Extending only the first means sorting by `gpa` and then clicking Export fails
validation and dead-ends the user on a 422 page (the controller's `catch` is
`Throwable`, so the friendly flash never fires).

Extract one shared constant consumed by both. Extend it with: `crm_campus`,
`crm_major`, `gpa`, `school`, `graduation_year`, `province`, `nationality`,
`crm_paid_amount`, `last_synced_at`, `overall`, `gender`. Only real DB columns.

### Filter validation — shape only for data-derived fields

Do **not** build `Rule::in()` from live `DISTINCT` queries. That would (a) run a
DB query per field inside `rules()`, and (b) make validity depend on current
data and the viewer's campus — so a bookmarked `?scholarship=X` starts 422-ing
when the last row carrying `X` changes, contradicting **D6** (an unsatisfiable
filter returns empty, it does not error).

| Field kind | Validation |
|---|---|
| Static enums (`status`, `synced`, `direction`, `sort`, `gender`, `gpa_type`) | `Rule::in([...])` with a hardcoded array — the pattern in `app/Http/Requests/Lecture/ListLecturesRequest.php:51-59`, which uses **static** arrays |
| Data-derived (`province`, `scholarship`, `school`, `birth_place`, `graduation_year`, …) | `nullable`, `string`, `max:255` — an unmatched value simply returns zero rows |
| Numeric ranges | `nullable`, `numeric`, `min:0` |

### Filter field groups

| Group | Fields | Type |
|---|---|---|
| Identity | `search` (existing), `gender`, `ethnicity`, `religion` | select |
| Admission | `status`, `intake` (existing), `crm_major`, `scholarship`, `pathway_gateway` | select |
| Academic | `graduation_year`, `gpa_type` select; `school` free-text; `gpa_min`/`gpa_max` range |
| Location | `province` select; `birth_place` free-text |
| English | `english_test_type` select; `overall_min`/`overall_max` range |
| Sync | `synced` (`all` \| `synced` \| `not_synced`) from `last_synced_at IS NULL` |
| Finance | `paid_min` / `paid_max` on `crm_paid_amount` | range |

### Distinct counts — must be measured PER CAMPUS

The earlier measurement was taken on the **unscoped** table, which is the wrong
scope: options are campus-scoped, so a filter's usefulness must be judged per
campus. Re-measured 2026-08-11:

| Field | Global distinct | HCM | HN | Decision |
|---|---|---|---|---|
| `crm_campus` | 2 | **1** | **1** | **omit as a filter** — it *is* the campus |
| `province` | 31 | 17 | 14 | select |
| `school` | 121 | — | — | free-text `like` |
| `birth_place` | 57 | — | — | free-text `like` |
| `nationality` | 1 | 1 | 1 | omit as a filter |
| `uu_dai_gc` | 1 | 1 | 1 | omit as a filter |
| `graduation_year` 8, `scholarship` 7, `religion` 6, `ethnicity` 4, `crm_major` 4, `gpa_type` 3, `pathway_gateway` 2, `english_test_type` 2, `gender` 2 | | | | select |

`crm_campus`, `nationality`, `uu_dai_gc` remain available as **columns**; they
are excluded only from the filter set. Re-measure per campus before adding any
new select.

**Zero-vs-null (D12):** `overall` is 311 non-null but only 206 non-zero — 105
rows hold `0.00`. **`0` means "no data", not a score.** Range filters on
`overall` and `gpa` must therefore exclude zero rows explicitly
(`where('overall', '>', 0)` alongside the range), rather than relying on a
positive minimum to hide them — otherwise `overall_min = 0` would sweep all 105
in as if they had been assessed.

### Campus scoping must fail closed

`ListApplicationsQuery.php:20-22` is `if ($campusCode !== null)` — null means
**no predicate at all**, i.e. every campus. The controller derives it as
`app()->bound('campus') ? app('campus') : null`
(`StudentApplicationController.php:52`), and `SetCampus` only binds when
`Campus::find()` succeeds (`SetCampus.php:20`), while `CheckCampusSelected` only
checks the session *key* exists (`CheckCampusSelected.php:53`).

So a session holding a **deleted** campus id passes the gate, skips the bind,
and returns every campus. Pre-existing, but this phase multiplies the payload by
~20 PII columns, so it is fixed here: null campus returns an empty set (or
throws) in both `ListApplicationsQuery::handle()` and `ExportApplicationsAction`.

### Option lists must carry the campus

`filters()` currently campus-scopes exactly one list, by a hand-written `when()`
on `ListApplicationsQuery.php:49`. The shared helper must take the campus
explicitly so the scope cannot be forgotten on the reused path:

```php
private function distinctOptions(string $column, ?string $campusCode): Collection
```

Otherwise `filterOptions.school` / `.province` / `.religion` leak another
campus's applicant population as Inertia props — readable in page source without
opening the filter panel.

### `like` filters must escape wildcards

Bindings stop SQL injection; they do not stop `%` and `_` acting as wildcards.
`?school=%` would return every row with a non-null `school` while the user
believes they narrowed. Repo-wide there is exactly one escaping precedent
(`app/Modules/Academic/Support/AiAcademicEntitySearchReader.php:228`,
`addcslashes($value, '\%_')`); the existing search on this very query
(`ListApplicationsQuery.php:24`) is unescaped. Add one shared `escapeLike()`
helper used by the two new free-text filters.

### `filterOptions` must be lazy

The controller builds `$lists` eagerly before `Inertia::render`
(`StudentApplicationController.php:53`), but the page's partial reload is
`only: ['applications', 'filters']` (`Index.vue:131`) — so the option lists are
serialized away while their `DISTINCT` scans have already run on every
keystroke. Pass `filterOptions` as a closure so Inertia skips it on partial
reloads.

## Related Code Files

- Modify: `app/Modules/Admissions/Http/Requests/Admissions/ListApplicationsRequest.php`
- Modify: `app/Modules/Admissions/Http/Requests/Admissions/ExportApplicationsRequest.php` (shared sort list)
- Modify: `app/Modules/Admissions/Queries/ListApplicationsQuery.php` — note its
  `use App\Models\ApplicationDocumentType` (line 7) is a deprecated shim import.
  **Leave it as-is**; the sweep is a separate plan
- Modify: `app/Modules/Admissions/Actions/ExportApplicationsAction.php` (fail-closed campus)
- Modify: `app/Modules/Admissions/Http/Web/StudentApplicationController.php` (lazy `filterOptions`)
- Test: `tests/Feature/Admissions/ListApplicationsFilteringTest.php` (create)

## Implementation Steps

1. Extract the shared sort allow-list constant; point both FormRequests at it.
2. Make campus scoping fail closed in the query and the export action.
3. Extend `ListApplicationsRequest::rules()` per the validation table above.
4. Extend the `through()` projection with the ship-list fields, leaving
   `documents_by_type` and `conversion_readiness` untouched.
5. Add filter clauses via `when()` in a private `applyFilters()` method.
6. Add `escapeLike()` and use it for `school` / `birth_place`.
7. Add `distinctOptions(string $column, ?string $campusCode)` and build the
   option lists with it.
8. Pass `filterOptions` to Inertia as a closure.
9. Tests first per repo TDD rule.

## Validation

```bash
./scripts/dev.sh artisan test tests/Feature/Admissions/ListApplicationsFilteringTest.php
./scripts/dev.sh artisan test tests/Feature/Admissions
```

## Success Criteria

- [ ] Each filter field returns only matching rows (one case per group)
- [ ] Filters compose: campus scope + status + `gpa_min` yield the intersection
- [ ] An out-of-allow-list `sort` is rejected with 422 by **both** requests, and
      a test asserts the two allow-lists are identical
- [ ] Sorting by each newly allowed column then exporting succeeds (no 422)
- [ ] A **null campus** returns zero rows, not every campus — asserted for both
      the list query and the export action
- [ ] `school=%` returns zero rows, not everything
- [ ] A value present only at another campus does not appear in `filterOptions`
- [ ] A data-derived filter value that matches nothing returns an empty set, not
      a 422 (consistent with D6)
- [ ] `gpa_min` > `gpa_max` returns an empty result set for all three range pairs
- [ ] Row payload contains every ship-list field and none of the 6 excluded ones
- [ ] Existing list/sort/pagination tests still green

## Risk Assessment

| Risk | Mitigation |
|---|---|
| `crm_campus` shipped as a 1-option filter | Resolved: measured per campus, dropped from the filter set |
| Free-text `school` (121) / `birth_place` (57) as selects | Resolved: both are `like` filters, escaped |
| Bookmarked filter 422s after data changes | Resolved: shape-only validation for data-derived fields |
| Campus scoping fails open on a stale session | Fixed in this phase; asserted by test |
| Option lists leak another campus's population | Helper signature pins `$campusCode`; asserted by test |
| Filter clauses scan unindexed columns | 395 rows today. Measure before indexing — but note the readiness N+1 above is the larger cost at high `per_page` |
| Sort allow-list drifts from real columns | Test asserts every allow-listed value is a real column on `student_applications` |
