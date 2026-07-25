---
id: ADR-0009
title: "MCP server replaces the Staff Copilot chat UI as Swinx's AI surface"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# MCP server replaces the Staff Copilot chat UI as Swinx's AI surface

Swinx will stop building its own Staff Copilot chat UI and instead deliver AI access through a Controlled MCP server that authorized staff reach from external agent clients (Claude, ChatGPT, Codex). We chose this over continuing the in-house chat experience because the agent clients already provide planning, multi-tool orchestration, and natural-language synthesis for free, and reusing them lets us validate the existing ToolDispatcher and tool catalog against real agents far faster than hardening a bespoke UI. The staff-facing audience is unchanged in principle — Academic and Finance staff and leadership — but they now consume Swinx data through a client they already run, not a Swinx-built screen.

The chat UI and the entire Swinx-side synthesis stack (planner agent, final-answer agent, `laravel/ai` providers, `AiProviderSetting`, and its per-user connected-provider requirement) are frozen in place, not deleted, so the decision is recoverable if MCP clients prove unworkable for non-technical staff. Over MCP the external client's model performs synthesis, so a Swinx-side connected provider is not required. The internal `ToolDispatcher` remains the enforcement boundary per [0008](0008-swinx-tooldispatcher-is-ai-execution-boundary.md), and MCP maps only its read-only tools outward.

Claude is the sole committed v1 client. The ChatGPT connector is technically feasible with the pinned `laravel/mcp` OAuth metadata, but remains deferred until its cloud egress is covered by the data-handling precondition in [0011](0011-mcp-server-is-public-and-accepts-redacted-data-egress.md). Risk accepted: `laravel/mcp` is pinned and wrapped in Swinx `Host`/`Origin`, rate-limit, and authentication middleware. Staff must own a capable approved agent-client account to use the surface.
