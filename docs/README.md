# Docs Index

Last updated: 2026-03-02
Source of truth: `docs/`

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
- [useInertiaFilters Example](./EXAMPLE_useInertiaFilters.md)
- [Vue Form + useApi Rules](./RULES_vue-form-useApi.md)
- [Excel Export Service](./excel-export-service.md)
- [Excel Memory Optimization](./excel-memory-optimization.md)
- [Event Participation Service](./event-participation-service.md)
- [Course Completion & EGC Progression](./COURSE_COMPLETION_AND_EGC_PROGRESSION.md)

## Domain Documentation (Folders)

- API docs: `docs/api/`
- Notification docs: `docs/features/notification/`
- Canvas docs: `docs/features/canvas/`
- Upload docs: `docs/features/upload/`
- Logging docs: `docs/features/logs/`
- Rules docs: `docs/rules/`

## Naming Consistency Notes

- Some docs still use legacy uppercase file names (for example `COURSE_COMPLETION_AND_EGC_PROGRESSION.md`, `EXAMPLE_useInertiaFilters.md`, `RULES_vue-form-useApi.md`).
- Prefer kebab-case for new docs; keep existing names stable unless a coordinated rename plan updates all links.

## Maintenance Rules

- Keep docs factual and code-verified.
- Update docs in the same change window as behavior/route/contract changes.
- Keep unresolved decisions in an `Unresolved Questions` section.
- Keep markdown docs concise and below 800 LOC when possible.
