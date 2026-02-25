# Code Standards

Last updated: 2026-02-23
Applies to: backend, frontend, API contracts, tests, and docs updates

## 1) Core Principles

- YAGNI: implement only currently required behavior.
- KISS: prefer simple, explicit logic over abstractions.
- DRY: reuse shared logic when duplication becomes operationally costly.
- Evidence-first docs: document only what is verified in code.

## 2) Repository Structure Standards

### Backend

- Keep domain-oriented code in `app/Modules/{Domain}/` when introducing or extending major business capabilities.
- Keep cross-domain technical plumbing in `app/Http`, `app/Models`, `app/Providers`, `app/Console`.
- Avoid adding new business-heavy classes in global `app/Services` unless extending existing service-centric areas.

### Frontend

- Keep Inertia pages in `resources/js/pages/{Feature}/`.
- Keep reusable UI in `resources/js/components/`.
- Keep reusable stateful logic in `resources/js/composables/`.
- Keep shared types in `resources/js/types/`.

## 3) Naming and File Conventions

- PHP classes: PascalCase.
- Vue components: PascalCase filenames.
- Composables: `useXxx.ts` naming.
- Database tables/columns: snake_case.
- Route names: dot notation where named.
- Route paths: kebab-case segments for readability.

## 4) Backend Implementation Standards

### Controllers

- Controllers should remain orchestration layers:
  - accept request
  - authorize
  - call action/query/service
  - return response

### Validation

- Use FormRequest classes for request validation.
- Keep validation rules out of controllers where feasible.

### Business Logic Placement

Current codebase has mixed patterns:
- `app/Modules/*/Actions/*` (often static `run`)
- `app/Actions/*` and some queries using `execute`
- heavy business/services in `app/Services/*`

Baseline rule for new work:
- Prefer domain `Actions` for write use-cases.
- Prefer domain `Queries` for read/reporting use-cases.
- Use `app/Services/*` only when extending existing service-led flows.

### Transactions and Side Effects

- Use DB transactions for multi-write operations that must stay consistent.
- Log operationally significant failures and state transitions.

### Authorization and Security

- Enforce access via middleware + `can:*` or policies.
- For API actor separation, use dedicated middleware (`student.api.auth`, `lecturer.api.auth`).
- Never commit secrets or credentials in source/docs.

### API Response Contract

- Prefer unified JSON envelopes through `App\Http\Responses\ApiResponse` for new/updated API endpoints.
- Keep error structure stable (`success`, `message`, `errors`, `timestamp`).

## 5) Frontend Standards

### Inertia and Layout

- Use Inertia page components as route-level views.
- Keep auth/campus selection exceptions explicit in layout resolver logic.

### Type Safety

- Maintain strict TypeScript compatibility (`npm run type-check`).
- Define explicit interfaces/types for API payloads.

### UI Patterns

- Use existing design tokens and theme variables in `resources/css/app.css`.
- Reuse existing UI primitives and sidebar/menu patterns.
- Avoid introducing parallel UI systems when current components can be extended.

### API Consumption

- Prefer existing composables/utilities for request logic.
- Keep client permission checks aligned with backend-provided permission props.

## 6) Testing Standards (Current Baseline)

Current state has limited automated tests. Baseline standard:
- Add or update tests for behavior-critical backend changes.
- Prioritize unit tests for finance/academic calculations and workflow edge cases.
- Add feature tests for critical route/controller behaviors.

Minimum quality checks before merge:
- `php artisan test`
- `npm run type-check`
- `npm run lint`

## 7) Documentation Standards

- Keep operational docs in `docs/`.
- Keep each markdown file concise and below 800 LOC.
- Update docs when route structure, scripts, env contracts, or architecture decisions change.
- Add an `Unresolved Questions` section whenever uncertainty remains.

## 8) Definition of Done (Engineering + Docs)

A change is complete only when:
- behavior works and is authorized correctly,
- validation and error handling are present,
- tests/checks are updated at least for the touched critical paths,
- relevant docs in `docs/` are updated.

## Unresolved Questions

- Should the team standardize one method name for actions/queries (`run` vs `execute`) for new code?
- Should new domain logic in `app/Services/*` be blocked in favor of `app/Modules/*`?
- What minimum test coverage target should be enforced per module?
