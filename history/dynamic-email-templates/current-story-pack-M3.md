# Current Story Pack: M3 — EmailService → outbox emit

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 3 (critical path)
**Mode:** high-risk (parent) · **Story risk:** HIGH (cross-module rewrite, production traffic)
**Depends on:** S2 ✅ + C1 ✅ (Shared Contract)

## Entry State

- `app/Services/EmailService.php` (~1000 LOC) has 2 send methods: `sendSingleEmail()` + `sendBulkEmail()` — both write `email_logs` directly.
- 12 callsites across 8 files (full inventory: `spike-S2-emailservice-callers.md`).
- Channel sinks (`EmailChannelAdapter`, `RenderedEmailChannelAdapter`) MUST stay (they ARE the outbox terminal).
- C1 shipped: `App\Shared\Contracts\Notification\EmailContentProvider` available for cross-module use.
- Outbox pipeline: `notification_event_outbox` → `HandleOutboxEventAction` → `PersistIntentAction` → `notification_messages`/`notification_deliveries` → `SendNotificationDeliveryJob` → channel adapter → `EmailService::sendSingleEmail` (the channel sink).

## Exit State

- `EmailService::sendSingleEmail` + `sendBulkEmail` bodies preserved (channel adapters keep calling them). NO behavior change at the sink.
- **Cross-module callers** (6 Finance Actions + `NotificationTemplateController:160` test-send button) re-routed to emit an outbox event instead of calling `EmailService` directly. They go through a new `DispatchNotificationAction` (or extend existing — name decided in pack body) that writes `notification_event_outbox` and dispatches `HandleOutboxEventJob`.
- Legacy `email_logs` write stays from the channel-sink side (audit shadow). No backfill, no read-path change in M3.
- Parity test per migrated entry point: assert outbox row + `notification_messages` + `notification_deliveries` + email lands at sink.
- `./scripts/dev.sh test tests/Feature/Finance tests/Feature/Notification` green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Notification/Actions/DispatchNotificationAction.php` (or extend existing `HandleOutboxEventAction`) | create/edit |
| `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php` | edit (line 77: replace `$emailService->sendSingleEmail(...)` with outbox emit) |
| `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php` | edit (line 60) |
| `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php` | edit (lines 82 + 152 — TWO loops) |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` | edit (lines 81 + 147) |
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` | edit (line 160 test-send button) |
| `tests/Feature/Finance/SendPaymentRemindersActionTest.php` (+ siblings) | edit (parity assertions) |
| `tests/Feature/Notification/OutboxFlowTest.php` (or similar) | edit/create (per-entry-point parity test) |

Total: ~8–10 file ops. At pack limit. If `DispatchNotificationAction` needs supporting classes, split into M3a (Action + 1 Finance caller) + M3b (rest of Finance + test-send button).

## DAG Row

`[C1 ✅] → [M3] → [M4 (jobs)]; [M3] → [M5 (deprecate EmailController)]`

## Critical Patterns Applied

- **#1 unsafe-char fixtures** — every parity test uses `<>"&'` in recipient/subject/body.
- **#3 Octane** — if `DispatchNotificationAction` introduces any mutable state, use `app->scoped()` + `RequestHandled` reset.
- **#7 ≥2-row fixtures** — Finance loops have ≥2-recipient fixtures (existing tests already comply per P2 D-batch learning).
- **CLAUDE.md Forbidden Patterns** — Finance imports Shared Contract namespace, NOT Notification internal.

## Feasibility Notes

- Highest-risk story in P3. Validating MUST cite the outbox emit precedent (`HandleOutboxEventAction` does the same — Finance reproduces the pattern).
- Risk: `EmailService::sendBulkEmail` has CSV-recipient batching — under outbox emit it becomes N events. Queue throughput needs measurement. May need batch-event shape.
- Risk: `NotificationTemplateController:160` (test-send) is the cleanest first migration — move it first to prove pattern.

## Handoff to Validating

Validating gates:
1. Cite outbox-emit precedent file:line (where does `HandleOutboxEventAction` get called for an academic feature?).
2. Confirm `DispatchNotificationAction` (or extend) doesn't conflict with existing class names — grep.
3. Confirm parity-test pattern from P2 D-batch — reuse the fixture setup; don't reinvent.
4. Confirm CSV-bulk batching approach (one event per recipient vs one event per batch) — DECIDE before workers commit.
