---
title: Testing and Quality Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - tests
  - app
  - resources/js
---

# Testing and Quality Rules

Verification is scoped to changed behavior. Repository-wide suites are reserved
for cross-cutting changes, explicit requests, and release or merge validation.

## Required evidence

- Add or update tests for changed behavior, including authorization,
  validation, error, and empty-state paths that matter to the use case.
- Use Pest feature tests for HTTP, middleware, bindings, Inertia props, and
  database integration.
- Use unit tests for isolated Actions, Queries, value objects, and algorithms
  only when the isolation is meaningful.
- Use factories and existing factory states. Do not create global fixtures.
- Keep each test focused and name it by observable behavior.
- Mock external boundaries in unit tests; feature tests should use real
  application bindings unless the external provider itself is out of scope.

## Filtered index coverage

For a filtered, sorted, or paginated page, prove the layers that changed:

- Query/Action: defaults, ordering, supported filters, empty data, pagination.
- FormRequest: accepted values and rejected invalid sort, direction, page, and
  per-page values.
- Controller: route access, authorization, expected Inertia component, props,
  and echoed sanitized filters.
- Frontend: component behavior when a component test exists; otherwise
  file-level lint and formatting.

Use `RefreshDatabase` where persistence is involved and assert against the
paginator or Inertia contract rather than implementation details.

## Scoped commands

Run commands through `./scripts/dev.sh`.

- PHP: the smallest relevant Pest file or `--filter`.
- Frontend: component tests where available, then lint/format checks scoped to
  changed files.
- PHP formatting: Pint on changed PHP files. The repository's required
  pre-finalization command is
  `./scripts/dev.sh composer exec pint -- --dirty --format agent`.

Do not run `./scripts/dev.sh test`, global `npm run type-check`, or repository
wide frontend lint by default for an individual issue.

## Reporting

The final change summary records:

- targeted checks run and their results;
- broader checks intentionally not run because they were outside scope;
- any check that could not run and the reason;
- portal lint, typecheck, and build results when a portal was changed.

Local evidence is the active quality gate while CI workflows are inactive; do
not imply green CI when no workflow enforced the change.
