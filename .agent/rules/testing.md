---
trigger: manual
---

# Testing Rules: Filterable Index Flows

Use these rules for any list/index feature composed of an action, controller, and request class. They ensure consistent coverage for the backend layers that power our Vue + Inertia pages.

```
tests/
├── Feature/
│   └── {Domain}/
│       └── List{Resource}Test.php      ← controller + request coverage
└── Unit/
    └── Actions/
        └── List{Resource}ActionTest.php
```

## 1. Action Tests (`tests/Unit/Actions/List{Resource}ActionTest.php`)

Keep the action tests isolated from HTTP details. Call the action with raw filter arrays, seed data with factories, and assert on the `LengthAwarePaginator` it returns.

- **test runs without errors**
    - Seed multiple records.
    - `$action->execute(['per_page' => 15]);`
    - Assert paginator count and default ordering.
- **test filter / search**
    - Seed distinct records.
    - `$action->execute(['search' => 'keyword']);`
    - Assert only matching rows are returned.
- **test edge case (empty data)**
    - Run the action on an empty table.
    - Assert total `0`, no exceptions.

## 2. Controller Tests (`tests/Feature/{Domain}/List{Resource}Test.php`)

Hit the real HTTP route (e.g., `GET /resources`). These tests verify routing, Inertia rendering, and filter propagation.

- **test route returns 200**
    - `$this->get(route('{resources}.index'))->assertOk();`
- **test inertia component is correct**
    - `assertInertia(fn (AssertableInertia $page) => $page->component('{resourcePlural}/Index'));`
    - Optionally assert props: paginator payload + `filters`.
- **test filter/search interactions**
    - Request with query params (`?search=foo&sort=name`).
    - Assert `filters` props echo the request and paginator data reflects filtering.

## 3. Request Validation (same Feature test file)

Because the request object is resolved in the controller, keep validation tests alongside the controller tests.

- **test validation fails**
    - `$this->get(route('{resources}.index', ['per_page' => 999]));`
    - Assert session errors (e.g., `assertSessionHasErrors('per_page')`).
- **test validation passes**
    - `$this->get(route('{resources}.index', ['per_page' => 25]))->assertSessionHasNoErrors();`
    - Optionally assert sanitized props in the Inertia response.

## 4. Shared Best Practices

- Use factories and the `RefreshDatabase` trait; no global fixtures.
- Keep each Pest `it()` focused on one behaviour and name it descriptively.
- Reuse helpers (`actingAs`, `assertInertia`) for clarity.
- Mock dependencies only inside unit tests; feature tests should exercise real bindings.

Following this structure keeps every list endpoint covered end-to-end: unit tests protect the action’s query logic, and feature tests verify routing, validation, and Inertia output.
