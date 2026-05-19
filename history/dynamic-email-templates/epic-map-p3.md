# Epic Map: Dynamic Email Templates — P3 (Outbox migration + Retry fix)

**Mode:** `high-risk`
**Source:** [CONTEXT-p3.md](CONTEXT-p3.md) D1–D6, [approach-p3.md](approach-p3.md), [critical-patterns.md](../learnings/critical-patterns.md)
**Architecture / reality basis:** P2 shipped 2026-05-17. Outbox stack (`notification_event_outbox.payload JSON`, `notification_messages.data JSON`, `notification_deliveries`) is live. Legacy stack (`email_logs`, `EmailController`, `EmailService`, `SendBulk/SingleEmailJob`) still serves `/systems/email-history`. Retry path at `EmailController.php:336-370` discards original content. 12 source files touch `email_logs` (full inventory in approach-p3.md §Writers).

## Feature Outcome (when all epics finish)

Admin clicks Retry on a row in `/systems/email-history` → the row's original `payload` (already persisted in `notification_event_outbox.payload`) re-renders against the current template version via `EmailContentRegistry` → email arrives with correct content (no more `Retry: [subject]` literal body). Every legacy send entry point (`sendSingle`, `sendBulk`, `sendNotification`, `scheduleReminder`, plus any `EmailService::sendSingleEmail/sendBulkEmail` callers found in the spike) now emits an outbox event; nothing writes `email_logs` directly post-deploy. `/systems/email-history` reads from `notification_deliveries ⨝ notification_messages` and matches the existing `EmailHistoryModal.vue` props. Pre-cutoff rows (`notification_message_id IS NULL`) hide the Retry button with the D4 tooltip. The 7 NotificationOps CSRF + 30 finance test failures are either repaired or explicitly quarantined with a root-cause memo. The `email_logs` table is read-only with a `@deprecated` annotation on `EmailController` and a named follow-up sprint for table retirement.

## Epics

