# Close remaining domain-boundary bypasses and retire transitional adapters

Status: ready-for-agent

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Perform the final evidence-driven closure after every tracer slice has landed. Inventory all runtime consumers, scheduled jobs, reports, imports, exports, and portal contracts; remove remaining shared-model reads, concrete cross-context calls, obsolete compatibility adapters, deprecated provider fields, and temporary allowlists only when replacement behavior is proven.

## Acceptance criteria

- [ ] No runtime context imports another context's Eloquent models, concrete Actions, internal services, or schema-specific enums.
- [ ] Cross-context interaction is limited to approved neutral references, Query/Command Contracts, Domain Events/outbox, and Read Projections.
- [ ] Architecture tests cover Identity, Institution, Registry, Admissions, Workforce, Catalog, Delivery, Progression, Finance, Facilities, Notification, and AI/Reporting with no unexplained allowlist entries.
- [ ] Every transitional adapter, compatibility read/write, and deprecated provider field is either removed or retained with a named owner and separate approved follow-up.
- [ ] Historical academic, financial, admissions, guardian, access, notification, and audit evidence remains intact.
- [ ] Existing staff, student, guardian, lecturer, Finance, Facilities, Notification, AI, scheduled-job, import/export, and reporting behavior passes regression checks.
- [ ] Student and lecturer portals pass lint, typecheck, and build where their contracts or shared authentication context were touched.
- [ ] Architecture and domain documentation describe the landed state rather than the migration target.

## Blocked by

- All issues 01 through 22 in this feature.

## Comments

- 2026-07-19: Closure pass moved Delivery roster, gradebook, assessment, and Canvas sync identity reads to `StudentReferenceReader`; Canvas catalog/progression facts now use owner readers; lecturer attendance accepts only a lecturer ID. The complete architecture suite passed (54 tests / 170 assertions), Canvas grade-sync tests passed (15 / 75), PHP formatting passed, and frontend type-check passed. Review found further Assessment score-table paths that still load/join Registry persistence and an expanded DNG writer allowlist that requires a named owner/retirement decision. The issue remains `ready-for-agent`: its full regression, portal, documentation, historical-evidence, and all-predecessor completion criteria are not yet evidenced; `LecturerRosterInactiveStudentTest` still has three pre-existing API assertions returning `403 Actor is not authorized` before the changed service is reached.
