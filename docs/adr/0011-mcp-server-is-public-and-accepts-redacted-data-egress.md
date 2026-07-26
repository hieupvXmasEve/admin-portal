---
id: ADR-0011
title: "Public OAuth-protected MCP server, accepting redacted data egress to client clouds"
status: accepted
owner: Platform Team
last_verified: 2026-07-26
scope: architecture-decision
---

# Public OAuth-protected MCP server, accepting redacted data egress to client clouds

The Controlled MCP server is exposed as a publicly reachable, OAuth-protected HTTPS endpoint, and we accept that permission-gated Swinx data — including a named student's full academic and finance profile via `get_entity_profile` — will leave to the calling client's cloud (OpenAI for ChatGPT, Anthropic for Claude). We chose this over a network-private, internal-only server because cloud agent clients such as ChatGPT can only connect inbound to a public endpoint, and excluding them would defeat the staff-facing goal of [0009](0009-mcp-server-replaces-staff-copilot-chat-ui.md). All three existing tools (`query_metrics`, `search_entities`, `get_entity_profile`) are exposed uniformly to every client; the data a staff member can extract is bounded only by their own Swinx permissions, which they already hold in the product.

Mitigations are access control and audit, not network isolation: per-user OAuth, Swinx permission and campus checks, and a per-call `AiToolCall` audit record (with nullable conversation/trace FKs plus `channel`, actor, and client identity). We deliberately add **no egress-side redaction layer** — the payload a client receives is exactly what the staff member is permission-gated to see, and the exposed tool/profile sections already do not surface `national_id`; the existing redactor only scrubs secrets/identifiers from *audit* evidence. The server is a hard read-only surface — no write tools without a separate ADR — and must validate `Host`/`Origin` and rate-limit to resist DNS-rebinding and abuse on the public endpoint.

The product owner accepts external client-cloud egress under the permission,
campus, allowlist, audit, read-only, origin, and rate-limit controls above.
Compatible clients, including Claude, ChatGPT, and Codex, may be enabled when
they satisfy the MCP and OAuth runtime contract.
