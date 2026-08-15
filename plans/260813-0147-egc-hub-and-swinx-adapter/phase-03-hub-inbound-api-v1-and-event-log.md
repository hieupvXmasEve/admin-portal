---
phase: 3
title: "Hub Inbound API v1 and Event Log"
status: pending
priority: P1
effort: "5d"
dependencies: [2]
---

# Phase 3: Hub Inbound API v1 and Event Log

## Overview
Public inbound API consumed by all school systems (Swinx + third-party), plus the append-only event log that powers webhooks (phase 7) and replay. **This phase freezes the v1 contract** — workstream B (phase 9) starts after it.

## Requirements
- Functional endpoints (all school-scoped by API key middleware from phase 1):
  - `POST /v1/enrollments` — upsert student + enrollment by `(school, student_code, level)`; carries name, campus, starting/current/total levels, pending retake blocks
  - `PATCH /v1/enrollments/{student_code}` — lifecycle status + reason
  - `PUT /v1/rooms` — replace/upsert room catalog for the school
  - `GET /v1/events?since={cursor}` — school-scoped event feed for replay
  - `GET /v1/schedules|attendance|scores|block-results` — pull surfaces (read models; data populated by later phases)
- Non-functional: idempotent (same payload twice = same state, no dup rows); rate limit per key; consistent error envelope; every accepted mutation appends an `Event` row
- Domain-neutral payloads — never leak Swinx table/column names

## Architecture
Append-only `events` table (id sequence = cursor, school_id, type, payload JSON, created_at). Inbound writes go through domain services (phase 2), then `EventRecorder`. Suspend transition immediately blocks attendance marking + queues Canvas deactivate (executed in phase 6).

## Related Code Files (egc-hub/)
- Create: `routes/api_v1.php`; `app/Http/Controllers/Api/V1/{EnrollmentController,RoomController,EventController,ScheduleController,AttendanceController,ScoreController,BlockResultController}.php`
- Create: `app/Http/Requests/Api/V1/*.php` (strict validation, allow-lists for statuses)
- Create: `app/Models/Event.php` + migration; `app/Services/EventRecorder.php`
- Create: `app/Http/Middleware/ApiRateLimit.php` (per-key buckets)
- Create: OpenAPI spec `docs/openapi-v1.yaml` — written FIRST, controllers conform to it
- Create: Pest feature tests: idempotency (double-post), scoping (school A cannot read B), rate limit, dup-code warning surfaced in response `warnings[]`

## Implementation Steps
1. Write `docs/openapi-v1.yaml` (contract-first; review with user before coding).
2. Enrollment upsert + status endpoints on top of phase 2 services.
3. Rooms upsert; events feed with cursor pagination; pull surfaces returning empty-but-valid shapes until phases 4-7 populate them.
4. Rate limiting + error envelope `{error: {code, message, details}}`.
5. Contract tests asserting responses match OpenAPI schema (spectator or pest plugin).

## Success Criteria
- [ ] Double-POST same enrollment → 1 row, 200 both times
- [ ] School A key on school B data → 404/403, never leaks existence
- [ ] Every mutation appends exactly 1 event; `GET /v1/events?since=` pages correctly
- [ ] OpenAPI spec validates against real responses in CI

## Risk Assessment
- Contract churn after third parties integrate → mitigation: contract-first + user review gate before phase 9; additive-only changes after freeze.
