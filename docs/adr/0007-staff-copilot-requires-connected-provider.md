# Staff Copilot requires a connected provider

> **Status:** Superseded for the MCP direction by [0009](0009-mcp-server-replaces-staff-copilot-chat-ui.md). This requirement only applied to the Swinx-hosted chat UI, where Swinx ran the provider to synthesize answers. Over MCP the *client's* model performs synthesis, so no Swinx-side connected provider is involved. Retained for historical context and in case the chat UI is unfrozen.

Staff Copilot in the staff/admin web app requires each staff member to connect and successfully test an AI provider before they can submit prompts or run AI-assisted data queries. We chose this over deterministic staff-facing fallback because the product experience depends on model-assisted planning, multi-tool orchestration, analysis, and natural-language synthesis; running a separate non-provider answer path would create different behavior, weaker evaluation coverage, and confusing support expectations.

Provider connection only enables the assistant experience. Swinx permissions, campus scope, domain data permissions, tool schemas, redaction, and audit still decide what data can be queried, and a connected provider never grants data access by itself.
