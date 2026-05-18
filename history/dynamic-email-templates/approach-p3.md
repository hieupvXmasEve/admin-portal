# Dynamic Email Templates — P3 Approach

**Feature slug:** dynamic-email-templates
**Phase:** P3
**Date:** 2026-05-18
**Mode:** high-risk
**CONTEXT source:** `history/dynamic-email-templates/CONTEXT-p3.md` (D1–D6 locked)

## Mode gate

Mode = **high-risk**. Justifications:

1. **Cross-stack data migration:** legacy `email_logs` writers retire → all send endpoints reroute through outbox. Production traffic-bearing.
2. **Data-fidelity invariant change:** D1 changes "what gets sent on retry" from "stub literal" to "re-render from payload". Wrong wiring → silent regression at the user-visible Retry button.
3. **Unknown root cause in scope:** 7 NotificationOps CSRF fails + 30 finance fails (D6). Could be shared session-seed pattern (cheap) or a real bug in P2 code (expensive). Until probed, scope is unbounded.
4. **Multi-stack surface:** Vue (EmailHistoryModal), Inertia controllers, JSON API, Laravel queue jobs, outbox flow, FrankenPHP/Octane lifecycle.

Direct/small/standard modes do not protect work that can break Sent-mail truth at the user-visible Retry button. High-risk → **Epic Map + spike-style current story for feasibility**.

## Discovery findings

### Stack inventory (verified)

- **Legacy stack:** `App\Http\Controllers\Api\V1\Admin\EmailController` + `App\Services\EmailService` + `App\Models\EmailLog` + `App\Jobs\SendBulkEmailJob` + `App\Jobs\SendSingleEmailJob`. Wired to `/systems/email-history` UI via `App\Http\Controllers\EmailLogController`. Retry path at `EmailController.php:336-370` discards original content.
- **New stack:** `app/Modules/Notification/*`. Outbox flow: `notification_event_outbox` (has `payload JSON`) → `HandleOutboxEventAction` → `PersistIntentAction` writes `notification_messages` (has `data JSON`) + `notification_deliveries` (has `rendered_subject/rendered_html/rendered_text` snapshot + `email_log_id` FK back to legacy).

### Critical schema fact — D1 is already implementable without new columns

`notification_event_outbox.payload` (JSON, line 23 of migration) **already persists the original data dictionary that produced the rendered email.** `notification_messages.data` (JSON, line 24) carries it forward to message level. CONTEXT-p3.md's "Resolve Before Planning #1" (`payload` column shape) is **no longer open** — the column exists. Validating must confirm payload reaches retry pathway without truncation.

This removes the biggest schema-risk item planned at exploring time.

### EmailLog model surface

`App\Models\EmailLog` already has a `metadata JSON` column. No new column on legacy is needed for D4 either — D4 says "disable Retry for pre-cutoff rows", not "backfill". UI gates on absence of `notification_deliveries` row pointing back via `email_log_id`.

### Writers of `email_logs` (full inventory for D5)

Files that touch `email_logs`:
- `app/Models/EmailLog.php` (model)
- `app/Http/Controllers/Admin/EmailMonitoringController.php` (read)
- `app/Http/Controllers/EmailLogController.php` (Inertia page; reads + retry-trigger)
- `app/Http/Controllers/Api/V1/Admin/EmailController.php` (write + buggy retry)
- `app/Jobs/SendBulkEmailJob.php` (write)
- `app/Jobs/SendSingleEmailJob.php` (write)
- `app/Modules/Notification/Models/NotificationDelivery.php` (FK reference only — reads `email_log_id`)
- `app/Services/EmailService.php` (top-level write orchestrator)
- `app/Services/EmailLoggingService.php` (write helper)
- `app/Services/EmailTemplateService.php` (read template tied to log)
- `app/Services/EmailTemplateVersioningService.php` (read)
- `app/Console/Commands/CleanupEmailLogs.php` (delete)
- `app/Models/User.php` (relation)

→ **12 source files** touch `email_logs`. Migration scope confirmed nontrivial but bounded.

### Critical patterns to respect

From `history/learnings/critical-patterns.md`:

| Pattern | Application to P3 |
|---|---|
| Parity tests need unsafe-character fixtures | Every payload-replay test must include `<`, `>`, `"`, `&`, `'` in every interpolated variable. |
| Per-tenant invariants need observer + provisioner | Any new per-campus default templates retain `CampusObserver` + `NotificationEmailTemplateProvisioner`. |
| Singleton state under Octane | `EmailContentRegistry` retry consumer must not introduce new singleton mutable state without scoped binding. |
| Storage decisions are about lifecycle | Storing payload on `notification_event_outbox` (existing) vs `email_logs.metadata` (legacy) is correct per lifecycle: outbox payload has retry-aware lifecycle; legacy metadata has audit lifecycle. Do **not** stuff payload into `email_logs.metadata`. |
| Inertia useForm ↔ response-shape pairing | EmailHistoryModal Retry button uses non-navigating API path → must use `useApi`/`useApiRequest`, NOT `useForm`. |
| Sibling-endpoint sanitization parity | If P3 adds new admin endpoints for testing/preview, every sibling reuses sanitizer (extract Action — F7 is exactly this). |
| Per-recipient loops need ≥2-row fixtures | Re-render tests must cover ≥2 recipients per send. |

