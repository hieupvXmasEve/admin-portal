---
title: Engineering Rules Index
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - repository
---

# Engineering Rules Index

This directory is the canonical home for project-specific engineering rules.
Repository-wide operating and safety instructions remain in `AGENTS.md`;
architectural rationale remains in `docs/adr/`.

Read the smallest relevant set before changing code:

| Area | Rule |
|---|---|
| Module boundaries and ownership | [architecture.md](architecture.md) |
| Laravel and PHP implementation | [backend.md](backend.md) |
| Cross-module interfaces | [contracts.md](contracts.md) |
| Server-filtered list pages | [filtering.md](filtering.md) |
| Vue, Inertia, forms, and UI | [frontend.md](frontend.md) |
| Moving legacy code into modules | [legacy-migration.md](legacy-migration.md) |
| Names, paths, routes, and contracts | [naming.md](naming.md) |
| Broadcasting and notifications | [realtime.md](realtime.md) |
| Authentication and authorization | [security.md](security.md) |
| Tests and scoped quality gates | [testing.md](testing.md) |

## Task routes

- New backend use case: `architecture.md`, `backend.md`, `naming.md`.
- Cross-module read or write: add `contracts.md`.
- Inertia or Vue work: `frontend.md`.
- Filtered, sorted, or paginated list: add `filtering.md`.
- Existing code in a legacy location: add `legacy-migration.md`.
- Auth-sensitive change: add `security.md`.
- Broadcast or notification change: add `realtime.md`.
- Any implementation change: finish with `testing.md`.

Do not recreate separate rule sources elsewhere. When a rule changes, update
its canonical file here and link to it.