| Epic | Capability/Risk Area | Why It Exists | Stories | Proof Needed |
|---|---|---|---|---|
| **S. Spike (feasibility)** | Probe the 3 unknowns from approach-p3.md before any epic commits scope | Without spike, 4h of unknown unknowns drive into Epic M and inflate scope. Spike resolves payload-presence %, EmailService caller graph, and CSRF/finance test-fail shared-cause hypothesis. | S1, S2, S3 | (a) staging payload-coverage % ≥99% on last 30d; (b) full caller-graph of `EmailService::send*` across repo; (c) reproduce 1 CSRF + 1 finance fail in isolation, name shared root cause or separate. |
| **R. Retry correctness** | Re-wire Retry button to `RetryDeliveryAction` re-rendering from `payload` (D1) | The user-visible bug. Solving R without M (migration) is safe because outbox already persists payload — proven in approach-p3 §"D1 is already implementable". | R1, R2, R3 | Send → Retry → recipient receives byte-equivalent email (unsafe-character fixtures + ≥2 recipients per Critical Patterns #1, #7). UI hides Retry for pre-cutoff rows. |
| **M. Outbox migration** | Re-route every legacy send entry point through outbox; deprecate `EmailController`; re-point `/systems/email-history` UI to `notification_deliveries` | D2 + D5. Without M, new emails keep landing in `email_logs` and Retry continues to discard content on new rows. Largest blast-radius epic. | M1, M2, M3, M4, M5 | (a) parity test per legacy entry point: old API path → outbox row + delivery row + `email_log_id` linkage; (b) `/systems/email-history` returns identical shape to old endpoint; (c) Vue UI smoke (Playwright) loads + retries. |
| **C. Contracts + sanitization (REV-P2-04 / F7–F8)** | Formalize `EmailContentProvider` as a Contract under `app/Shared/Contracts/Notification/`; extract `RenderDraftEmailTemplateAction` so preview + testSend + new outbox path all reuse the same sanitization step | REV-P2-04 carries from P2; F7 is the Critical Pattern #6 (sibling-endpoint sanitization parity) reification. M will introduce new render call sites — without C, the sanitization matrix gains untracked columns. | C1, C2 | (a) Contract namespace + interface land; (b) sanitization matrix (endpoint × untrusted-field × sanitizer-called) is all-Y including new outbox-emitter paths. |
| **T. Test-debt repair** | Repair or quarantine the 7 NotificationOps CSRF fails + 30 finance fails (D6) | These were noted as unrelated in P2 validation. Spike S3 decides whether root cause is shared (cheap batch fix) or per-test (timebox to ≤30 fixes; quarantine the rest with a tracked follow-up). | T1, T2 | All 7 CSRF + all 30 finance tests either green or marked `->skip()` with `// quarantined: <root-cause-id>` + a follow-up bead. Full suite passes. |
| **F. P2 follow-ups (F9–F16)** | Mechanical P2-review leftovers that are easier to land alongside M than as a separate sprint | F9–F16 are low-risk cleanup, but they touch some of the same files M touches (e.g. `EmailController`, `EmailHistoryModal.vue`). Bundling avoids re-opening files twice. | F1 (catch-all pack) | Each F9–F16 line item from P2 review closes or is explicitly deferred with reason. |

## Story Queue

| Story | Epic | Outcome | Depends On | Feasibility Status |
|---|---|---|---|---|
| **S1** | S | Run staging query: `SELECT COUNT(*) FILTER (WHERE data IS NULL OR data = '{}')::float / COUNT(*) FROM notification_messages WHERE created_at > NOW() - INTERVAL '30 days'`. Document result. | none | HIGH risk if <99% (forces D4 cutoff redesign). Spike-blocking. |
| **S2** | S | `grep -rn 'EmailService::send' app/ resources/ database/` + cross-module map. Output: file:line list with module classification (Finance/Academic/Notification/legacy). | none | MEDIUM risk if >5 cross-module callers (M3 scope grows). |
| **S3** | S | Reproduce 1 of 7 CSRF fails + 1 of 30 finance fails in isolation. Memo: shared root cause Y/N, fix size estimate. | none | HIGH risk if not shared → T epic timebox tightens. |
| **R1** | R | `RetryDeliveryAction` accepts `notification_delivery_id` → loads `notification_messages.data` (or `notification_event_outbox.payload` fallback) → calls `EmailContentRegistry::resolve($message->type)->render($data)` → re-uses `EmailChannelAdapter`. Snapshot fields marked advisory. | S1 | MEDIUM risk; depends on S1 result. Touches Critical Pattern #3 (Octane). |
| **R2** | R | New JSON API endpoint `POST /api/admin/email/deliveries/{delivery}/retry` returning `ApiResponse::success`. `EmailHistoryModal.vue` Retry button switches to `useApi` (per Critical Pattern #5, NOT `useForm`). | R1 | LOW risk; mechanical UI wiring. |
| **R3** | R | `EmailHistoryModal.vue` hides Retry button when `delivery.notification_message_id === null`. Tooltip text per D4. Pest+Playwright tests with unsafe-character fixtures + ≥2 recipients (Critical Patterns #1, #7). | R2 | LOW risk. |
| **M1** | M | ⛔ **RETIRED 2026-05-19** — `ListDeliveriesQuery` already exists from prior P3 session (see `app/Modules/Notification/Queries/ListDeliveriesQuery.php`). Retirement memo in `current-story-pack-M1.md`. | n/a | — |
| **M2** | M | `EmailLogController` re-pointed to existing `ListDeliveriesQuery`. Optional small DTO adapter if `EmailHistoryModal.vue` prop shape diverges. Old `email_logs` read path kept behind feature flag `email_history.use_outbox` for 1-deploy rollback. | R3 | MEDIUM risk; user-visible. |
| **M3** | M | `EmailService::sendSingleEmail` + `sendBulkEmail` reimplement bodies to emit outbox events (via `DispatchNotificationAction` wrapper or direct `notification_event_outbox` insert + `HandleOutboxEventJob` dispatch). Legacy `email_logs` write stays as audit shadow (read-only after deploy). | S2, **C1 (HARD predecessor per S2 — 6 Finance Actions cross-module reach)** | HIGH risk; cross-module callers. Spike S2 confirmed blast radius. |
| **M4** | M | Migrate `SendBulkEmailJob` + `SendSingleEmailJob`: replace direct `EmailLog::create` with outbox emit. CSV-recipient bulk path batches outbox events per Critical Pattern (risk #6 in approach-p3.md). | M3 | MEDIUM risk; queue throughput needs measurement. |
| **M5** | M | `EmailController` legacy endpoints (`sendSingle`, `sendBulk`, `sendNotification`, `scheduleReminder`) marked `@deprecated`, bodies route through `EmailService` (which now emits outbox per M3). `app('routes')` lookup keeps URLs alive for any external caller. | M3 | LOW risk; thin façade rewrite. |
| **C1** | C | `app/Shared/Contracts/Notification/EmailContentProvider.php` Contract created. Existing `EmailContentRegistry` + `DbEmailContentProvider` implement it. Cross-module reads (Finance reminder Actions) switch to the Contract per `CLAUDE.md` Forbidden Patterns. | none | LOW risk; interface extraction. |
| **C2** | C | `RenderDraftEmailTemplateAction` extracted (F7). `preview()`, `testSend()`, and new outbox-emitter render call sites all route through it. Sanitization matrix asserted in a Pest test that fails on any new endpoint that doesn't call the sanitizer (Critical Pattern #6). | C1 | LOW risk; refactor + matrix test. |
| **T1a** | T | **(forked from T1 per S3)** CSRF batch fix: 7 NotificationOpsController fails share root cause = web-group `VerifyCsrfToken` rejects `postJson`. Fix = route exemption OR test-level `withoutMiddleware`, validating decides. ~30 min. | S3 | LOW risk; batch fix. |
| **T1b** | T | **(forked from T1 per S3)** Finance auth backfill: ~30 fails missing `actingAs($student, 'sanctum')`. 10-min `setUp` regression check first — if shared, batch fix; else per-test edits. | S3 | MEDIUM risk; per-test scope possible. |
| **T2** | T | Quarantine memo: for any test that resists repair within budget, add `->skip('quarantined: <root-cause-id>, follow-up bead Q-NN')` + a row in `history/dynamic-email-templates/quarantine-log.md` naming the follow-up. | T1a, T1b | LOW risk; only opens if T1b hits budget ceiling. |
| **F1** | F | **AMENDED 2026-05-19** — scope is F12–F16 only (5 mechanical 1-file edits). | none | LOW risk; mechanical. |
| **F11** | F | **NEW** (spun from F1) — Extract `ListNotificationTemplatesQuery` + move `+ ['updated_by_user_id'=>...]` into `UpdateNotificationTemplateAction`. Design-level cleanup, separate pack `current-story-pack-F11.md` (not yet authored). | none | LOW-MEDIUM risk. |
| **F-tests** | F | **NEW** (spun from F1) — Regression-guard test backfill (~2hr): F9 + F10 + F16-complex items. Separate pack `current-story-pack-F-tests.md` (not yet authored). | none | LOW risk; pure test additions. |

Total: **17 stories** across 6 epics (3 spikes + 14 execution stories).

## Critical-path & parallelism

```text
Spike wave (blocking for downstream epics):
  S1 ─┐
  S2 ─┼─ all parallel; 4h total timebox per approach-p3.md.
  S3 ─┘

Execution waves (post-spike):

  AMENDED post-S-batch: C1 promoted to critical path (hard predecessor to M3);
  T1 forked into T1a (CSRF batch) + T1b (Finance auth); R-epic parked on S1.

  S2 → C1 (Contract, CRITICAL) → M3 (EmailService outbox emit) → M4 (jobs) → M5 (deprecate)
                              ↘ C2 (RenderDraftEmailTemplateAction)

  S2 → M1 (query) → M2 (controller re-point) ──→ F1 (P2 cleanup catch-all)

  S1 (operator-blocked) → R1 (retry action) → R2 (api+UI) → R3 (test+UI gate)

  S3 → T1a (CSRF batch, ~30min)
     → T1b (Finance auth backfill) → T2 (quarantine, conditional)

Realistic swarm width:
  Wave 1 (spike, DONE 2026-05-19): S1 (parked-operator), S2 ✅, S3 ✅
  Wave 2 (unblocked NOW): 4 packs parallel — C1, M1, T1a, F1
  Wave 2.5 (operator-blocked): R1 — waits for S1
  Wave 3: C2, M2, M3, T1b
  Wave 4: M4, T2 (conditional)
  Wave 5: M5
```

## Current Story To Prepare

**Recommended: spike batch `S1+S2+S3` packaged as `current-story-pack-S-batch.md`.**

Why this one first:

1. **Smallest believable feasibility net.** All three spikes are read-only investigation; total budget 4h per approach-p3.md §Mode gate.
2. **Unblocks every downstream epic.** R, M, and T all have a spike gate in their entry-state. C is the only epic that can start without spike (Contract extraction is pure refactor) — but parallelising C1 with the spike wave is fine.
3. **Resolves the three highest-leverage unknowns:**
   - S1 → confirms D1 is implementable without new columns (approach-p3 already claims this; spike confirms with real data).
   - S2 → sizes M3 scope. If `EmailService::send*` has callers in Finance/Academic modules, M3 grows from 2 files to N. Without S2, M3 is unscoped.
   - S3 → forks T epic into either "1 root-cause fix" or "37 individual fixes". 30× scope difference.
4. **No file writes outside `history/dynamic-email-templates/spike-*.md`.** Spike packs cannot regress existing code.

Testable exit for spike batch:
- `history/dynamic-email-templates/spike-S1-payload-coverage.md` — staging query result + interpretation (≥99% → go; <99% → revisit D4 with stakeholder).
- `history/dynamic-email-templates/spike-S2-emailservice-callers.md` — every file:line touching `EmailService::sendSingleEmail` or `sendBulkEmail`, classified by module.
- `history/dynamic-email-templates/spike-S3-test-fail-root-cause.md` — reproduce-in-isolation log for 1 CSRF + 1 finance fail, shared-cause Y/N memo, scope estimate.
- Decision matrix: each downstream epic's `Depends On` resolved to GO/REVISIT-SCOPE/STOP.

## Approval Summary

- **Mode:** `high-risk` — cross-stack migration + user-visible Retry button + unknown test-debt scope (approach-p3.md §Mode gate).
- **Shape:** Epic Map + spike-first current story — 6 capability/risk areas, not 6 sequential phases.
- **17 stories** across 6 epics. Foundation = spike batch (S1+S2+S3). Critical-path = spike → (C1 ∥ R1 ∥ M1 ∥ T1) → fan-out.
- **Current story to prepare next (post-approval):** `current-story-pack-S-batch.md` — read-only spike batch.
- **Validating must clear 7 questions** before bead creation (approach-p3.md §Validating questions).
- **Out of scope:** full `email_logs` table deletion (P3-followup), backfill of historical rows (D4), rate-limit redesign (only if S3 points there), per-row "as sent" historical fidelity (re-opens D1).
- **Critical patterns explicitly inherited:** #1 unsafe-char fixtures (R3, C2 tests); #3 Octane reset (R1); #5 useApi vs useForm (R2); #6 sanitization parity matrix (C2); #7 ≥2-row fixtures (R3, M4); #8 pack-as-bead DAG (this map).

## Stop

Planning has chosen the smallest work shape. Approve it before current story prep. Tough work uses an epic map; beads wait until feasibility passes.
