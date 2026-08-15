---
phase: 10
title: "Swinx Adapter Inbound Materialization"
status: pending
priority: P1
effort: "7d"
dependencies: [7, 9]
---

# Phase 10: Swinx Adapter Inbound Materialization (this repo)

## Overview
Receive hub webhooks + replay feed, materialize EGC classes/sessions/attendance/scores into Swinx standard tables so student portal, `CourseCompletionService` → `EgcBlock` → progression → finance run **unchanged**. Riskiest phase — it replaces the human Excel-retype step.

## Requirements
- Functional:
  - Webhook receiver endpoint verifying HMAC; enqueue-then-200 (no inline processing)
  - Materializers per event type:
    - `session.scheduled`/`session.updated` → CourseOffering (unit `unit_type='egc'` matched by level/block) + ClassSession rows; owning school sees room reference, others location text; changed sessions trigger existing Notification module to affected students
    - `attendance.recorded` → attendance rows per session sheet; `excused` maps to absent-equivalent status so existing `attendance_rate` math counts it; note preserved where schema allows
    - `scores.updated` → `AssessmentComponentDetailScore` via component mapping (Canvas assignment id ↔ AssessmentComponentDetail per offering)
    - `block.completed` → trigger existing `CourseCompletionService` path for the offering (NOT direct `EgcBlock` writes — reuse pipeline)
  - Replay catch-up command consuming `GET /v1/events?since={cursor}` with stored cursor; run on schedule as webhook safety net
  - Reconciliation compare command against `GET /v1/reconciliation`
- Non-functional: all writes via existing services/actions (no raw inserts); idempotent upserts keyed by hub ids stored in mapping tables; inbound writes flagged to suppress outbound observers (phase 9 loop guard) and school-lifecycle side effects that assume local ownership

## Related Code Files (this repo — exact paths per placement rules)
- Create: `app/Modules/Academic/EgcHubSync/Http/Api/EgcHubWebhookController.php`
- Create: `app/Modules/Academic/EgcHubSync/Http/Requests/EgcHubWebhookRequest.php` (HMAC middleware/validation)
- Create: `app/Modules/Academic/EgcHubSync/Support/Materializers/{SessionMaterializer,AttendanceMaterializer,ScoreMaterializer,BlockResultMaterializer}.php`
- Create: `app/Modules/Academic/EgcHubSync/Models/EgcHubMapping.php` + migration `database/migrations/*_create_egc_hub_mappings_table.php` (hub_id ↔ local_id per entity type, cursor storage)
- Create: `app/Modules/Academic/EgcHubSync/Jobs/ProcessEgcHubEventJob.php`
- Create: `app/Console/Commands/Academic/{ReplayEgcHubEventsCommand,ReconcileEgcHubCommand}.php`
- Modify: `app/Modules/Academic/routes/api.php` (webhook route registration)
- Modify: `app/Modules/Academic/Providers/AcademicServiceProvider.php`
- Create: `tests/Feature/Academic/EgcHubSync/{WebhookReceiverTest,SessionMaterializerTest,AttendanceMaterializerTest,ScoreMaterializerTest,BlockResultFlowTest,ReplayCommandTest}.php`

## Implementation Steps
1. Webhook endpoint + HMAC verify + ProcessEgcHubEventJob dispatch (FIFO queue).
2. EgcHubMapping table + cursor persistence.
3. SessionMaterializer first (offering/session creation via existing Academic services) — validate with student portal rendering.
4. AttendanceMaterializer with excused→absent mapping; confirm `attendance_rate` parity against fixture.
5. ScoreMaterializer with component mapping bootstrap (per offering, from Canvas assignment metadata in payload).
6. BlockResultMaterializer invoking `CourseCompletionService` path; assert `EgcBlock` + finance side effects equal today's flow (regression fixture from a real historical block).
7. Replay + reconciliation commands, scheduled.

## Success Criteria
- [ ] Replaying a full recorded block of events (fixture) produces `EgcBlock` rows + finance charges byte-equal to the legacy manual flow
- [ ] Duplicate webhook delivery → zero duplicate rows
- [ ] Session update triggers student notification via existing Notification module
- [ ] Student portal displays hub-originated schedule/attendance/grades with no portal changes
- [ ] 3-day outage recovered by replay command; reconciliation reports zero drift after

## Risk Assessment
- HIGHEST RISK: side effects firing on materialized writes (progression events, finance hooks, notification spam). Mitigation: explicit inventory of observers/hooks on touched tables before coding; context flag suppression; regression fixture comparing full downstream state.
- Component mapping ambiguity (Canvas assignment ↔ AssessmentComponentDetail) → require deterministic mapping payload from hub (assignment id + name + position); fail loud to dead-letter on unmapped.
