# Design

## Domain Model

No domain model changes.

## Application Flow

1. `AppSidebarHeader.vue` imports command palette and semester switcher.
2. `usePermissions().canAny(...)` determines whether to show Finance shell
   affordances.
3. Finance users see both controls in the topbar.
4. Non-Finance users keep the existing header behavior.

## Interface Contract

Frontend component:

- `resources/js/components/AppSidebarHeader.vue`

Permission gate:

- `view_finance_student_overview`
- `view_finance_audit_workspace`
- `view_finance_operations_dashboard`

## Data Model

No schema changes.

## UI / Platform Impact

Visible topbar change for Finance users. It depends on previous child stories:

- route constants
- permissions
- Student 360 route
- search endpoint/component
- semester context/component
- sidebar IA for final smoke

## Observability

No audit logs. This story owns final milestone evidence and Harness trace.

## Alternatives Considered

1. Mount controls only on Finance pages.
   - Rejected because the design calls for an app-shell affordance that is
     always available to Finance users.
2. Show controls to every authenticated user.
   - Rejected because search exposes student finance identity.
