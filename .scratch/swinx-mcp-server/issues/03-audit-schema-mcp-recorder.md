# 03 — Audit schema + MCP audit recorder

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

Extend the AI tool-call audit so an MCP call can be persisted as a standalone
record, and add the recorder that writes it.

Make the conversation and agent-trace foreign keys nullable, and add three
columns: `channel` (string, default the chat value), `actor_user_id` (nullable FK
to users), and `mcp_client_id` (nullable string — the OAuth client id). Add the
actor relation and an MCP query scope to the model.

Add a new MCP audit recorder that writes one standalone row per MCP call —
channel set to the MCP value, null conversation/trace links, actor user id, OAuth
client id — by reading the frozen result-object accessors (permission result,
record count, source references, safe error code, status, campus-scope snapshot,
hidden sections). Reuse the existing redactor for the audit fields only; there is
no egress redaction of tool output. Leave the inner tools' own audit hook dormant
(it early-returns with no trace) — the MCP wrapper is the sole MCP audit source.

This slice is verifiable in isolation via the recorder; the wrapper that calls it
arrives in issue 04.

## Acceptance criteria

- [ ] Migration runs: conversation/trace FKs are nullable; `channel`, `actor_user_id`, `mcp_client_id` columns exist.
- [ ] The model exposes the actor relation and an MCP scope; new columns are fillable/cast.
- [ ] The MCP recorder writes a standalone row (MCP channel, null conversation/trace, actor, client id) with the evidence fields, driven from a result object in a test.
- [ ] Existing chat-channel rows and behavior are unaffected; no backfill performed.
- [ ] The existing audit foundation test stays green.

## Blocked by

- None — can start immediately (parallel to issues 01 and 02).
