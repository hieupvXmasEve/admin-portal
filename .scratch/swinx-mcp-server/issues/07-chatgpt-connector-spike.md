# 07 — ChatGPT connector spike + finalize v1 client list

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

Measure the real gap to a second client without disturbing the shipped Claude
path. Configure a ChatGPT custom MCP connector against the public endpoint and
attempt the OAuth + tool-call flow. Record gaps (consent screen, supported
scopes, resource indicators, token audience/expiry). If gaps are small, add the
metadata/fixes; if large, record the deferral. Update the relevant ADRs and the
glossary with the finalized v1 client list and the data-handling precondition
status.

This is a documented go/no-go, not a blocking dependency for the Claude path.

## Acceptance criteria

- [ ] A reproducible outcome is recorded: ChatGPT connects and calls a tool, or a precise list of blocking gaps is documented.
- [ ] Any small metadata fixes are applied; large gaps are recorded as a deferral.
- [ ] ADRs and the glossary reflect the finalized v1 client list and precondition status.
- [ ] The Claude path is unaffected.

## Blocked by

- Issue 06 (hardened, stable public endpoint)
