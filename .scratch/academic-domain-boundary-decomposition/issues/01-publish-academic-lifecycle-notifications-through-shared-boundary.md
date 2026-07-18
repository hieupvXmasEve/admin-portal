# Publish Academic lifecycle notifications through the shared Domain Event boundary

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Establish the shared Domain Event publisher as the only entry point from Academic business flows into Notification, using the existing student-facing lifecycle notifications as the tracer. Course completion, course-stage changes, EGC progression, and academic warnings must keep their current observable delivery behavior while producers stop depending on Notification implementation Actions or envelope types.

## Acceptance criteria

- [x] Academic lifecycle producers publish through a shared contract with context-owned event payloads and stable deduplication keys.
- [x] Notification remains responsible for templates, recipients, message persistence, delivery attempts, email, and realtime channels.
- [x] Events are persisted only after the source business transaction commits; a rolled-back source transition creates no notification work.
- [x] Retried publication or consumption does not create duplicate messages or deliveries.
- [x] Existing student-facing notification behavior and API response shapes remain compatible.
- [x] Architecture tests reject direct Academic dependencies on Notification concrete Actions, models, and internal contracts.
- [ ] Focused behavior, rollback, idempotency, and student portal checks pass.

## Blocked by

None - can start immediately.

## Comments

- Implemented the shared `DomainEventPublisher` boundary with deterministic UUIDv5 event identifiers from stable deduplication keys. Academic course completion, stage changes, EGC progression, and warning producers now publish context-owned payloads through that contract; Notification adapts the event into its existing outbox pipeline.
- Verification passed: focused Academic/Notification/architecture suite (19 tests, 83 assertions), full `./scripts/dev.sh test`, `./scripts/dev.sh npm run type-check`, `./scripts/dev.sh npm run lint`, Pint, student portal `pnpm typecheck`, and student portal `pnpm build`.
- Remaining verification gap: student portal `pnpm lint` cannot initialize `eslint-plugin-pnpm` because `pnpm-workspace.yaml` is absent (the repository contains `pnpm-workspace.save.yaml`). No portal files were changed by this issue.
- Frozen-zone exception: the two existing lifecycle services are compatibility producers for course completion and EGC progression. This issue changes only their injected publisher dependency and existing publication calls to remove the forbidden Notification implementation imports; it adds no service methods. Their physical extraction remains separate from this boundary cutover.
- The related `academic.course_score_updated` producer was migrated with course completion because it shares those same lifecycle services; leaving it behind would retain a direct Notification bypass and fail the new architecture guard.
