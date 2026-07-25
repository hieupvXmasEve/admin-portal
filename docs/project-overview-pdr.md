---
title: Swinx Product Baseline
status: canonical
owner: Product and Platform Teams
last_verified: 2026-07-25
scope: product-contract
---

# Swinx Product Baseline

## Product outcome

Swinx provides one operational platform for university staff while exposing
controlled student, guardian, lecturer, provider, and AI integration surfaces.
The product favors clear operational ownership and auditable workflows over
service decomposition.

## Users and surfaces

| User/surface | Primary jobs |
| --- | --- |
| Staff/admin web app | Admissions, academic operations, delivery, finance, facilities, engagement, notifications, reporting |
| Student portal/API | Timetable, courses, curriculum, results, finance, forms, events, clubs, notifications |
| Lecturer portal/API | Assigned courses, sessions, attendance, assessments, forms, timetable |
| Guardian access | Authorized access to selected Student capabilities |
| Admissions CRM | Idempotent application and document ingestion |
| DNG | Payment request, callback, reconciliation, and audit |
| Canvas | Course mapping, syllabus/assessment sync, and grade integration |
| Controlled MCP | Permission-scoped, audited read access for authorized staff |

Public API details live in `docs/api/`. External integration and operational
details live in `docs/features/`.

## Product capabilities

### Identity and access

- Sanctum protects student and lecturer API groups.
- Actor middleware separates student, guardian, lecturer, staff, and service
  access.
- Campus context and permissions constrain staff operations.
- Guardian relationship and Guardian Access Grant are separate concepts.
- Controlled MCP access uses per-user OAuth and preserves Swinx authorization,
  campus scope, redaction, and audit boundaries.

### Admissions

- CRM ingestion is authenticated, rate-limited, idempotent, and code-based.
- Staff approve, reject, and safely revoke Applications.
- Approval is an atomic business outcome spanning Admissions, Student Registry,
  Identity, and Academic Enrollment.
- Approved/rejected Applications reject subsequent CRM mutation.
- A Student with downstream activity is never hard-deleted to undo approval.

### Student registry and academic progression

- Student Identity is separate from account, Program Enrollment, and Study
  Stage state machines.
- Student Hub is the staff operational console for a single Student.
- Lifecycle actions and academic progression retain auditable provenance.
- Formal Decisions may cover multiple Students and authorize lifecycle actions.
- Course completion/recalculation produces Course Results and commits Transcript
  Entries through explicit ownership boundaries.

### Course delivery and assessment

- Course Offerings own roster, sessions, attendance, assessment components,
  grading, and completion operations.
- Grading supports current default and Metropolia engines.
- Post-completion grade changes flow through controlled recalculation.
- Lecturer reports and exports remain campus- and permission-scoped.

### Finance

- Finance Obligations explain why money is owed or reduced.
- Materialized charges and payable lines support settlement and reporting.
- Settlement Position is the authoritative calculation seam for collectible
  amounts and settlement state.
- Cash, discounts, and credits remain distinct.
- Installments are collection plans, not independent debt sources.
- DNG collection fails closed on invalid or unreconciled settlement positions.
- Preserved cash, defer, return-study, scholarship, and credit behavior follows
  accepted Finance ADRs.

### Notifications and email

- Notification owns delivery, not source-domain business decisions.
- New delivery flows use persisted messages/deliveries and outbox processing.
- Templates, variables, channels, retries, and deduplication are auditable.
- Student warnings derive from owned academic/attendance data and use explicit
  warning milestones.

### Facilities and engagement

- Facilities owns spaces, room availability, reservations, series bookings,
  and conflicts while keeping existing user-facing URLs stable.
- Engagement owns clubs and events surfaced to staff and students.

## Non-functional requirements

- Authorization and campus isolation fail closed.
- Financial and academic state transitions are auditable.
- Public APIs use stable response envelopes and documented errors.
- Multi-write workflows are transactional.
- Cross-context access uses contracts, events, or projections.
- Provider callbacks and retryable commands are idempotent.
- Student-facing errors do not expose internal integrity evidence.
- Expensive reads use bounded queries and explicit pagination.
- Documentation and tests change with public contracts.

## Product boundaries

- Swinx remains a modular monolith; physical service extraction is not a
  current objective.
- Portal UI behavior is owned by the separate Nuxt repositories.
- Provider systems do not receive database credentials or unrestricted internal
  access.
- AI clients do not grant permissions or become a source of truth.
- Operational snapshots and caches are rebuildable and never replace canonical
  ledgers or owned records.

## Current risks

- Legacy service/controller paths remain and require controlled migration.
- Some public contracts are handwritten rather than generated from OpenAPI.
- Cross-repository portal validation is not yet a single CI gate.
- Provider and deployment behavior depends on environment configuration outside
  the repository.

## Success evidence

- Targeted tests prove changed behavior and authorization.
- API contract changes update the affected portal types and consumers.
- Architecture tests guard critical ownership boundaries.
- Operational runbooks provide reproducible verification commands.
- Documentation validation reports no broken links, duplicate ADR IDs, or
  orphan canonical files.
