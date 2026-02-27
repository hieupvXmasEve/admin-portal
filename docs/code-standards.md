# Code Standards

Last updated: 2026-02-27  
Owner: Platform Team  
Status: Active baseline (enforced by convention; CI currently inactive)  
Source of truth: repository code and route/contracts in this workspace

## 1) Core Principles

- YAGNI
- KISS
- DRY
- Evidence-first documentation (no unverified claims)

## 2) Structure Standards

### Backend

- Prefer new business logic in `app/Modules/{Domain}`.
- Keep shared framework plumbing in Laravel standard folders.
- Extend `app/Services/*` only when modifying an existing service-led area.

### Frontend

- Pages: `resources/js/pages/{Feature}`
- Shared components: `resources/js/components`
- Reusable logic: `resources/js/composables`
- Shared contracts/types: `resources/js/types`
- Route helpers/constants should be preferred over literal paths.

## 3) Naming and Contract Rules

- PHP classes: PascalCase
- Vue component files: PascalCase
- Composables: `useXxx.ts`
- DB tables/columns: snake_case
- Route names: dot notation
- URL segments: kebab-case

For student action logs/import contracts keep exact fields:

- `decision_number`
- `decision_signed_at`
- `decision_signer`
- `decision_id`
- `missing_documents`
- `shared_upload_record_id`

## 4) Backend Implementation Rules

- Controllers orchestrate; they do not contain heavy business logic.
- Use FormRequest for request validation.
- Use transactions for multi-write flows.
- Keep auth/authorization explicit in middleware/policies.
- For token refresh, keep current rotation pattern consistent unless intentionally migrated.

## 5) API Security and Auth Standards

Current enforced baseline:

- Student/Lecturer v1 APIs: Sanctum + actor middleware chain.
- Parent access to student APIs: explicit parent proxy middleware path.

Current known exceptions (must be documented, not ignored):

- Public `system-config` API routes.
- Finance API route groups using `web` + `auth`.

## 6) Frontend Contract Standards

- Prefer `route(...)` helpers and typed route constants.
- Avoid introducing new literal endpoint strings when a helper exists.
- Prefer shared API wrappers/composables for consistent envelope/error handling.
- For server-driven list pages, prefer shared table workflow primitives (`useServerTableQuery`, `ServerPaginatedDataTable`, `ServerDateRangeFilters`) instead of page-local query/pagination glue.
- Keep auth-sensitive calls aligned with backend middleware expectations.

## 7) Testing and Quality Gates

Current status:

- Local quality scripts exist.
- GitHub workflow enforcement is disabled (commented workflow files).

Minimum expected checks before merge:

- `php artisan test`
- `npm run type-check`
- `npm run lint`

If checks cannot be run, record the gap in the change summary.

## 8) Documentation Standards

- `docs/` is the source-of-truth directory for engineering docs.
- Core docs must include:
    - last updated date
    - owner
    - status
    - unresolved questions (if decisions pending)
- Keep docs under 800 LOC and remove stale claims quickly.

## 9) Definition of Done

A change is complete when:

- implementation behavior is correct
- auth/validation/error paths are handled
- quality checks are run or explicitly tracked as not-run
- impacted docs are updated in the same change window

## Unresolved Questions

- Should `run` and `execute` action method naming be standardized repo-wide?
- Which checks become hard merge blockers once CI is re-enabled?
