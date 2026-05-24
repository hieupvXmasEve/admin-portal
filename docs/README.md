# Docs Index

Last updated: 2026-04-26
Source of truth: `docs/`

## AI Quick-Start

- See `AGENTS.md` at the repo root — single source of truth for all AI agents (rules, commands, forbidden patterns, harness flow).

## Core Baseline

- [Project Overview & PDR](./project-overview-pdr.md)
- [Codebase Summary](./codebase-summary.md)
- [Code Standards](./code-standards.md)
- [System Architecture](./system-architecture.md)
- [Project Roadmap](./project-roadmap.md)
- [Deployment Guide](./deployment-guide.md)
- [Design Guidelines](./design-guidelines.md)

## Engineering References

- [DB Flow](./db-flow.md)
- [Vue Form + useApi Rules](./RULES_vue-form-useApi.md)
- [Excel Export Service](./excel-export-service.md)
- [Excel Memory Optimization](./excel-memory-optimization.md)
- Finance engineering: [Tuition Settlement Model v2](./features/finance/tuition-settlement-model-v2.md)

## Domain Documentation (Folders)

- Rules: `docs/rules/` — see [Rules Index](./rules/README.md)
- API docs: `docs/api/`
- Notification docs: `docs/features/notification/`
- Canvas docs: `docs/features/canvas/`
- Upload docs: `docs/features/upload/`
- Logging docs: `docs/features/logs/`

## Maintenance Rules

- Keep docs factual and code-verified.
- Update docs in the same change window as behavior/route/contract changes.
- Keep unresolved decisions in an `Unresolved Questions` section.
- Keep markdown docs concise and below 800 LOC when possible.
- Prefer kebab-case for new doc filenames.
