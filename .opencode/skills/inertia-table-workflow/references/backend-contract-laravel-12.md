# Backend Contract (Laravel 12)

Use one stable contract for every index endpoint.

## 1) Request Validation

- Create `{Resource}IndexRequest`.
- Validate + normalize query keys:
  - `search`: nullable string
  - `sort`: nullable string, in allowlist
  - `direction`: nullable, `asc|desc`
  - `page`: integer min:1
  - `per_page`: integer min:1 max:100
  - resource filters (`type`, `status`, `campus_id`, etc.)

## 2) Query Layer

- Move list logic to action/query class (`ListUnitsQuery`).
- Apply filters with `when()` or dedicated scopes.
- Apply sort only from allowlist map:

```php
$allowedSorts = [
    'code' => 'code',
    'name' => 'name',
    'level' => 'level',
    'created_at' => 'created_at',
];
```

- Fallback sort when query params missing/invalid.

## 3) Pagination

- Use `paginate($perPage)->withQueryString()`.
- Cap `per_page` server-side even if UI sends larger values.
- Return full paginator meta/links to Inertia page.

## 4) Controller

- Keep controller thin: validate -> query class -> Inertia render.
- Return page props with stable keys:
  - table data (`units`)
  - normalized `filters`
  - optional stats/cards

## 5) Resource/DTO Stability

- Return table row shape via API Resource.
- Keep column keys stable across releases.
- Add computed/derived fields in resource layer, not component render hacks.

## 6) Performance Rules

- Add DB indexes for hot filter/sort columns.
- Eager-load relations used in columns.
- Avoid N+1 on row badges/counts.

## 7) Safety Rules

- Never trust UI sort key directly in `orderBy`.
- Reject unknown filter keys.
- Sanitize text search length to avoid expensive scans.

## 8) Minimum Test Set

- Feature test: default listing order + default pagination.
- Feature test: each key filter behavior.
- Feature test: sort allowlist and invalid sort rejection/fallback.
- Feature test: query string persistence across pages.
