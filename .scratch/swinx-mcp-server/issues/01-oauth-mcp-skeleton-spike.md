# 01 — Spike: OAuth + MCP skeleton + `ping`

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

The enabling tracer bullet for the transport: prove that a real MCP client can
complete the OAuth round-trip against Swinx and call a read-only stub tool.

Install Laravel Passport and add an `api` guard (Passport driver, users provider)
**alongside** the existing session (`web`) and student (`sanctum`) guards, leaving
the default guard and the student guard untouched. Stand up the MCP server with a
single read-only `ping` tool registered under the canonical name `ping`. Wire up
Passport's OAuth authorize/token routes plus the MCP discovery + dynamic client
registration (DCR) routes, and carve the unauthenticated DCR + `.well-known`
routes out of CSRF so they don't 419. Render a minimal Swinx-branded consent
screen that names the requesting client (marked third-party), shows the redirect
URI, and lists the access being granted.

Freeze the two interfaces the later slices code against: the actor/campus
acquisition contract (actor resolved from the OAuth guard inside the tool;
campus from a validated argument) and the result-object → audit-row field map.

This is a hard pass/fail spike. If the OAuth round-trip cannot be achieved, stop
and re-evaluate the MCP dependency — there is no fallback in v1.

## Acceptance criteria

- [ ] `laravel/mcp` and `laravel/passport` are direct composer dependencies (mcp promoted from transitive).
- [ ] The `api` guard resolves; the existing `web` and `student` guards still resolve (guard regression test added).
- [ ] OAuth discovery metadata advertises authorization, token, and registration endpoints; the DCR endpoint returns a client id (not 419).
- [ ] The branded consent screen renders with client name (third-party label), redirect URI, and granted access.
- [ ] The MCP inspector completes the OAuth round-trip and calls `ping` (required, automated gate).
- [ ] Optional: a real Claude Desktop connector completes OAuth and calls `ping`.
- [ ] The frozen actor/campus and result→audit contracts are committed as stubs for later slices.
- [ ] The existing AI feature suite stays green.

## Blocked by

- None — can start immediately.
