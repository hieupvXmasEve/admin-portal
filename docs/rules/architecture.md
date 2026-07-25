---
title: Architecture Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - app/Modules
  - app/Shared
  - app/Models
  - routes
---

# Architecture Rules

Swinx is a hybrid modular monolith. New domain behavior belongs in
`app/Modules/{Domain}`; shared legacy services and models remain only where the
current migration boundary requires them.

## Ownership and boundaries

- Each table and business invariant has one owning module.
- Controllers are adapters: validate, authorize, invoke an Action or Query, and
  return an Inertia or API response.
- State-changing business use cases belong in `Actions/`; complex read behavior
  belongs in `Queries/`.
- Modules must not import another module's internal models, Actions, Queries, or
  concrete implementations.
- Cross-module interactions use contracts, domain events, or explicit
  projections. See [contracts.md](contracts.md) and ADR-0043.
- Do not use cross-module Eloquent joins.
- Frontend code renders backend-owned business state; it does not redefine
  business rules.

`Student` is a Shared Kernel identity reference. Academic and Finance are
separate bounded contexts: Academic owns the academic lifecycle and Finance
owns money. They do not share money or lifecycle Eloquent models. See ADR-0026.

## Module shape

```text
app/Modules/{Domain}/
├── Actions/
├── Queries/
├── Http/
│   ├── Web/{Actor}/
│   ├── Api/{Actor}/
│   └── Requests/{Domain}/
├── Models/                 # only when this module owns the model
├── Policies/
├── Providers/
└── routes/
    ├── web.php
    └── api.php
```

Shared cross-module interfaces live in
`app/Shared/Contracts/{Domain}/`. Shared framework plumbing may remain in
Laravel's standard directories.

## New work

1. Identify the owning domain and the business use case.
2. Update the owning model and migration; do not create a new shared model to
   bypass a context boundary.
3. Add an Action or Query, a FormRequest where input is accepted, and a thin
   controller.
4. Register routes in the owning module.
5. Add a page only when the web flow needs one, keeping the route-to-page
   mapping explicit.
6. Add a contract before another module consumes the capability.

New business logic, controllers, and routes must not be added to legacy
top-level locations. See [legacy-migration.md](legacy-migration.md).

## Domain invariants

### Admissions

Admissions approval remains one atomic cross-context orchestration: it converts
the pending application into the required Identity and Academic records in one
transactional business outcome. Context ownership and orchestration are defined
by ADR-0042.

### Notification V2

- Notification domain logic stays in `app/Modules/Notification`.
- Persist domain events to `notification_event_outbox` before dispatch.
- Use `recipient_user_id` as the canonical recipient; resolve Student or
  Lecturer targets before persistence.
- Keep `campus_id` scope consistent from event through message and delivery.
- Use `notifications:process-outbox`; do not write deliveries directly.
- Phase 1 uses the V2 tables without a legacy `notifications` backfill.

### Facilities

Facilities owns room-booking routes and new booking behavior while preserving
the public route names and URLs. Existing shared legacy operations may remain
until their next intentional migration. See ADR-0047.
