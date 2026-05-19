# M3 Repair Memo — addressing validation 2026-05-19

**Source:** validation report on `current-story-pack-M3.md` rejected with 3 findings.
**Status of findings after code re-check:**

| Finding | Original verdict | After re-check | Repair |
|---|---|---|---|
| F1 — missing type_keys for due_item_reminder / due_item_parent_reminder | BLOCKING | **INVALIDATED** — all 4 Finance reminders already resolve `payment_reminder` / `parent_payment_reminder` (verified `SendDueItemRemindersAction.php:25`, `SendDueItemParentRemindersAction.php:28`, `SendPaymentRemindersAction.php:29`, `SendParentPaymentRemindersAction.php:35`). No new type_keys needed. | drop |
| F2 — `HandleOutboxEventAction::buildEmailData` clobbers Finance payload | BLOCKING | **CONCERN (not blocking)** — the override block (`HandleOutboxEventAction.php:150`) is gated on `data['dng_payment_request_id'] !== null`. Existing Finance reminder `$contentData` does NOT carry that key (uses `invoice_code`, not `dng_payment_request_id`). Path is clean as long as M3 emitter follows the same convention. | document in pack |
| F3 — dispatcher contract gap (Finance → `PublishDomainEventAction` is Notification internal) | BLOCKING | **DECISION REQUIRED** — established precedent across `app/Modules/Academic`, `app/Modules/Finance/Services/PaymentService.php:13`, `app/Modules/Finance/Dng/Services/DngPaymentService.php:25`, `app/Actions/Form/SubmitResponseAction.php:30`, multiple `app/Actions/Query/*.php` all import `App\Modules\Notification\Actions\PublishDomainEventAction` directly. M3 either accepts the precedent or introduces `App\Shared\Contracts\Notification\NotificationDispatcher` to align with CLAUDE.md letter. | user picks |

## Validating questions (must answer before M3 swarms)

### Q1 — Dispatcher contract approach (F3)

**Option A — accept precedent.** M3 Finance Actions inject `PublishDomainEventAction` directly, matching the 6 existing cross-module callers. Document the established exception in `docs/rules/contracts.md` (one paragraph). Pack file-ops stays at 8-10.

- Pros: matches reality; no new abstraction; faster to ship.
- Cons: enshrines a CLAUDE.md Forbidden Pattern violation in docs.

**Option B — introduce dispatcher Contract.** Create `app/Shared/Contracts/Notification/NotificationDispatcher.php` with `dispatch(DomainEventEnvelope $envelope): void`. Bind to `PublishDomainEventAction` in `NotificationServiceProvider`. Finance Actions inject the Contract. Pack grows by 2 file ops (Contract + binding).

- Pros: aligns with CLAUDE.md; opens a clean migration path for the 6 legacy callers.
- Cons: introduces an interface with one implementation (YAGNI risk); leaves legacy callers as-is unless a cleanup epic is scheduled.

### Q2 — Pack split (scope discipline)

Pack already lists 8-10 file ops at limit. Recommend split:

**M3a (low-risk first):** `NotificationTemplateController.php:160` test-send button only. Proves outbox-emit pattern with one callsite, same module (no cross-module concern), no Contract decision needed. ~3 file ops including parity test.

**M3b (cross-module wave):** 6 Finance callsites in 4 Actions + parity tests. Hard predecessor = M3a green on staging. Applies F3 decision (Option A or B).

Alternative: keep as one M3 if user accepts higher rollback blast-radius.

## Required pack edits if Option A chosen

- §"Critical Patterns Applied" — replace "Finance imports Shared Contract namespace, NOT Notification internal" with "Finance imports `App\Modules\Notification\Actions\PublishDomainEventAction` per established precedent (PaymentService, DngPaymentService, Academic PublishCourseStageChangedNotificationAction). Documented exception, not a new violation."
- §"Handoff to Validating" — drop Q4 (CSV-bulk batching, irrelevant for M3 callers; verified zero `sendBulkEmail` callers in M3 scope).
- New §"Outbox payload contract" — payload.data MUST mirror existing `$contentData` shape (campus_id, student_name, student_code, semester_code, invoice_code, balance_formatted, due_date) and MUST NOT carry `dng_payment_request_id` (avoids `HandleOutboxEventAction` override path).

## Required pack edits if Option B chosen

All of Option A's edits, plus:

- §"File Ops Inventory" — add `app/Shared/Contracts/Notification/NotificationDispatcher.php` (create) and `app/Modules/Notification/Providers/NotificationServiceProvider.php` (edit — register binding).
- §"Depends on" — keep C1, no new dependencies (Contract is sibling).
- §"DAG Row" — unchanged.

## Recommendation

**Option A + pack split (M3a then M3b).** Rationale: precedent is established and stable; the dispatcher Contract has no second implementation in sight; M3a/M3b split protects production traffic. The CLAUDE.md tension is real but better addressed in a dedicated "Shared dispatcher contract" cleanup epic in P4, not bolted onto M3.

## Decision needed from user

1. Q1: Option A or Option B?
2. Q2: Split M3 into M3a + M3b, or keep monolithic?

After answers, planning will write the amended `current-story-pack-M3.md` (or `-M3a.md` + `-M3b.md`) and hand off to validating.
