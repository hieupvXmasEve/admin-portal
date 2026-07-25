---
id: ADR-0047
title: "Facilities owns room-booking routes and new behavior"
status: accepted
date: 2026-05-31
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Facilities owns room-booking routes and new behavior

## Context

Room booking grew beyond one legacy controller into series creation,
availability queries, conflict preview, clone drafts, and an availability
board. Splitting the same workflow between legacy and module route owners would
make ownership ambiguous.

## Decision

Facilities owns the room-booking web route surface:

- `App\Modules\Facilities\Providers\FacilitiesServiceProvider` loads
  `app/Modules/Facilities/routes/web.php`;
- existing public route names and URLs remain stable;
- availability-board and series-preview routes live with the module;
- new series, availability, clone, validation, and request behavior lives under
  `app/Modules/Facilities`;
- existing shared legacy services and models may remain until their operations
  are intentionally migrated.

## Consequences

One module owns the user workflow without breaking existing navigation or Ziggy
consumers. Remaining legacy booking behavior migrates when the next change
touches that workflow.
