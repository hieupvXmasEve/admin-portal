# Dynamic Email Templates — P3 Context

**Feature slug:** dynamic-email-templates
**Phase:** P3
**Date:** 2026-05-18
**Exploring session:** complete
**Scope:** Deep
**Domain types:** SEE, RUN, ORGANIZE

## Feature Boundary

Close the gap that P2 left: the user-visible `/systems/email-history` page and every legacy send/retry endpoint still run on the old `App\Http\Controllers\Api\V1\Admin\EmailController` + `email_logs` stack, which does not persist enough data to re-render an email accurately on retry. P3 migrates every send path onto the new `Modules/Notification` outbox stack, persists `template_key + payload` for every outgoing email, switches `/systems/email-history` to read from `notification_deliveries`, and closes P2 follow-ups (REV-P2-04, F7–F16). P3 ends when the legacy stack is dark and the test suite is green.

## Locked Decisions

These are fixed. Planning must implement them exactly.

- **D1 — Retry semantics: re-render from template + original data.**
  - Every send persists `template_key` + `payload` (the data dictionary used to render). Retry re-renders against the current template version using the same payload. Snapshot fields (`rendered_subject`, `rendered_html`) become advisory only — not the source of truth for retry.
  - Trade-off accepted: if the template was edited after the original send, retry will reflect the new content. Users see the latest template at retry time.

- **D2 — Migration path: migrate UI to new stack, retire legacy gradually.**
  - `/systems/email-history` (`EmailLogController`) is re-pointed at `notification_deliveries` (joined with `notification_messages` / `notification_email_templates`).
  - Legacy `App\Http\Controllers\Api\V1\Admin\EmailController` is marked deprecated. `email_logs` table stays read-only during P3 and is retired in a follow-up after parity is proven in production.

- **D3 — Sprint shape: single P3 sprint covers everything.**
  - One swarm-driven sprint, same shape as P2. Contents: (a) retry bug, (b) email-history UI migration, (c) all legacy send endpoints migrated to outbox, (d) F7–F16 cleanup, (e) REV-P2-04 Contract gap, (f) 7 NotificationOpsController CSRF fails + 30 finance fails investigation/repair.

- **D4 — Historical email_logs rows: forward-fix only, no backfill.**
  - Rows created before P3 lack `payload` → cannot be safely re-rendered. UI hides or disables the Retry control for those rows and shows "Retry unavailable: original data not stored. Send a new email instead."
  - No backfill script. Cutoff is the migration deploy timestamp.

- **D5 — Legacy send-endpoint migration: full migration, no dual-write.**
  - Every legacy send endpoint (`sendSingle`, `sendBulk`, `sendNotification`, `scheduleReminder`, plus any other writer of `email_logs`) routes through the existing outbox flow (`HandleOutboxEventAction` / `PersistIntentAction`) so every new email lands in `notification_deliveries` with `template_key + payload`.
  - Statistics + filter endpoints (`logs`, `statistics`, `showLog`, `getUserRoles`, `getCampuses`) re-point at `notification_deliveries` or stay legacy-read-only — planning decides per endpoint.

- **D6 — Pre-existing test debt is in scope.**
  - 7 CSRF failures in `NotificationOpsControllerTest` and ~30 finance failures in the composite suite (e.g. `StudentFinanceDngAccessTest`) are repaired in this sprint. They must be triaged early because root cause may be shared with the migration work.

## Specific Ideas And References

- Bug evidence the user surfaced: retry posts `Retry: [Metropolia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ SPRING2026` as the body. Confirmed in `app/Http/Controllers/Api/V1/Admin/EmailController.php:336-370` — comment in code reads `// We need to retrieve the original content - for simplicity, we'll send a basic retry`.
- The new stack already snapshots `rendered_subject` / `rendered_html` (`PersistIntentAction.php:51-52`) but does NOT persist `template_key + payload`. P3 must add those columns to `notification_deliveries` (or a sibling table) so D1 is implementable.
- `RetryDeliveryAction` (`app/Modules/Notification/Actions/RetryDeliveryAction.php`) is the correct retry pathway and re-uses `SendNotificationDeliveryJob`. P3 routes the email-history Retry button to this action via a new endpoint or by re-pointing the existing one.

## Existing Code Context

### Reusable Assets