### Path (decision-by-decision wiring)

- **D1 (re-render from template + payload):** Retry path = `RetryDeliveryAction` re-renders via `EmailContentRegistry::resolve($notification_messages.type)->render($notification_messages.data ?? $outbox.payload)` then re-uses `EmailChannelAdapter`. Snapshot fields become advisory.
- **D2 (UI migration):** `EmailLogController::index` rewrites to query `notification_deliveries` join `notification_messages`. Output shape matches existing `EmailHistoryModal.vue` props so frontend changes minimal.
- **D3 (one sprint):** Epic Map below.
- **D4 (forward-fix):** Vue Retry button checks `delivery.notification_message_id !== null` (or equivalent) → hides Retry + shows tooltip when absent.
- **D5 (legacy senders migrate):** Each legacy entry point routes through `DispatchNotificationAction` (new wrapper) → outbox → existing pipeline. `EmailService::sendSingleEmail`/`sendBulkEmail` reimplement as outbox-emitters.
- **D6 (test debt):** Spike first (timebox 4h). Hypothesis: 7 CSRF fails are P2 session-seed pattern bleed; 30 finance fails are CheckCampusSelected middleware gap. Confirm before scoping repair.

### Files in scope (preliminary)

Will be refined per-epic in current story pack.

- New: `app/Modules/Notification/Actions/RetryDeliveryFromOutboxAction.php` (or extend existing `RetryDeliveryAction.php`), `app/Modules/Notification/Queries/ListDeliveriesQuery.php`, `app/Shared/Contracts/Notification/EmailContentProvider.php` (REV-P2-04 / F8), `app/Modules/Notification/Actions/RenderDraftEmailTemplateAction.php` (F7).
- Modify: `app/Modules/Notification/Actions/RetryDeliveryAction.php`, `app/Http/Controllers/EmailLogController.php`, `resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue`, `app/Services/EmailService.php`, `app/Jobs/SendBulkEmailJob.php`, `app/Jobs/SendSingleEmailJob.php`, `routes/web/systems.php`, `routes/api/admin.php`.
- Deprecate: `app/Http/Controllers/Api/V1/Admin/EmailController.php` (mark `@deprecated`, keep readable while migration lands).
- Tests: per-Epic stories.

## Risks

1. **`notification_messages.data` may be `nullable` — old rows might be empty.** Probe in spike: confirm every recent row has populated `data`. If gaps exist, treat as pre-cutoff (D4) even though row is in new stack.
2. **`email_log_id` linkage may be inconsistent.** Some new-stack deliveries may have `email_log_id = null` (when sent without legacy bridge). UI must show those correctly.
3. **EmailService is called from many places.** Migration to outbox might cascade into Finance/Academic modules that import `EmailService` directly. Discovery scope underestimated. → spike must inventory callers of `EmailService::sendSingleEmail/sendBulkEmail` repo-wide.
4. **Octane lifecycle on the new outbox-emitter wrapper.** Per Critical Pattern #3, any new singleton must use `app->scoped()` + reset hook.
5. **Test-debt root cause may be larger than 4h spike.** Spike outcome decides whether D6 expands into its own epic.
6. **CSV recipient lists** (e.g. `sendBulkEmail` with 1000 recipients) — each becomes 1 outbox event. Outbox throughput / queue depth needs validating. May force batching strategy.
7. **`Inertia::flash` vs JSON response divergence** at Retry endpoint per Critical Pattern #5.

## Proof needs (validating gates)

- **Spike outcome (4h timebox):** confirm payload presence in outbox for ≥last 7 days production-equivalent, inventory EmailService callers, root-cause sample of 7 CSRF + 30 finance fails.
- **Re-render parity test:** new retry vs original send, ≥2 recipients, unsafe-character fixtures.
- **Migration parity test per legacy entry point:** send via old API path → assert `notification_deliveries` row + outbox payload + `email_log_id` linkage all present.
- **EmailHistoryModal UI smoke (Playwright):** old row → Retry hidden; new row → Retry visible + click → email sent with original content.
- **Octane safety:** named reset hook OR `app->scoped()` for any new stateful binding.

## Validating questions

- [ ] Spike #1: Is `notification_messages.data` populated on ≥99% of rows from the last 30 days? (sample query against staging.)
- [ ] Spike #2: List every caller of `EmailService::sendSingleEmail` and `EmailService::sendBulkEmail` across the repo. Any caller in Finance/Academic/Notification modules that ISN'T already going through outbox?
- [ ] Spike #3: Root cause sample — pick 1 of the 7 CSRF fails + 1 of the 30 finance fails, reproduce in isolation. Shared cause or separate?
- [ ] Schema confirm: is `notification_messages.type` sufficient as the `template_key` per D1, or do we need an explicit `template_key` column?
- [ ] PII retention policy for `notification_event_outbox.payload` — currently retained forever?
- [ ] Retire date for `email_logs` table (post-P3 sprint name + ETA).
- [ ] Octane: which existing singleton in new stack would absorb the retry wiring, and is its scoped binding pattern documented?

## Open questions (out of scope for P3)

- Full deletion of `email_logs` (P3 keeps it read-only).
- Backfill of historical `email_logs` rows (D4 closes this).
- Per-user / per-campus rate limit redesign (only if finance fails point there).
