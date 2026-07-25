---
title: Swinx System Architecture
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: architecture
---

# Swinx System Architecture

## Architecture style

Swinx is a Laravel 13 modular monolith with a Vue 3 and Inertia v3 staff
application. Student and lecturer portals consume versioned JSON APIs from
separate Nuxt repositories.

```text
Web/Portal/MCP/Provider
        |
Routes + middleware + authorization
        |
Thin controllers / MCP boundary
        |
Module Actions, Queries, and contracts
        |
Eloquent + transactional outbox + provider adapters
        |
MySQL + Redis
```

The modular monolith is the deployment boundary. Logical context ownership is
established before any physical extraction is considered.

## Runtime entry points

| Surface | Entry |
| --- | --- |
| Laravel bootstrap | `bootstrap/app.php` |
| Staff web | `routes/web.php` and module web routes |
| Public API | `routes/api.php` |
| Student API | `routes/api/v1/student.php` |
| Lecturer API | `routes/api/v1/lecturer.php` |
| Identity API | `app/Modules/Identity/routes/api.php` |
| Frontend | `resources/js/app.ts` |
| SSR | `resources/js/ssr.ts` |
| Scheduled work | `routes/console.php` |

## Module ownership

| Module | Owns |
| --- | --- |
| `Admissions` | Applications, CRM ingestion, approve/reject/revoke orchestration |
| `Identity` | Accounts, authentication, actor/access grants, token lifecycle |
| `StudentRegistry` | Student Identity and Guardian relationships |
| `Academic` | Catalog/calendar, course delivery, assessment, progression, transcript |
| `Finance` | Billing accounts, obligations, settlement, invoices, DNG, financial reporting |
| `Notification` | Message intent, templates, channels, outbox, delivery, retries |
| `Facilities` | Buildings, rooms, availability, reservations, booking conflicts |
| `Institution` | Campuses and organizational reference data |
| `Engagement` | Clubs, events, and related participation |
| `Upload` | Managed upload/storage boundary |
| `AI` | Audited tool catalog/dispatch and controlled MCP exposure |
| `Platform` | Cross-cutting platform-owned capabilities |

Shared legacy code remains under `app/Services/`, `app/Http/`, and
`app/Models/`. Its presence describes current state, not permission to add new
business behavior there.

## Layering

### HTTP boundary

- FormRequests parse and validate incoming data.
- Middleware establishes authentication, actor, permission, and campus context.
- Controllers orchestrate and translate results into Inertia or `ApiResponse`.
- Eloquent API Resources shape public JSON contracts.

### Application layer

- Actions own state changes and business decisions.
- Queries own complex reads and read models.
- Multi-write actions use database transactions.
- Jobs handle retryable asynchronous work after durable intent is recorded.

### Domain and integration layer

- Module-owned models, policies, enums, and support classes express business
  rules.
- Provider adapters translate DNG, Canvas, email, realtime, storage, and AI
  boundaries.
- Shared contracts expose narrow owned capabilities without leaking models.

## Cross-context communication

Use one of three mechanisms:

1. **Synchronous contract** for an immediate query or atomic state change.
2. **Domain event + outbox** for post-commit reactions.
3. **Owned projection/reader** for reporting, dashboards, search, and AI.

Contexts do not import another context's concrete Actions or Eloquent models to
make business decisions. Projections explain and report state; they do not
decide hard business gates.

## Identity and authorization

- Sanctum protects student and lecturer APIs.
- Actor middleware rejects role/context mismatch.
- Staff web operations require authenticated, permission-aware, campus-scoped
  context.
- Service integrations use dedicated credentials and abilities.
- Guardian relationship is Student Registry data; portal access is an Identity
  grant.
- Lecturer employment eligibility and Lecturer access are separate owned facts.
- MCP maps an authenticated staff user into the same permission and campus
  enforcement boundary used by internal tools.

## Admissions orchestration

Approval is one atomic business outcome:

```text
Admissions Application
  -> Student Registry identity + guardians
  -> Identity account + access grants
  -> Academic Program Enrollment
  -> Application approved
```

Admissions owns the transition and calls receiving contexts through command
contracts. Failure rolls back the transaction. Revoke uses the same boundaries
and refuses destructive teardown after downstream activity exists.

## Academic contexts

Academic decomposes logically into:

- **Catalog & Calendar** — Units, Programs, Curriculum Versions, Academic
  Periods, and catalog policies.
- **Course Delivery & Assessment** — Course Offerings, roster, sessions,
  attendance, grading, and Course Results.
- **Progression & Lifecycle** — Program Enrollment, Study Stage, Transcript
  Entries, GPA, standing, lifecycle actions, and formal Decisions.

Course completion and recalculation synchronously commit accepted Course Result
DTOs into Transcript Entries in the same transaction. Post-commit notifications
use the outbox.

The Metropolia grading scheme pack is intentionally repository-tracked at
`docs/features/academic/metropolia-grading-schemes.json` and loaded by
`MetropoliaSchemeCatalog`.

## Finance source-of-truth model

| Concept | Authority |
| --- | --- |
| Why money is owed | Finance Obligation |
| Payer identity for new aggregates | Billing Account |
| Materialized debit/collection ledger | Finance Charge + Invoice Line |
| Cash received | Payment |
| Cash applied | Payment Application |
| Fee reduction | Discount Allocation |
| Credit reduction | Credit Application |
| Collectible amount/state | Settlement Position contract |
| Provider request/callback evidence | DNG request and webhook records |

Payable Line is the atomic settlement unit. Consumers do not reproduce
settlement arithmetic or conceal invalid ledger state with clamping. Invalid
positions fail closed for collection; internal integrity evidence is not exposed
to students.

Installments schedule collection but do not create debt. Preserved cash remains
distinguishable from new collection. Provider campus identifiers are Finance
owned rather than Campus identity fields.

## Notifications

Source modules publish durable intent. Notification owns:

- Recipient resolution.
- Templates and rendered content.
- Message and delivery persistence.
- Email/realtime channel adapters.
- Retry and operational monitoring.
- Deduplication evidence.

Notification does not decide whether an academic or financial event should
exist. That decision remains with the source module.

## Provider boundaries

### DNG

Finance creates payment requests, records callbacks before processing, verifies
integrity/idempotency, reconciles unknown outcomes, and preserves late verified
cash without reviving cancelled collection state.

### Canvas

Academic owns local Course Offering and syllabus/assessment state. Canvas
mappings and sync evidence constrain reusable templates and provider-specific
operations.

### Portals

The Swinx APIs own server contracts. `FE/student-nuxt` and
`FE/lecturer-nuxt` own portal presentation and handwritten client types. See
`docs/portal-repos.md`.

### Controlled MCP

The ToolDispatcher remains the read execution boundary. OAuth, authorization,
campus scope, schemas, limits, redaction, and audit are enforced inside Swinx;
external agent clients never receive database credentials or unrestricted
application access.

## Data and infrastructure

- MySQL stores transactional state.
- Redis supports cache and queues.
- Queue workers process outbox/provider work.
- FrankenPHP serves production workloads.
- Docker Compose defines development, local-production, host-proxy, and full
  production modes.
- Snapshots and cached totals are rebuildable; they are not business authority.

## Architecture constraints

- No new business logic in legacy shared services.
- No cross-context Eloquent joins for decisions.
- No direct JSON response outside the project API envelope.
- No provider payload enters domain logic without boundary validation.
- No public contract change without canonical docs and targeted tests.
- No runtime dependency on historical plans, reports, or story files.
- No new bounded context based only on a portal actor or UI menu.
