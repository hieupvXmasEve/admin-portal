# 05 — Remaining tools over MCP: `search_entities` + `get_entity_profile`

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

The two remaining read-only tools, reusing the identity/campus/audit harness from
issue 04. Both follow the same wrapper shape (resolve actor → resolve campus →
dispatch → audit → map to structured JSON/evidence with safe-error mapping) and
register under their canonical names.

- **`search_entities`** — canonical name `search_entities`; gating is
  **per entity type** (e.g. the student-view permission), not a flat metrics
  permission.
- **`get_entity_profile`** — canonical name `get_entity_profile`; gating is the
  AI-metrics permission plus the student-view permission plus **per-section**
  permissions, returning only permitted sections with a hidden-sections list for
  what was withheld. Confirm entity-reference resolution works **session-free**:
  a reference is bound to campus + expiry + catalog version (not the issuing
  user) and is re-checked for the student-view permission and campus on resolve,
  so it stays permission-gated. Do not claim per-user binding.

After this slice all three canonical tools are registered and the `ping` stub is
removed.

## Acceptance criteria

- [ ] `search_entities` is callable over MCP with per-entity-type gating; in/out-of-scope campus behaves correctly; structured JSON + evidence; safe-error mapping.
- [ ] `get_entity_profile` is callable over MCP; per-section permissions yield partial sections + a hidden-sections list.
- [ ] Entity-reference resolution works without a session and remains campus + permission gated.
- [ ] Each call writes exactly one MCP-channel audit row with actor and client id.
- [ ] Both tools reach data only through `ToolDispatcher`.
- [ ] The MCP inspector lists all three canonical tools and executes each; the `ping` stub is removed.

## Blocked by

- Issue 04 (`query_metrics` end-to-end harness)
