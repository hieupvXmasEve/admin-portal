# Design

## Domain Model

No new Finance model. The context is:

- `current_semester_id` in session.
- Shared Inertia prop containing selected id and semester options.

## Application Flow

1. `HandleInertiaRequests` shares `semester` only for Finance users.
2. If no session semester is selected, the active semester is selected.
3. `SemesterSwitcher` reads `page.props.semester`.
4. Switcher posts to `/finance/semester-context`.
5. Controller stores the new session value and redirects back.

## Interface Contract

Route:

- `POST /finance/semester-context`
- Name: `finance.semester-context.update`

Shared prop:

- `semester.selected_id`
- `semester.options[]`

Frontend type:

- `SemesterContext`

## Data Model

No schema changes. Existing `semesters` table is read for options.

## UI / Platform Impact

Adds `resources/js/components/finance/SemesterSwitcher.vue`. It is mounted by
the topbar story.

## Observability

No audit log required; the route only writes the user's session.

## Alternatives Considered

1. Keep independent page filters.
   - Rejected because future cockpit/batch surfaces need one visible context.
2. Persist a user preference table.
   - Rejected for Milestone 1 because session state is enough for operator
     context.
