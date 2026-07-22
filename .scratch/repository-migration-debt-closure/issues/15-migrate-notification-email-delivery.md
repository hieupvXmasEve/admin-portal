# Migrate Notification and email delivery operations

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move notification publication, preferences, templates, SMTP configuration, delivery monitoring, retries, and email transport behind the Notification/shared publisher boundaries. Producers publish domain facts after commit and never depend on Notification implementation types.

## Acceptance criteria

- [ ] Notification V2 outbox, messages, deliveries, preferences, templates, SMTP resolution, retries, and monitoring have supported owner paths.
- [ ] Domain producers use the shared publisher after commit and do not write delivery state directly.
- [ ] Campus scope, permissions, channel selection, idempotency, retries, and audit evidence remain intact.
- [ ] Staff and portal notification contracts remain compatible while legacy history has an explicit retained or approved migration disposition.
- [ ] Legacy email/notification services and controllers retire only after scheduled, queued, and template-driven callers cut over.

## Blocked by

- [Establish the Migration Debt inventory and regression guards](03-establish-migration-debt-inventory-and-guards.md)

## Comments

- 2026-07-22: Cut over the supported Finance student, parent, and due-item reminder actions to publish `finance.payment_reminder_requested` through the shared `DomainEventPublisher` after the reminder marker commits. The Notification V2 outbox now owns template rendering, recipient resolution, delivery creation, retries, SMTP transport, and monitoring for these paths. The existing operation response shape remains unchanged; `sent_count` now means successfully queued for V2 delivery. Campus scope, per-parent recipient selection, and per-event idempotency are retained. Focused verification: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/SendPaymentRemindersActionTest.php tests/Feature/Finance/SendParentPaymentRemindersActionTest.php tests/Feature/Finance/SendDueItemRemindersActionTest.php` — 10 passing / 50 assertions. Portal inspection found no contract change, so neither portal was modified. This issue remains `ready-for-agent`: legacy scheduled Event notifications, bulk/manual email surfaces, preference APIs, SMTP configuration APIs, and their explicit retirement disposition still need supported-owner cutover before any legacy service or controller can be retired.
- 2026-07-22: Added V2 delivery-time email preference suppression (persisted as a skipped delivery with audit-safe reason), with the existing event-preference API delegated to Notification actions/queries. Migrated Event notifications to a compatibility facade that publishes shared domain facts after commit; Event queue dispatch is now explicitly after commit and no longer writes legacy `notifications` rows. Manual notifications and template test-send also publish through `DomainEventPublisher`. Notification V2 now owns the SMTP transport and creates/updates retained `EmailLog` audit rows itself; it no longer calls `EmailService` from either V2 email channel adapter. V2 operations pages continue to own outbox/message/delivery monitoring and retry. Verification: 31 focused tests / 146 assertions passed, `migration-debt:inventory --check` passed, and `git diff --check` passed. The remaining decision is material: legacy admin bulk/single-email endpoints, invoice-parent reminders, and exam-resit cancellation notices accept arbitrary external email addresses (and attachments), while V2 messages currently require an internal user recipient. Confirm whether V2 should gain an external-recipient/attachment delivery model, or those legacy paths should be explicitly retained outside Notification V2; do not retire them until this is decided.
- 2026-07-23: Approved direction: Notification V2 now supports text/HTML email to external recipients, identified by normalized `recipient_key` (`email:{address}`) for idempotency. External recipients cannot receive realtime delivery and do not inherit internal-user preferences. Parent due-item reminders, exam-resit cancellation notices, and admin single/bulk text email publish through the shared publisher; V2 creates the retained EmailLog when delivery occurs. Attachments are intentionally not supported: admin single/bulk endpoints now reject `attachments` and the deferred attachment design must cover storage retention, access control, and retry-safe file availability. Migration dry-run and focused external-recipient tests pass.
- 2026-07-23: Completed the text/HTML external-email cutover behind the shared `ExternalEmailPublisher` contract, so Finance, Academic, and the admin API do not import Notification implementations. Added the recipient-key migration, external email-only message persistence, SMTP delivery/audit support, and API coverage that proves attachments are rejected with `422`. Focused verification: 27 tests / 139 assertions passed; Pint, migration dry-run, `migration-debt:inventory --check`, and `git diff --check` passed. The full suite was also started and progressed through its Notification/Finance/Academic groups, but terminated in an unrelated Academic grading test due to the container's PHP 256 MB memory limit (`HasAttributes.php:799`); it did not report a Notification failure. V2 must remain enabled in deployment configuration (`NOTIFICATION_V2_ENABLED=true` with a V2 write mode) for these publishers to enqueue delivery.
