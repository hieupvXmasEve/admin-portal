# Current Story Pack: M3a — test-send button → outbox emit

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 3a (proof-of-pattern)
**Mode:** high-risk (parent) · **Story risk:** MEDIUM (single callsite, same module, no cross-module reach, BUT extends `HandleOutboxEventAction` API surface per Option α)
**Depends on:** S2 ✅ + C1 ✅
**Supersedes part of:** `current-story-pack-M3.md` (split per `m3-repair-memo.md`)
**Repair history:** Validation 2026-05-19 rejected v1 (registry-based render would clobber draft content); v2 adopts Option α — handler accepts pre-rendered envelope.

## Entry State

- `NotificationTemplateController.php:160` — `app(EmailService::class)->sendSingleEmail(...)` is the test-send button in the admin template editor.
- Same-module reach (Notification module owns both the controller and the outbox pipeline). No cross-module Contract needed.
- Outbox emit precedent: `PublishDomainEventAction::run(DomainEventEnvelope $envelope)` at `app/Modules/Notification/Actions/PublishDomainEventAction.php:13-37`.
- `EmailContentRegistry` already registers `payment_reminder`, `parent_payment_reminder`, `dng_payment_pushed`, `dng_payment_received`. Test-send button can send any registered `type_key`.

## Exit State

- `NotificationTemplateController::testSend` (`NotificationTemplateController.php:125-172`) keeps its existing draft-rendering step: `$transient->render($sampleVariables)` produces `$rendered['subject']` + `$rendered['html']` from the admin's draft input. The `'[TEST] '` subject prefix stays applied at the controller before emit.
- After rendering, the endpoint no longer calls `EmailService::sendSingleEmail` directly. It builds a `DomainEventEnvelope` and calls `PublishDomainEventAction::run`.
- Envelope shape:
  - `event_name` = `notification.test_send_requested` (new event; grep-verified no conflict).
  - `aggregate_type` = `notification_template`, `aggregate_id` = `$template->id`.
  - `payload.type_key` = `$template->type_key->value` (e.g. `payment_reminder`). Carried only for `EventIntentMapper` to resolve targets/channels; the registry is bypassed for rendering — see next bullet.
  - `payload.channels` = `['email']`.
  - `payload.recipient_targets` = `[['type' => 'user', 'id' => $request->user()->id]]` (admin self-send).
  - `payload.rendered_email` = `['rendered_subject' => '[TEST] '.$rendered['subject'], 'rendered_html' => $rendered['html'], 'rendered_text' => null]` (NEW envelope field — Option α).
  - `payload.data` = `$sampleVariables` (kept for audit; handler does not re-render against it).
