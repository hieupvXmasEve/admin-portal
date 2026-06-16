# Design

## Domain Model

M6 is a **navigation and operational cutover story**. It does not introduce new
Finance domain objects, money calculations, schema, or write actions.

The main artifact is a cutover inventory:

```text
current entrypoint -> new destination -> keep | hide | redirect | deep-link | retire
```

Definitions:

- **keep:** The surface remains a first-class operator page in the new structure.
- **hide:** The route remains callable but is removed from normal navigation.
- **redirect:** The route redirects to a new primary destination.
- **deep-link:** The route remains reachable from Audit, Student 360, row actions,
  or repair workflows, but not from the main sidebar.
- **retire:** The route/page can be removed only if no route constants, tests,
  deep links, or operator repair paths still use it.

## Application Flow

### Cutover inventory

1. Read the current Finance routes, `financeRoutes`, and `menu-sidebar.ts`.
2. Compare each entrypoint against the M1-M5 destinations.
3. Assign a cutover decision and a reason.
4. Identify missing route constants, stale literal paths, or duplicated labels.

### Navigation cutover

1. Keep the task-first sidebar as the default Finance Office path.
2. Route **Hôm nay** to Cockpit.
3. Route bulk work to Batch Studio where possible.
4. Route object lookup to the M5 lookup pages.
5. Keep detail pages reachable from Audit/Student 360/Lookup actions.

### Operator UAT

Run UAT as role-aware walkthroughs:

- Finance staff (`can_bo`) daily triage.
- Finance lead (`truong_phong`) review and all workflow visibility.
- Restricted finance user with missing action permissions.
- All-campus-capable user, if the role exists in the environment.

## Interface Contract

### Web routes

Route changes must be minimal and explicit:

- Prefer menu/route-helper cutover before redirects.
- Add redirects only when an old URL is likely bookmarked or linked.
- Do not remove detail routes used by `FinanceAuditWorkspaceController`,
  `LookupRowActions`, `Student 360`, DNG detail pages, or export flows.

### Frontend

- Use existing `financeRoutes` helpers and `FINANCE_ROUTE_NAMES`.
- No new literal Finance URLs in Vue.
- Preserve permission-aware menu rendering.
- Keep loading, empty, and denied states explicit on cutover destinations.

### Authorization

- Menu visibility must match route permission gates.
- Missing permission means the action is hidden, or disabled with a reason when
  the operator is already inside the relevant context.
- No cutover may make a previously protected route reachable without its
  existing `can:` middleware.

## Data Model

No migrations or data changes.

## UI / Platform Impact

Likely touched surfaces:

- `resources/js/constants/menu-sidebar.ts`
- `resources/js/constants/finance-routes.ts`
- `resources/js/utils/routes.ts`
- Existing Finance page links or row actions that still point to old starts.
- Story validation docs and cutover inventory docs.

Potential backend changes:

- Route redirects for old bookmarked entrypoints, only when necessary.
- Controller/index copy or route names only if an old route now clearly hands off
  to a new destination.

## Observability

M6 must record:

- Route/menu/page inventory.
- Permission matrix for visible Finance Office entrypoints.
- UAT checklist results for core workflows.
- Targeted tests and lint output.
- `finance:audit-invariants` sanity output if any route/controller change touches
  a write-capable Finance surface.

## Alternatives Considered

1. **Stop after M5.** Rejected because implemented surfaces can still be hidden
   behind old navigation and duplicated entrypoints.
2. **Delete all old pages aggressively.** Rejected because Audit, repair, exports,
   and bookmarked operator paths may still need detail/deep-link routes.
3. **Do UAT without a route inventory.** Rejected because the goal is full module
   redesign cutover, not a few happy-path clicks.
