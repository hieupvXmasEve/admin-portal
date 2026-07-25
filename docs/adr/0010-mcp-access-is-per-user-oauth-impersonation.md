---
id: ADR-0010
title: "MCP access is per-user OAuth impersonation, not service tokens"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# MCP access is per-user OAuth impersonation, not service tokens

Each MCP connection authenticates as a specific staff member through per-user OAuth, and every tool call runs with that staff member's own Swinx permissions and campus scope. We chose this over the per-agent "scoped service token" model the glossary originally described because staff genuinely differ in what they may see — a Cán Bộ Đào tạo and a BOD member, or two campuses, must not collapse into one shared machine identity — and because the existing tool layer already keys every permission and campus check off a real `User`. Campus is no longer a session value: it becomes an explicit, validated tool argument (a caller may only pass campuses they are permitted, and an **All-campus AI scope** holder may span or omit it), with clarification-first behavior when no single campus resolves.

A connected client inherits the staff member's **full** permission set — there is no per-client capability narrowing — but each client's authorization is independently revocable and audited. This requires an OAuth2 authorization-server capability Sanctum cannot provide (it issues opaque bearer tokens with no authorize/consent/registration flow); **Laravel Passport is approved** as that authorization server and coexists with Sanctum through a dedicated `api` guard — Sanctum keeps the SPA and existing APIs, Passport serves only the MCP resource server. `laravel/mcp`'s `oauthRoutes()` scaffolds discovery, dynamic client registration (RFC 7591), and PKCE authorization-code on top of Passport. Consequence: this supersedes the **Scoped MCP service token** glossary term (now **MCP client authorization**).

Claude is the committed v1 client. A connector spike confirmed that ChatGPT needs no additional OAuth metadata from the pinned build, but Passport does not audience-bind tokens to the RFC 8707 `resource` parameter. Tightening that accepted residual requires a separate decision; committing ChatGPT remains gated by [0011](0011-mcp-server-is-public-and-accepts-redacted-data-egress.md), not engineering work.