- `HandleOutboxEventAction::buildRenderedEmail` extended (Option α): if `$envelope->payload['rendered_email']['rendered_subject']` is present, return that array directly without consulting `EmailContentRegistry`. Existing registry-resolution path stays as the else branch for all current callers (Finance reminders, DNG events, etc.).
- `PersistIntentAction` already accepts a `rendered_email` array (`PersistIntentAction.php:19-22, 51-55`) — no change needed there.
- `SendNotificationDeliveryJob` already forks on `delivery.rendered_subject !== null` to `RenderedEmailChannelAdapter` (`SendNotificationDeliveryJob.php:57-60`) — no change needed there.
- Channel sink: `RenderedEmailChannelAdapter::send` calls `EmailService::sendSingleEmail` with the pre-rendered subject + html (`RenderedEmailChannelAdapter.php:31-36`). Legacy `email_logs` audit-shadow write still happens at the sink.
- Parity test asserts: outbox row exists with `payload.rendered_email` populated; `notification_messages` row exists; `notification_deliveries` row exists with `rendered_subject` + `rendered_html` populated from payload (NOT from registry); `RenderedEmailChannelAdapter` invoked once; recipient receives email with `[TEST] ` prefix and the draft body verbatim.
- `./scripts/dev.sh test tests/Feature/Notification tests/Feature/Modules/Notification` green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Notification/Actions/HandleOutboxEventAction.php` | edit — `buildRenderedEmail` short-circuit on `payload.rendered_email` (Option α). ~10-line guard at start of method. |
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` | edit — replace `sendSingleEmail` call with `PublishDomainEventAction::run`; build envelope with `payload.rendered_email` (lines 160-169) |
| `tests/Feature/Modules/Notification/HandleOutboxEventPreRenderedTest.php` | create — unit test for Option α short-circuit (registered type_key + rendered_email → bypass registry; no rendered_email → existing path) |
| `tests/Feature/Notification/TestSendOutboxEmitTest.php` | create — parity test for endpoint (unsafe-char fixtures, ≥2 distinct test-sends per Critical Patterns #1 + #7) |
| `history/dynamic-email-templates/m3a-rollout-notes.md` | create — record staging-verify outcome + rollback notes |

Total: **5 file ops.** Within pack limit.

## DAG Row

`[S2 ✅, C1 ✅] → [M3a] → [M3b (Finance Actions)]`

M3b is hard-gated on M3a being green on staging — proves outbox-emit pattern before cross-module fan-out.

## Critical Patterns Applied

- **#1 unsafe-char fixtures** — parity test uses `<script>`, `&amp;`, `"`, `'` in recipient name, subject, body.
- **#3 Octane reset** — `PublishDomainEventAction` is stateless; no new mutable state introduced. No `app->scoped()` needed.
- **#7 ≥2-row fixtures** — parity test triggers test-send twice with different recipients to catch single-row bugs.
- **CLAUDE.md cross-module rule** — N/A for M3a (same module).

## Outbox payload contract (applies to all M3 stories)

`payload.data` MUST mirror the existing `$contentData` shape the EmailContentProvider expects for the selected `type_key`. For test-send, this is whatever fields the admin form provides plus `campus_id`.

`payload.data` MUST NOT carry `dng_payment_request_id` unless the type_key is `dng_payment_pushed` or `dng_payment_received`. The handler at `HandleOutboxEventAction.php:150` re-derives `student_name`, `invoice_code`, etc. when that key is present, which would clobber pre-built data for other type_keys.

## Feasibility Notes

- Story risk upgraded from LOW-MEDIUM to MEDIUM due to handler edit (Option α). Still single-module, no Contract reach.
- Option α surface: `HandleOutboxEventAction::buildRenderedEmail` gains a short-circuit guard. Existing callers (Finance reminders, DNG events) are not affected — they don't set `payload.rendered_email`, so they fall through to the registry path. Behavior-preserving for current production traffic.
- Risk: test-send today is synchronous — admin clicks button, sees result immediately. Outbox path is async (queue dispatch). UX changes: success response = "test send queued"; admin checks `/systems/email-history` to confirm delivery. Acceptable per P3 scope (admin tool).
- Risk: `notification.test_send_requested` is a new event_name. Grep-verified clean as of 2026-05-19.
- Risk: rate-limiter (`notification-template-test-send`, 5/min) sits on the controller endpoint, unaffected by switch.
- Octane safety: `HandleOutboxEventAction` does not gain new mutable state from Option α. Guard is a pure read of `$envelope->payload`. No `app->scoped()` required.
- Rollback: revert `HandleOutboxEventAction.php` + `NotificationTemplateController.php`. ~2 files, ~30 lines. Tests skip when path is removed.

## Handoff to Validating

Validating gates:
1. Cite outbox-emit precedent file:line — DONE (`PublishDomainEventAction.php:13-37`).
2. Confirm Option α handler change is behavior-preserving for all existing callers (Finance reminders, DNG events, manual notifications, query events). Specifically: when `payload.rendered_email` is absent, the existing registry path runs unchanged.
3. Confirm `HandleOutboxEventRenderedEmailTest.php` (existing) still passes — no regression.
4. Confirm no name conflict for `notification.test_send_requested` event_name — DONE (grep 2026-05-19).
5. Confirm parity-test fixture pattern reusable from `tests/Feature/Modules/Notification/HandleOutboxEventRenderedEmailTest.php`.
6. Confirm async UX is acceptable (admin tool, not user-facing critical path).
7. Confirm `EventIntentMapper.resolveTypeKey` accepts payload.type_key without mapper edit (verified `EventIntentMapper.php:48`).
8. Confirm `RenderedEmailChannelAdapter` is the resolved adapter when `delivery.rendered_subject !== null` (verified `SendNotificationDeliveryJob.php:57-60`).
