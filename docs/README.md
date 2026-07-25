---
title: Swinx Documentation Registry
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: documentation-registry
---

# Swinx Documentation Registry

Only documents listed here or in a linked API index are canonical. A Markdown
file outside this registry must be reviewed before agents use it as authority.

## Entry points

| Path | Purpose | Read when |
| --- | --- | --- |
| `AGENTS.md` | Agent operations, source precedence, task routing | Every agent task |
| `README.md` | Human repository onboarding | Starting local work |
| `CONTEXT.md` | Stable domain vocabulary | Naming domain concepts |

## Core

| Path | Owner | Purpose |
| --- | --- | --- |
| `docs/project-overview-pdr.md` | Product / Platform | Current product capabilities and constraints |
| `docs/system-architecture.md` | Platform | Current architecture and ownership boundaries |
| `docs/design-guidelines.md` | Frontend | Visual language, accessibility, layout |
| `docs/deployment-guide.md` | Platform | Environment and deployment operations |
| `docs/portal-repos.md` | Platform | Swinx-to-Nuxt cross-repository workflow |

## Implementation rules

Read [rules/README.md](rules/README.md) for task routing.

Canonical rule files:

- `rules/architecture.md`
- `rules/backend.md`
- `rules/contracts.md`
- `rules/filtering.md`
- `rules/frontend.md`
- `rules/legacy-migration.md`
- `rules/naming.md`
- `rules/realtime.md`
- `rules/security.md`
- `rules/testing.md`

## Decisions

`docs/adr/` contains accepted and currently applicable decisions. ADRs explain
durable trade-offs; they do not track work or retain superseded history.

## API contracts

- [API index](api/README.md)
- [Admissions](api/admissions/ingestion.md)
- [Student API](api/student/README.md)
- [Lecturer API](api/lecturer/README.md)
- [Notifications](api/notifications.md)

## Integration and operations

| Path | Purpose |
| --- | --- |
| `features/academic/grading.md` | Grading engines and Metropolia scheme pack |
| `features/ai/mcp.md` | Controlled MCP deployment and troubleshooting |
| `features/canvas/integration.md` | Canvas boundary and contract |
| `features/canvas/operations.md` | Canvas sync operations |
| `features/email/template-contract.md` | Email template contract |
| `features/email/operations.md` | Email configuration and troubleshooting |
| `features/excel/exports.md` | Excel export memory and streaming practices |
| `features/finance/dng.md` | DNG payment-provider integration |
| `features/logs/audit-logging.md` | Activity and audit logging |
| `features/notification/operations.md` | Notification delivery and realtime operations |
| `features/upload/storage.md` | Upload and storage operations |

## Local issue tracking

- `docs/agents/issue-tracker.md`
- `docs/agents/triage-labels.md`

The `.scratch/` tracker is intentionally outside this documentation cleanup and
will be reviewed separately.

## Metadata contract

Canonical Markdown starts with YAML frontmatter containing:

```yaml
---
title: Document title
status: canonical
owner: Owning team
last_verified: YYYY-MM-DD
scope: concise-scope
---
```

API documents may add `portal_impact` and `source_routes`.

## Maintenance

- Update the owning canonical document in the same change as a contract change.
- Reference existing knowledge instead of copying it.
- Delete obsolete documentation; Git is the history.
- Do not add repository plans, reports, stories, archives, framework copies, or
  tool-specific agent instruction files.
- Run `./scripts/check-docs.sh` after documentation changes.
