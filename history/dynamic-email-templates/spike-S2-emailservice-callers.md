# Spike S2 — `EmailService::send*` caller graph

**Run date:** 2026-05-19
**Operator:** orchestrator (Bash grep, read-only)
**Pack:** [current-story-pack-S-batch.md](current-story-pack-S-batch.md) §S2
**Status:** ✅ COMPLETE

## Commands

```bash
grep -rn "EmailService" app/ --include="*.php"
grep -rn "sendSingleEmail\|sendBulkEmail\|sendNotification\|scheduleReminder" app/ --include="*.php"
```

## Caller classification

### Direct `sendSingleEmail` / `sendBulkEmail` calls (= M3 migration targets)

| File:line | Module | Call shape | Cross-module? | Migration target |
|---|---|---|---|---|
| `app/Http/Controllers/Api/V1/Admin/EmailController.php:59` | legacy | `sendSingleEmail` (via `$this->emailService`) | No (legacy) | Outbox emit (M5 deprecates this controller) |
| `app/Http/Controllers/Api/V1/Admin/EmailController.php:128` | legacy | `sendBulkEmail` | No (legacy) | Outbox emit (M5) |
| `app/Http/Controllers/Api/V1/Admin/EmailController.php:350` | legacy | `sendSingleEmail` (retry path — the bug source) | No (legacy) | Replaced by R1 (`RetryDeliveryAction`) |
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php:160` | Notification | `app(EmailService::class)->sendSingleEmail` (test-send button) | Same module | Re-route via `RenderDraftEmailTemplateAction` + outbox (C2 + M3) |
| `app/Modules/Notification/Channels/EmailChannelAdapter.php:29` | Notification | `sendSingleEmail` | No (this IS the outbox channel) | **KEEP** — outbox sink, not source |
| `app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php:31` | Notification | `sendSingleEmail` | No (this IS the outbox channel) | **KEEP** — outbox sink, not source |
| `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php:77` | **Finance** | `sendSingleEmail` (loop body) | ⚠️ **YES** | Outbox emit via Contract (C1) — also Critical Pattern #7 ≥2-row fixture applies |
| `app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php:60` | **Finance** | `sendSingleEmail` (loop body) | ⚠️ **YES** | Outbox emit via Contract (C1) |
| `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php:82` | **Finance** | `sendSingleEmail` (loop body) | ⚠️ **YES** | Outbox emit via Contract (C1) |
| `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php:152` | **Finance** | `sendSingleEmail` (second loop) | ⚠️ **YES** | Outbox emit via Contract (C1) |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php:81` | **Finance** | `sendSingleEmail` (loop body) | ⚠️ **YES** | Outbox emit via Contract (C1) |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php:147` | **Finance** | `sendSingleEmail` (second loop) | ⚠️ **YES** | Outbox emit via Contract (C1) |

### Direct send-shape calls NOT through `EmailService`

| File:line | Method | Notes |
|---|---|---|
| `app/Http/Controllers/Api/V1/Admin/EmailController.php:165` | `sendNotification` (own controller method) | Legacy endpoint — façade (M5) |
| `app/Http/Controllers/Api/V1/Admin/EmailController.php:219` | `notificationService->scheduleReminder` | Different service — out of M3 scope unless S3 finds shared cause |
| `app/Http/Controllers/Api/V1/Lecturer/StudentController.php:323,332` | `sendNotificationToStudent` (local helper) | Lecturer-side — different shape, defer |
| `app/Listeners/AssessmentDeadlineListener.php:76` | `notificationService->scheduleReminder` | Different service — defer |
| `app/Services/ScheduledNotificationService.php:157,207,260,280,312` | `notificationService->scheduleReminder` (5 callers) | Different service — defer (Academic-side) |
| `app/Console/Commands/ScheduleAssessmentReminders.php:73,99` | `scheduleReminderForAssessment` (local helper) | Wraps notificationService — defer |

### DI-only / import-only (no migration impact)

| File:line | Notes |
|---|---|
| `app/Http/Controllers/Admin/EmailMonitoringController.php:7,18,20` | Constructor DI, read-only monitoring; safe to leave |
| `app/Jobs/SendBulkEmailJob.php:8,57,85` | Job that writes `email_logs` directly — migration target (M4) |
| `app/Services/EmailService.php:19` | Service class itself (M3 rewrites the body) |
| `app/Console/Commands/TestEmailConfiguration.php:48,153,158` | Diagnostic command; safe (or update opportunistically) |

## Summary numbers

- **Direct migration targets (sendSingle/sendBulk):** **12 callsites in 8 files**
- **Cross-module reach (Finance → EmailService):** **6 callsites in 4 files** ⚠️ confirms CLAUDE.md Forbidden Patterns violation
- **Channel sinks (keep as-is):** 2 (`EmailChannelAdapter`, `RenderedEmailChannelAdapter`)
- **`scheduleReminder` deferred targets:** 7 callsites (different service; out of P3 scope unless S3 reroutes)
- **DI-only / dead imports:** 3 files

## Findings

### Critical findings

1. **6 Finance Actions reach `EmailService` directly across module boundary.** This violates `CLAUDE.md` § "Cross-module communication: Module A needing data from Module B → **must use a Contract**". C1 (Contract) is **hard-blocking** for M3. Cannot route Finance Actions through outbox without the Contract; raw `app(EmailService::class)` resolution does not survive the migration cleanly.

2. **Loop-body calls in Finance Actions = Critical Pattern #7 territory.** `SendDueItemReminders` has TWO loops in the same file (lines 82, 152) — both need ≥2-row fixture coverage after migration.

3. **`NotificationTemplateController:160` (test-send button) is the cleanest M3 starting point.** Single call, already in Notification module, no cross-module concern. Migrate first to prove the outbox-emit pattern, then fan out to Finance Actions.

### Non-blocking findings

4. **`scheduleReminder` (7 callsites) calls a different service (`ScheduledNotificationService` / `NotificationService`).** Not in M3 scope. Flag for P4 if user wants the scheduled-reminder pipeline migrated too.

5. **`EmailMonitoringController` reads only.** Safe shadow — but M2 (re-point `/systems/email-history`) should consider whether `EmailMonitoringController` also needs to switch read sources.

## Decision: GO for M-batch with hard C1 dependency

| Downstream story | Status | Reason |
|---|---|---|
| **M3** (EmailService outbox emit) | **GO with C1 hard-blocked first** | Cross-module callers force Contract before refactor |
| **M4** (jobs migrate) | GO | 1 file (`SendBulkEmailJob`), uses `EmailService` via DI — refactor follows M3 |
| **M5** (deprecate `EmailController`) | GO | Thin façade rewrite, no surprises |
| **C1** (Contract creation) | GO + **promoted to Wave 2 critical path** | Was parallel-optional in epic-map; S2 makes it sequential gate |

### Epic-map-p3 amendment proposed

In `epic-map-p3.md` §"Critical-path & parallelism", change M3 row from:
```
S2 → M1 (query) → M2 (controller re-point)
        M3 (EmailService outbox emit) → M4 (jobs migrate)
```
to:
```
S2 → M1 (query) → M2 (controller re-point)
S2 → C1 (Contract) → M3 (EmailService outbox emit) → M4 (jobs migrate)
```

C1 stops being a parallel side-quest; it becomes a hard predecessor to M3.

## Unresolved Questions

- None for S2. Stakeholder questions on PII / retire date remain (deferred per pack feasibility table).
