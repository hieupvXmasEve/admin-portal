# Design

## Domain Model

No Finance money model changes. This story adds permission vocabulary:

- `view_finance_student_overview`: read access to Student 360 and global Finance
  search.
- `view_finance_all_campus`: scope escalation that allows cross-campus Student
  360 visibility.

## Application Flow

1. Permissions are declared in `config/permission.php`.
2. Seeder sync creates permission rows.
3. Role mappings grant overview access to approved finance roles.
4. Later controllers and UI gates consume the permissions.

## Interface Contract

Permission strings:

- `view_finance_student_overview`
- `view_finance_all_campus`

These strings are consumed by route middleware, `PermissionService`, and frontend
permission gates.

## Data Model

Permission rows are inserted or synced through existing seeders. No schema
change.

## UI / Platform Impact

No visible UI by itself. Later stories hide or show Student 360/search/topbar
affordances based on these permissions.

## Observability

Seeder output and tinker count are the operational proof. No audit log is needed
because this story defines permissions, not user assignments.

## Alternatives Considered

1. Reuse `view_finance_audit_workspace`.
   - Rejected because Student 360/search is a distinct staff surface.
2. Give all finance users all-campus lookup.
   - Rejected because campus scoping is a hard safety boundary.
