# Code Standards

Last updated: 2026-03-26  
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

For Notification V2 Phase 1 contracts keep exact fields:

- `notification_event_outbox.campus_id`
- `notification_messages.recipient_user_id`
- `notification_messages.campus_id`

## 4) Backend Implementation Rules

- Controllers orchestrate; they do not contain heavy business logic.
- Use FormRequest for request validation.
- Use transactions for multi-write flows.
- Keep auth/authorization explicit in middleware/policies.
- For token refresh, keep current rotation pattern consistent unless intentionally migrated.
- For Notification V2 operations, use `notifications:process-outbox` for outbox dispatch; do not bypass pipeline with direct delivery writes.
- For Finance settlement: use `PaymentApplication`, `DiscountAllocation` models. Do not reference legacy `payment_allocations` in new code.
- For Finance charge/voiding: use `VoidFinanceChargeAction`, `AllocatePaymentAction`, `AutoAllocatePaymentsAction`. Respect `invoice_lines.status` lifecycle (active|void).
- For DNG payment gateway operations:
    - Always use `DngClient` for API calls; verify checksum before processing webhook events.
    - Use `DngPaymentService` entrypoints for DNG create/push flows and payment-link flows.
    - Student identifier field is `student_code` (string MSSV from `students.student_id`), never `student_id` (int DB PK).
    - Campus code for DNG is resolved per campus from `campuses.dng_code`; do not treat `services.dng.campus_code` as source of truth.
    - When creating a new DNG request for the same `student + fee_type`, only cancel previous unpaid requests (`pending`, `pushed_to_dng`) after the new push succeeds.
    - Cancelled DNG requests are terminal and must not be revived by late webhook/reconciliation processing.
    - Queue webhook processing via `ProcessDngWebhookJob` instead of synchronous response handlers.
    - Audit all DNG requests/responses in `dng_payment_requests` and `dng_webhook_events` tables.

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
- Current filter stack is hybrid during migration:
    - preferred for new server tables: `useServerTableQuery`
    - broadly used baseline: `useInertiaFilters`
    - legacy still present: `useFilters` / `useTableFilters` from `resources/js/composables/useFilters.ts`
- For server-driven list pages, prefer shared table workflow primitives (for example `useServerTableQuery` and shared server-table components) instead of page-local query/pagination glue.
- **Canonical Pattern**: For new server-filtered/sorted/paginated pages, follow the `inertia-filter-table` skill at `custom-skills/inertia-filter-table/SKILL.md`. This skill documents the 5-step workflow: Query Class → Controller → Frontend Composable → Filter Panel → Data Table.
- **Reusable Filter Components** (`resources/js/components/filters/`):
    - `FilterPanel.vue` — grid container with configurable columns (2-6) and clear button
    - `FilterSearchInput.vue` — debounced search input (300ms default)
    - `FilterDateRange.vue` — date range picker (occupies 2 grid cells)
    - `FilterSelect.vue` — select dropdown for enum/status filters

    Usage pattern:
    ```vue
    <FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
        <FilterSearchInput :model-value="filters.search ?? ''" @search="applySearch" />
        <FilterSelect
            :model-value="filters.status ?? ''"
            :options="statusOptions"
            placeholder="All statuses"
            @change="(v) => applyFilter('status', v)"
        />
        <FilterDateRange
            :from-value="filters.issued_from ?? ''"
            :to-value="filters.issued_to ?? ''"
            @change="handleDateChange"
        />
    </FilterPanel>
    ```
- Keep auth-sensitive calls aligned with backend middleware expectations.

Form submission default and exception path:

- Default for Inertia pages: use Inertia `useForm` and server-driven redirects/validation.
- Exception path: use `vee-validate` + Zod + `useApi`/`useApiRequest` only for non-navigating JSON interactions (modal/drawer/inline flows).
- **Finance exception pattern**: Finance operations pages (Settlement Worklist, Dashboard, AutoAllocate, Generate Charges) use `useApi`/`useApiRequest` for modal/drawer workflows. Reference `docs/RULES_vue-form-useApi.md` for contract details.

## 7) Testing and Quality Gates

Current status:

- Local quality scripts exist.
- GitHub workflow enforcement is disabled; quality workflows are not currently present as active CI merge gates.

Minimum expected checks before merge:

- `./scripts/dev.sh test`
- `./scripts/dev.sh npm run type-check`
- `./scripts/dev.sh npm run lint`

Transition policy while CI is inactive:

- local checks are the active quality gate
- PR reviewers treat missing local-check evidence as a release risk
- no "green CI" assumption is valid until workflows are re-enabled

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
