# 02 — Prefactor: campus as an explicit argument in the validator

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

A regression-sensitive prefactor, isolated so it can be reviewed on its own:
make the query-plan validation path accept an **explicit** campus instead of only
reading it from the session/container, **without changing web-chat behavior**.

Today the validator falls back to the session campus and denies any campus filter
that mismatches that single session campus. Refactor so a caller may pass the
campus explicitly; the web-chat path must keep resolving its campus exactly as it
does now (prove no caller depends on the implicit fallback, or preserve the
fallback). Introduce the multi-campus capable scope-snapshot shape and add a new
`clarification_required` safe error code, so the later MCP campus resolver and the
audit recorder code against a stable shape. No MCP wiring in this slice.

## Acceptance criteria

- [ ] The validator accepts an explicitly supplied campus and validates a campus filter against it.
- [ ] Web-chat campus resolution is unchanged (regression test asserts behavior with no explicit campus supplied).
- [ ] The scope-snapshot shape can represent a span of multiple campuses.
- [ ] A `clarification_required` safe error code exists and is documented as a clarification result, not a deny.
- [ ] The existing AI feature suite stays green (`--filter=Ai`).

## Blocked by

- None — can start immediately (parallel to issue 01).
