# Exec Plan

## Goal

Make the Finance object detail pages a documented, tested secondary contract:
backend routes are kept, primary navigation is task-first, and detail pages serve
deep-link, audit, and repair workflows.

## Scope

In scope:

- Inventory current charge, invoice, payment, and DNG request detail routes.
- Add route contract coverage for existence, route names, route model bindings,
  and read permissions.
- Add or adjust tests for deep-link generation from Lookup, Audit Workspace,
  Student 360/Cockpit/DNG surfaces as needed.
- Check primary navigation so record-specific detail pages are not promoted as
  sidebar/task-first destinations.
- Preserve existing repair action routes and stricter permissions.
- Update affected Finance docs/story evidence if implementation changes route
  wording, labels, or link sources.

Out of scope:

- Removing, renaming, or redirecting the detail routes.
- Money math, ledger calculations, DNG provider calls, webhook/reconciliation
  state machines, settlement allocation algorithms, or charge generation rules.
- New migrations or production data repair.
- Student or lecturer API/portal changes.

## Risk Classification

Risk flags:

- Authorization: detail and repair routes have separate permission gates.
- Audit/security: detail links are used as evidence and repair entrypoints.
- Public contracts: named routes are consumed by Inertia/Ziggy and internal
  deep links.
- Cross-platform/deep links: bookmarked/admin deep links must remain stable.
- Existing behavior: M5/M6 already link to these routes.
- Weak proof: route removal can silently pass if only UI paths are tested.
- Multi-domain: charges, invoices, payments, and DNG are separate Finance
  subdomains.

Hard gates:

- Do not delete or rename backend detail routes.
- Do not loosen read or repair permissions.
- Do not change money/DNG state behavior while reclassifying navigation role.

## Work Phases

1. **Discovery** — confirm current route list, middleware, controller handlers,
   route helper usage, and deep-link sources.
2. **Contract tests** — add feature tests for route existence, permission
   gates, and model binding for the four detail routes.
3. **Deep-link checks** — verify Lookup, Audit Workspace, DNG/webhook, Cockpit,
   and Student 360 links use named routes and still resolve.
4. **Navigation role** — remove/promote no detail route in primary sidebar;
   keep links contextual from selected records/evidence only.
5. **Repair guard pass** — confirm repair actions under detail pages still use
   their existing stricter permissions.
6. **Verification** — run targeted PHP tests, targeted frontend lint when Vue/TS
   changes exist, route-list check, and read-only invariant sanity if any money
   repair surface was touched.
7. **Harness evidence** — update this story's validation evidence and record a
   Harness trace.

## Stop Conditions

Pause for human confirmation if:

- A route name, URL, or permission must change to implement the requested role.
- A detail route appears truly unreachable or unsafe and removal/redirect seems
  necessary.
- A repair action needs new money math, new DNG state behavior, or a schema
  change.
- Validation would require weakening permission or audit expectations.
- Student/lecturer API or portal behavior becomes affected.
