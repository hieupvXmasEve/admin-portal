# Phase 01 - Domain Contract and Schema

## Context Links

- Spec: `specs/notification_module_domain_event.md`
- Plan: `plans/260303-notification-module-domain-event-phase-1/plan.md`

## Overview

- Priority: High
- Status: completed
- Objective: create stable domain envelope contract and clean-slate persistence tables for outbox/messages/deliveries.

## Key Insights

- Existing `notifications` table has mixed `notifiable_type` values; phase 1 must isolate from this risk.
- Canonical recipient decision changes schema: store `recipient_user_id` and keep source actor in metadata.

## Requirements

- Functional:
    - define event envelope contract with required fields and naming convention
    - create `notification_event_outbox`, `notification_messages`, `notification_deliveries`
    - enforce idempotency unique keys
- Non-functional:
    - strict typing and clear status transitions
    - indexes for recipient feed and retry scanning

## Architecture

- Event envelope is immutable fact (`{domain}.{event}`).
- Message table stores logical recipient state per `recipient_user_id`.
- Delivery table stores per-channel send lifecycle and provider correlation.

## Related Code Files

- Modify:
    - `bootstrap/providers.php`
- Create:
    - `app/Modules/Notification/Providers/NotificationServiceProvider.php`
    - `app/Modules/Notification/Domain/Contracts/DomainEventEnvelope.php`
    - `app/Modules/Notification/Models/NotificationEventOutbox.php`
    - `app/Modules/Notification/Models/NotificationMessage.php`
    - `app/Modules/Notification/Models/NotificationDelivery.php`
    - `database/migrations/*_create_notification_event_outbox_table.php`
    - `database/migrations/*_create_notification_messages_table.php`
    - `database/migrations/*_create_notification_deliveries_table.php`
- Delete:
    - none

## Implementation Steps

1. Define envelope DTO/value object and validate required fields.
2. Add migrations with status columns, retry fields, timestamps, and indexes.
3. In `notification_messages`, use `recipient_user_id` and `recipient_meta` JSON.
4. Add unique constraints:
    - outbox: `event_id`
    - messages: `event_id,type_key,recipient_user_id`
    - deliveries: `message_id,channel`
5. Register Notification module service provider.

## Todo List

- [x] Create envelope contract and status enums
- [x] Add outbox migration + indexes
- [x] Add messages migration + indexes
- [x] Add deliveries migration + indexes
- [x] Add Eloquent models + casts
- [x] Register module provider

## Success Criteria

- Migrations apply cleanly on local DB.
- Unique/index constraints match plan.
- Model casts and guarded/fillable fields support queue pipeline.

## Risk Assessment

- Risk: schema drift with later phases.
- Mitigation: lock enum values and contract in one module namespace.

## Security Considerations

- Do not store sensitive raw payload in plaintext if not required.
- Keep `campus_id` as first-class field in all new tables.

## Next Steps

- Continue to Phase 02 dispatcher and idempotent pipeline.

## Unresolved Questions

- None.
