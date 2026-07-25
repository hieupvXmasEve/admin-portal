---
id: ADR-0008
title: "Swinx ToolDispatcher is the AI execution boundary"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Swinx ToolDispatcher is the AI execution boundary

AI providers may propose structured tool calls for Staff Copilot, but Swinx executes every data-access step through its own ToolDispatcher. We chose this over provider-native tools or direct provider access to application APIs because Swinx must validate permissions, campus scope, domain data permissions, schemas, limits, redaction, and audit evidence before any Academic or Finance data is queried.

The provider never receives database credentials, source API credentials, unrestricted tool access, or authority to call Swinx data sources directly. MCP may map internal tools outward, but the internal ToolDispatcher remains the product enforcement boundary.

Every AI data-access call persists redacted technical evidence after permission and campus validation, including tool identity, arguments, access result, scope, source references, safe errors, and result metadata. Provider synthesis never grants access, decides permissions, or replaces this backend audit.
