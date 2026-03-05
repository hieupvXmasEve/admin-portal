# Phase 02 - Outbox Dispatch and Idempotency

## Context Links

- Spec: `specs/notification_module_domain_event.md`
- Phase 01: `plans/260303-notification-module-domain-event-phase-1/phase-01-domain-contract-and-schema.md`

## Overview

- Priority: High
- Status: completed
- Objective: implement fixed pipeline `Event -> Intent -> Policy -> Persist -> Dispatch -> Track` with robust retry and dedupe.

## Key Insights

- Queue is at-least-once; DB idempotency is mandatory.
- Outbox must be written inside business transaction, but dispatch must happen after commit.

## Requirements

- Functional:
    - process pending outbox rows in batches
    - resolve recipients to `user_id`
    - persist messages/deliveries idempotently
    - update statuses and retry metadata
- Non-functional:
    - crash-safe worker behavior
    - bounded retries and dead-letter semantics

## Architecture

- Outbox command/job claims rows by `status=pending` and `next_retry_at<=now`.
- `RecipientResolver` maps targets (`Student`, `Lecture`, `User`, etc.) to `recipient_user_id`.
- Unresolved target is skipped with audit record and metrics increment.

## Related Code Files

- Create:
    - `app/Modules/Notification/Actions/PublishDomainEventAction.php`
    - `app/Modules/Notification/Actions/DispatchOutboxBatchAction.php`
    - `app/Modules/Notification/Actions/HandleOutboxEventAction.php`
    - `app/Modules/Notification/Actions/PersistIntentAction.php`
    - `app/Modules/Notification/Support/RecipientResolver.php`
    - `app/Modules/Notification/Support/EventIntentMapper.php`
    - `app/Modules/Notification/Console/ProcessNotificationOutboxCommand.php`
    - `app/Modules/Notification/Jobs/ProcessNotificationOutboxJob.php`
    - `app/Modules/Notification/Support/NotificationAuditLogger.php`
- Modify:
    - `routes/console.php` (schedule outbox worker if needed)

## Implementation Steps

1. Build outbox claim/process flow with row locking and retry-safe status updates.
2. Map event envelope to one or more intents with stable `type_key`.
3. Apply policy resolver (allow all in phase 1, plus strict campus rules + exceptions).
4. Resolve recipients to unique `user_id` set.
5. Persist messages and channel deliveries with upsert/idempotent keys.
6. Queue channel dispatch jobs and mark delivery state transitions.
7. Track and log correlation IDs (`event_id`,`message_id`,`delivery_id`,`campus_id`).

## Todo List

- [x] Implement outbox worker action + command
- [x] Implement intent mapper + resolver
- [x] Implement recipient resolver to canonical `user_id`
- [x] Implement unresolved-recipient audit path
- [x] Implement idempotent message/delivery persistence
- [x] Implement retry backoff and dead-letter marking

## Success Criteria

- Reprocessing same event does not duplicate rows.
- Failed rows retry then stop at max attempts.
- Unresolved recipients are skipped and observable.

## Risk Assessment

- Risk: lock contention on outbox table.
- Mitigation: small batches, indexed scans, short claim transaction.

## Security Considerations

- Redact payload fragments in failure logs.
- Preserve campus boundary in all transitions and logs.

## Next Steps

- Continue to Phase 03 channel adapters and realtime auth hardening.

## Unresolved Questions

- None.
