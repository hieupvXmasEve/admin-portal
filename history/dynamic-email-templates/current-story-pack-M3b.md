# Current Story Pack: M3b — Finance reminder Actions → outbox emit

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** M · **Wave:** 3b (cross-module fan-out)
**Mode:** high-risk (parent) · **Story risk:** HIGH (4 Finance Actions, production reminder traffic)
**Depends on:** S2 ✅ + C1 ✅ + H1 (hygiene precursor) + **M3a green on staging (HARD GATE — see §HARD GATE below)**
**Supersedes part of:** `current-story-pack-M3.md` (split per `m3-repair-memo.md`)
**Inherited from M3a:** see `history/learnings/20260519-dynamic-email-templates-p3-m3a.md` and `critical-patterns.md` entries D1 (cross-module dispatcher exception ≥3 callers) and P1 (outbox accepts pre-rendered envelope key — note this is **not used** by M3b; see §"Outbox payload contract" below).

## HARD GATE — M3a staging verification

M3b cannot enter validating until:

1. M3a deployed to staging.
2. UAT walked per `history/dynamic-email-templates/m3a-rollout-notes.md`:
   - Test-send delivers `[TEST]` + draft body (not persisted body).
   - `notification_event_outbox` row with `event_name = 'notification.test_send_requested'`.
   - `notification_deliveries` row with populated `rendered_subject` + `rendered_html`.
   - Existing Finance reminder send still works (no registry-path regression).
   - 6th test-send within 1 minute → 429.
3. Staging-verify outcome recorded in `m3a-rollout-notes.md` with date + verifier name.

If any UAT step fails, M3b is BLOCKED — return to compounding for M3a fix and re-run staging.

## F3 decision (locked)

**Option A — accept established precedent.** Finance Actions inject `App\Modules\Notification\Actions\PublishDomainEventAction` directly, matching the 6 existing cross-module callers (`PaymentService.php:13`, `DngPaymentService.php:25`, `Academic\PublishCourseStageChangedNotificationAction.php:17`, `Form\SubmitResponseAction.php:30`, `Query\ReplyToQueryAction.php`, `Query\AssignQueryAction.php`, `Query\CreateStudentQueryReplyAction.php`).

The CLAUDE.md "Cross-module → Contract" rule has an established de-facto exception for `PublishDomainEventAction`. M3b documents this exception in `docs/rules/contracts.md` rather than adding a single-impl interface. A future cleanup epic (P4) can introduce a dispatcher Contract and migrate all 7+ callers together.

## Entry State

- 4 Finance Actions hold 6 callsites to `EmailService::sendSingleEmail` (full inventory in `spike-S2-emailservice-callers.md`):
  - `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php:77`
  - `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php:60`
  - `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php:82` and `:152`
  - `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php:81` and `:147`
- All 4 Actions resolve `EmailContentRegistry::resolve('payment_reminder')` or `resolve('parent_payment_reminder')` — both type_keys already registered (`EmailContentRegistry.php:44-45`).
- Channel sinks (`EmailChannelAdapter`, `RenderedEmailChannelAdapter`) stay as outbox terminal. NO behavior change at the sink.
- M3a has shipped: outbox-emit pattern is proven for `notification.test_send_requested`. M3b reproduces the same shape for `finance.payment_reminder_sent` events.

## Exit State

- Each of the 6 callsites replaces the direct `$emailService->sendSingleEmail(...)` call inside its loop body with:
  1. Build a `DomainEventEnvelope` (event_name = `finance.payment_reminder_sent` for student reminders, `finance.parent_payment_reminder_sent` for parent reminders; `payload.type_key` = the existing string used in `resolve()`; `payload.recipient_targets` = `[['type' => 'student'|'parent', 'id' => $student->id]]`; `payload.data` = the existing `$contentData` array).
  2. Call `$publishAction->run($envelope)` (resolved via `app(PublishDomainEventAction::class)` — see Repair Q below).
