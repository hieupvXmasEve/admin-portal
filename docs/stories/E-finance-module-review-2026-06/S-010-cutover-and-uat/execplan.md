# Exec Plan

## Goal

Make the implemented Finance Office M1-M5 redesign the primary operational
surface by cutting over navigation, documenting legacy entrypoint decisions, and
recording role-based UAT evidence.

## Scope

In scope:

- Build a Finance route/menu/page cutover inventory.
- Update the Finance epic README and any affected validation docs.
- Repoint or hide stale sidebar/menu entries that compete with M1-M5.
- Add safe redirects for legacy bookmarked starts only when needed.
- Verify route constants and Vue links use `financeRoutes` / `route(...)`.
- Run role-based UAT checklists for the main Finance Office workflows.
- Record targeted automated proof and manual UAT evidence.

Out of scope:

- `S-011-student-360-installment-plan-guards`.
- New money logic, schema, production data repair, or settlement/DNG rewrites.
- Removing detail routes that are still used by Audit/Lookup/Student 360.
- Broad UI redesign beyond cutover polish and missing states.

## Risk Classification

Risk flags:

- Authorization — menu visibility and route gates must stay aligned.
- Existing behavior — old bookmarked or repair URLs may still be used.
- Weak proof — workflow UAT must complement automated route tests.

Lane: normal.

Hard gates:

- No route loses required `can:` middleware.
- No Finance write path changes without targeted tests and audit-invariant
  sanity evidence.
- No old route is retired until deep-link, export, repair, and test references
  are checked.
- Portal impact remains `none`.

## Work Phases

1. **Inventory** — list Finance web/API routes, route constants, sidebar items,
   and major pages; create the cutover table.
2. **Decision pass** — assign `keep|hide|redirect|deep-link|retire` and record
   the reason for each legacy entrypoint.
3. **Navigation cutover** — update sidebar/route helper usage and add redirects
   only where justified.
4. **Permission pass** — verify visible UI entries match backend gates for staff,
   lead, restricted, and all-campus contexts.
5. **UAT pass** — walk through daily triage, charge generation, DNG push,
   reminders, settlement/allocation, lifecycle exceptions, Student 360 lookup,
   and Audit drilldown.
6. **Validation** — run targeted tests, lint, route-list checks, and
   `finance:audit-invariants` if any write-capable path was touched.
7. **Evidence** — update `validation.md`, Harness status, and any backlog items
   for unresolved legacy surfaces.

## Stop Conditions

Pause for human confirmation if:

- A legacy route is still actively used but has no clear new destination.
- A proposed redirect would remove a repair/audit/export path.
- An old page exposes a write flow that M1-M5 did not replace.
- UAT finds permission mismatch between menu visibility and route middleware.
- The cutover would touch student or lecturer portal API contracts.

## Suggested Inventory Columns

| Current entrypoint | Current permission | New destination | Decision | Reason | Validation |
| --- | --- | --- | --- | --- | --- |
| `/finance/operations/dashboard` | `view_finance_operations_dashboard` | `/finance/cockpit` or deep-link | TBD | TBD | route/menu/UAT |

Replace `TBD` before implementation starts.
