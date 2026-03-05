---
title: 'Notification Module Phase 1 - Domain Event Clean-Slate'
description: 'Implement outbox-driven notification module with clean-slate tables, canonical recipient_user_id, strict campus isolation, and dual-read cutover.'
status: in-progress
priority: P1
effort: 6-9d
branch: dev
tags: [laravel12, notifications, outbox, realtime, email, multicampus, idempotency]
created: 2026-03-03
---

# Plan Overview

- Goal: deliver Phase 1 module from `specs/notification_module_domain_event.md` without breaking existing notification flows during cutover.
- Architectural decisions (locked):
    - strict isolation by `event.campus_id`, exception allowlist only for `system/security` and explicit global announcements
    - no legacy history migration in Phase 1; read only notifications created after cutover
    - canonical recipient is `recipient_user_id`; non-user targets must resolve to user before message/delivery persist
    - unresolved recipients: `skip + audit + metric alert`; no legacy fallback

## Phases

| Phase | File                                                 | Status      | Notes                                            |
| ----- | ---------------------------------------------------- | ----------- | ------------------------------------------------ |
| 01    | `phase-01-domain-contract-and-schema.md`             | completed   | contracts + migrations + model skeleton          |
| 02    | `phase-02-outbox-dispatch-and-idempotency.md`        | completed   | outbox worker + intent pipeline + retries        |
| 03    | `phase-03-channel-adapters-and-realtime-security.md` | in-progress | email/realtime adapters + channel auth hardening |
| 04    | `phase-04-dual-read-api-ui-cutover.md`               | in-progress | ops UI complete; feed query/mark-read pending    |
| 05    | `phase-05-pilot-event-integration-after-commit.md`   | in-progress | pilot domain events from Academic/Finance        |
| 06    | `phase-06-tests-observability-final-cutover.md`      | in-progress | ops tests done (38); cutover validation pending  |

## Dependencies

- Phase 01 before all phases
- Phase 02 before 03/04/05
- Phase 03 before realtime cutover in 04
- Phase 05 before full cutover in 06

## Delivery Rules

- Keep legacy `notifications` table and routes running until Phase 06 success criteria pass.
- No notification send in business transaction; only outbox write and after-commit dispatch.
- Idempotency enforced at DB unique constraints first, app lock second.

## Unresolved Questions

- None.