- `app/Modules/Notification/Actions/HandleOutboxEventAction.php` — outbox-side renderer. Already calls `EmailContentRegistry` and produces `rendered_subject` etc. P3 extends it to also persist `template_key + payload`.
- `app/Modules/Notification/Actions/PersistIntentAction.php` — writes `notification_deliveries`. Extend the `$renderedEmail` array contract to carry `template_key` and original `payload`.
- `app/Modules/Notification/Actions/RetryDeliveryAction.php` — correct retry entrypoint. Repoint email-history Retry button here.
- `app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php` and `EmailChannelAdapter.php` — channel adapters already pick the right path. P3 may flip the order so payload-re-render becomes the primary path and snapshot is fallback (per D1).
- `app/Modules/Notification/EmailContent/EmailContentRegistry.php` + DbEmailContentProvider — already campus-aware. F8 / REV-P2-04 formalises this as a Contract.

### Established Patterns

- Outbox-write-then-dispatch: `HandleOutboxEventAction` → `PersistIntentAction` → `SendNotificationDeliveryJob`. Migration of legacy senders must conform.
- ApiResponse envelope and FormRequest validation — apply to any new admin endpoints (per `CLAUDE.md` Forbidden Patterns).
- `EmailContentRegistry` octane-safety pattern from P2 story B4 — preserve in any new content-provider wiring.

### Integration Points

- `routes/web/systems.php` — `/systems/email-history` page + AJAX endpoints. Repoint to new controller / new query.
- `routes/api/admin.php:72-81` — every legacy admin email endpoint. Each one is a migration target.
- `resources/js/pages/Admin/BulkEmail/components/EmailHistoryModal.vue` — frontend retry-button caller. Update to: hide Retry for pre-cutoff rows, show new "unavailable" tooltip when applicable.
- `app/Models/EmailLog.php` and any `email_logs` foreign keys — audit before retiring the table.

## Canonical References

- `history/dynamic-email-templates/CONTEXT.md` — P1 boundary (still valid for new stack).
- `history/dynamic-email-templates/approach-p2.md` — P2 design including outbox flow.
- `history/dynamic-email-templates/validation-report-p2.md` — P2 validation, including the 7 CSRF + 30 finance pre-existing failures noted as unrelated.
- `history/learnings/20260517-dynamic-email-templates-p2.md` — P2 learnings, especially Purifier fragment vs document, EmailContentRegistry octane safety.
- `history/learnings/critical-patterns.md` — 6 promoted critical patterns from P2 compounding.

## Outstanding Questions

### Resolve Before Planning

- [ ] Where does `payload` live? New columns `template_key VARCHAR` + `payload JSON` on `notification_deliveries`, or a sibling `notification_delivery_payloads` table for size reasons? Decide based on max payload size (some finance reminders carry student rosters) and PII retention policy.
- [ ] PII / retention policy for `payload`: parent name, student names, possibly amounts. Confirm with stakeholders whether full payload may be persisted indefinitely or needs a retention window / encryption-at-rest.
- [ ] Retire date for `email_logs` table — P3 keeps it read-only, but planning must propose a target follow-up sprint (P4?) so the deprecation isn't open-ended.

### Deferred To Planning

- [ ] Root cause of 7 NotificationOpsController CSRF failures — is it shared session-seed pattern from P2 story B5, or genuine controller bug?
- [ ] Root cause of 30 finance composite-suite failures — confirm none touch new template stack before scheduling the fix.
- [ ] Inventory of every writer of `email_logs` (not just `EmailController` — there may be jobs, listeners, console commands). Scout before planning Phase 2.
- [ ] Existing `EmailContentProvider` interface vs F8 Contract: choose name and namespace per `app/Shared/Contracts/Notification/` convention.

## Deferred Ideas

- Backfill of historical `email_logs` rows — explicitly out of scope (D4).
- Full deletion of `email_logs` table — out of scope for P3, scheduled as P3-followup.
- Per-user / per-campus email rate-limiting redesign — touched only if 30 finance failures point there.
- Email preview "as it was sent" historical view — D1 already implies "as it would re-render today" instead. If the user wants true point-in-time fidelity later, re-open D1.

## Handoff Note

CONTEXT-p3.md is the source of truth for the P3 sprint. Decision IDs D1–D6 are stable and quoted in plans, beads, reviews. Planning starts by resolving the three "Resolve Before Planning" questions (column shape, PII policy, retire date), then scouting every writer of `email_logs` before designing Phase 1.