- `EventIntentMapper` accepts `payload.type_key` directly (verified `EventIntentMapper.php:48`); no mapper change required.
- `try/catch` around `sendSingleEmail` becomes `try/catch` around `publishAction->run` (envelope publish can fail on DB outbox-row constraint). `$sentCount` / `$failedCount` counters retain meaning (counted at emit time, not delivery time — same semantics as today's `sendSingleEmail` which counts at queue-dispatch, not SMTP success).
- Each Action's `$invoice->update(['last_reminder_at' => $now])` still happens after successful emit. Matches existing semantics.
- Parity test per Action: assert outbox row + `notification_messages` + `notification_deliveries` + channel sink invoked.
- `./scripts/dev.sh test tests/Feature/Finance tests/Feature/Notification` green.

### Repair Q (must answer before swarm)

Current Finance Actions use `public static function run(array $data)`. Static methods can't have constructor-injected dependencies. Options:

- **Static + `app()` lookup:** `$publishAction = app(PublishDomainEventAction::class);` inside `run()`. Keeps existing call shape (`SendPaymentRemindersAction::run([...])`). Less idiomatic but zero ripple to callers.
- **Instance method:** Convert to `public function run(array $data)` with constructor DI. Callers (controllers, jobs) must inject the Action instead of `::run(...)`. Bigger ripple — every caller of the 4 Actions changes.

Recommended: **static + `app()` lookup** for M3b. Matches existing precedent in the same Actions (they already use `app(SettlementService::class)`, `app(EmailService::class)`, `app(EmailContentRegistry::class)`). Idiomatic refactor belongs to a separate Finance hygiene story, not bolted onto an outbox migration.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php` | edit — line 77 |
| `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php` | edit — line 60 |
| `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php` | edit — lines 82 + 152 |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` | edit — lines 81 + 147 |
| `tests/Feature/Finance/SendPaymentRemindersActionOutboxTest.php` | create — parity test |
| `tests/Feature/Finance/SendParentPaymentRemindersActionOutboxTest.php` | create — parity test |
| `tests/Feature/Finance/SendDueItemRemindersActionOutboxTest.php` | create — parity test (both loops) |
| `tests/Feature/Finance/SendDueItemParentRemindersActionOutboxTest.php` | create — parity test (both loops) |
| `docs/rules/contracts.md` | edit — add "Established `PublishDomainEventAction` cross-module exception" paragraph (Option A documentation) |
| `history/dynamic-email-templates/m3b-rollout-notes.md` | create — staging-verify outcome + rollback notes |

Total: **10 file ops.** At pack limit.

## DAG Row

`[M3a green-on-staging] → [M3b] → [M4 (jobs)] → [M5 (deprecate EmailController)]`

## Critical Patterns Applied

- **#1 unsafe-char fixtures** — every parity test uses `<>"&'` in student_name, invoice_code, subject substitutions.
- **#3 Octane reset** — `PublishDomainEventAction` is stateless; no `app->scoped()` change needed.
- **#6 sanitization parity matrix** — C2 (`RenderDraftEmailTemplateAction`) is parallel/independent. The outbox pipeline already invokes `EmailContentProvider->subject()` / `->htmlBody()` inside `HandleOutboxEventAction::buildRenderedEmail()`, so sanitization stays in one place.
- **#7 ≥2-row fixtures** — `SendDueItemRemindersAction` and `SendDueItemParentRemindersAction` each have TWO loops (lines 82/152, 81/147). Each parity test exercises ≥2 items per loop type (dng_request + invoice).
- **CLAUDE.md cross-module rule** — explicitly handled by Option A documentation in `docs/rules/contracts.md`.

## Outbox payload contract (inherited from M3a)

**M3b does NOT set `payload.rendered_email`.** Finance reminders flow through the registry path (`EmailContentRegistry::resolve('payment_reminder' | 'parent_payment_reminder')`), not the Option α short-circuit. The handler will resolve the registered `DbEmailContentProvider`, call `subject($data)` + `htmlBody($data)`, and produce rendered fields from the persisted template. This is the correct path because the reminder subject and body ARE the persisted template content, not draft content.

If a future caller needs to pre-render (e.g. one-off campaign send), it sets `payload.rendered_email` per critical pattern P1. M3b doesn't.

`payload.data` MUST mirror the existing `$contentData` array exactly:
```
[
  'campus_id' => int,
  'student_name' => string,
  'student_code' => string,
  'semester_code' => string,
  'invoice_code' => string,
  'balance_formatted' => string,
  'due_date' => string,
]
```

`payload.data` MUST NOT carry `dng_payment_request_id` — even though `SendDueItemRemindersAction` and `SendDueItemParentRemindersAction` have a `dng_request` branch with access to `$dngRequest->id`, the existing `$contentData` already uses `invoice_code` = `'DNG-'.$dngRequest->id` (verified `SendDueItemRemindersAction.php:76`). Carrying `dng_payment_request_id` would trigger `HandleOutboxEventAction.php:150` override path and clobber the pre-built data.

## Feasibility Notes

- Highest-risk M3 story. 6 callsites, cross-module, production reminder traffic.
- Risk: today's reminder is synchronous-to-EmailService. Switching to outbox means delivery is queue-mediated. `last_reminder_at` update semantics preserved.
- Risk: `finance.payment_reminder_sent` / `finance.parent_payment_reminder_sent` are new event_names. Grep before swarm to confirm no conflicts.
- Risk: `email_logs` audit-shadow row count behavior during cutover — verify M3a observation logs before greenlighting M3b.
- Rollback: revert 4 Finance Action files + remove the docs paragraph. ~5 files to revert.

## Handoff to Validating

Validating gates:
1. **Confirm M3a staging-verify recorded** in `m3a-rollout-notes.md` with date + verifier. If absent, return to user before any further validation. This is the HARD GATE.
2. **Confirm H1 (hygiene precursor) merged** — empty-string guard + caller-sanitize doc-comment in place on `HandleOutboxEventAction::buildRenderedEmail`.
3. Confirm `finance.payment_reminder_sent` and `finance.parent_payment_reminder_sent` event_names have no conflicts (grep `app/`, `database/`, `config/`).
4. Confirm payload.data shape matches across all 6 callsites and matches what `PaymentReminderEmailContent` / `ParentPaymentReminderEmailContent` consume.
5. Confirm `try/catch` boundary semantics: failed `publishAction->run` should count as `$failedCount` and skip the `last_reminder_at` update — matches today's `sendSingleEmail`-failure path.
6. Confirm decision on static-vs-instance refactor (recommend: static + `app()` lookup; no caller ripple).
7. Confirm Option A documentation paragraph in `docs/rules/contracts.md` is minimal and points to the 6 precedent callers.
8. Confirm parity-test pattern reusable from M3a (`tests/Feature/Notification/TestSendOutboxEmitTest.php`).
