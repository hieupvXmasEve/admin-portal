---
phase: 9
title: "Swinx Adapter Outbound Push"
status: pending
priority: P1
effort: "4d"
dependencies: [3]
---

# Phase 9: Swinx Adapter Outbound Push (this repo)

## Overview
Swinx module pushing rosters, lifecycle statuses, and room catalog to hub. Can start once phase 3 contract freezes. Owner module: **Academic** (new submodule `EgcHubSync`), per repo file-placement rules.

## Requirements
- Functional:
  - Auto-push enrollment when student is/becomes EGC-eligible (`program_enrollments.study_stage = 'intake_pre_uni_gc'` with egc levels set) — create/update/level change
  - Auto-push lifecycle transitions: suspend (overdue tuition per school's own policy trigger), defer, withdraw, reactivate — mapped to `PATCH /v1/enrollments/{code}`
  - Push room catalog (`PUT /v1/rooms`) from campus rooms marked EGC-usable
  - Backfill command: push all current EGC students for initial onboarding; safe to re-run
  - Config: hub base URL + API key in env; queued jobs with retry
- Non-functional: idempotent payload building; no push loops (inbound-materialized data must never re-trigger outbound push)

## Related Code Files (this repo — exact paths per placement rules)
- Create: `app/Modules/Academic/EgcHubSync/Support/EgcHubClient.php` (HTTP client, auth header, error mapping)
- Create: `app/Modules/Academic/EgcHubSync/Support/EgcRosterPayloadBuilder.php`
- Create: `app/Modules/Academic/EgcHubSync/Jobs/{PushEgcEnrollmentJob,PushEgcStatusJob,PushRoomCatalogJob}.php`
- Create: `app/Modules/Academic/EgcHubSync/Listeners/PushEgcEnrollmentOnChange.php` (observer on ProgramEnrollment egc fields + student status transitions via `StudentStatusTransitionPolicy` events)
- Create: `app/Console/Commands/Academic/BackfillEgcHubRosterCommand.php`
- Create: config keys in `config/services.php` (`egc_hub.base_url`, `egc_hub.api_key`)
- Modify: `app/Modules/Academic/Providers/AcademicServiceProvider.php` (register observers/listeners)
- Create: `tests/Feature/Academic/EgcHubSync/OutboundPushTest.php` (fake HTTP, assert payloads, idempotency, loop guard)
- Create: `tests/Feature/Architecture/EgcHubSyncModulePlacementArchTest.php` (placement arch test — repo pattern)

## Implementation Steps
1. Client + payload builder against frozen OpenAPI contract (phase 3).
2. Observers/listeners for enrollment + status transitions → queued push jobs.
3. Room catalog push job + admin-triggerable artisan command.
4. Backfill command with chunking + dry-run flag.
5. Loop guard: adapter-inbound writes (phase 10) set a context flag observers skip.

## Success Criteria
- [ ] New EGC enrollment appears on hub within one queue cycle
- [ ] Suspend in Swinx → hub flags student; reactivate reverses
- [ ] Backfill 2000 students idempotently (run twice, same state)
- [ ] Arch test: module files live in Academic module only

## Risk Assessment
- Event coverage gaps (some enrollment mutations bypass observers, e.g. mass import) → backfill command + nightly reconciliation (phase 7) catch drift; document known bypass paths.
