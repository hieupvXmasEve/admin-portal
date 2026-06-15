# Design

## Domain Model

No new model. Search reuses existing resolver behavior and maps each target to
the owning student.

Target types:

- student
- invoice
- payment
- charge
- dng

## Application Flow

1. Command palette debounces user input.
2. Frontend calls `GET /finance/search?q=...`.
3. Controller invokes `ResolveFinanceAuditSearchQuery` with current campus.
4. Response maps single/ambiguous matches to Student 360 URLs.
5. Palette navigates with Inertia router.

## Interface Contract

Route:

- `GET /finance/search`
- Name: `finance.search`
- Middleware: `can:view_finance_student_overview`
- Envelope: `ApiResponse::success()`

Result shape:

- `type`
- `id` as owning student id
- `label`
- `sublabel`
- `url`

## Data Model

No schema changes.

## UI / Platform Impact

Adds `resources/js/components/finance/FinanceCommandPalette.vue`. It is mounted
by the topbar story, not by this endpoint story alone.

## Observability

No audit log required for read-only search. Feature tests prove permission and
campus filtering.

## Alternatives Considered

1. Query each model directly in the controller.
   - Rejected because the Audit Workspace resolver already owns deterministic
     search precedence and campus scoping.
2. Navigate non-student targets to their technical pages.
   - Rejected for Milestone 1 because design requires Student 360 as the global
     search destination.
