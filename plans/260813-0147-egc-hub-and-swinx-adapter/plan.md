---
title: "EGC Hub and Swinx Adapter"
description: "Standalone EGC operations hub (new Laravel repo) + Swinx adapter module: cross-school class placement, attendance, Canvas orchestration, bidirectional sync with 10+ school portals"
status: pending
priority: P1
effort: "8-12w"
tags: [egc, hub, sync, canvas, new-repo, adapter]
created: 2026-08-13
---

# EGC Hub and Swinx Adapter

## Overview

Build **EGC Hub** — standalone app (new repo `egc-hub`, Laravel 12 + Vue 3/Inertia) — as system-of-record for EGC operations: cross-school class placement, schedule, attendance, teacher assignment, Canvas orchestration. Schools (10+ Swinx deployments + third-party systems) integrate via public API `/v1` + webhooks. Swinx gets an adapter module (this repo) that pushes rosters up and materializes classes/attendance/scores down into standard tables — existing pipeline (`CourseCompletionService` → `EgcBlock` → progression → finance) unchanged.

Full business spec + decisions log: [brainstorm report](../reports/brainstorm-260811-0918-egc-hub-architecture.md). Read it before any phase.

## Key Decisions (binding)

| Area | Decision |
|---|---|
| Shape | Separate thin app; NOT another Swinx deployment. Grading engine stays in schools; Canvas is grade-entry |
| Auth | Sanctum static API key, 1 key/school, 2 active for rotation, hashed, `last_used_at` |
| Identity | `(school, student_code)` key; students created ONLY via inbound API (portal = master, no login on hub); cross-school dup code → warning not block |
| Lifecycle | `active/suspended/deferred/withdrawn` + reason via API; suspend = flag + block attendance + Canvas deactivate, keep history; no money data on hub |
| Attendance | `present/absent/late/excused`; excused COUNTS as absent in rate, note only |
| Finalize | `finalize-block` permission gates `block.completed` emission |
| Sync | Queue-based idempotent both ways + manual re-sync; event log + `GET /v1/events?since=`; nightly reconciliation |
| Rooms | Schools push room catalog; hub assigns, checks own conflicts; owning school gets room ref back |
| Notifications | Hub emits `session.updated`; school portal notifies students. Hub never contacts students |
| Teaching hours | Hub tracks + audit export only; no payroll push to schools |
| Stack | PHP 8.3+/Laravel 12, MySQL 8, Redis+Horizon, Vue 3+Inertia, Pest, Docker dev.sh pattern |

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | EGC staff run placement/attendance on hub — no Excel | P1 |
| 2 | Data flows back into each school's standard tables; downstream pipeline untouched | P1 |
| 3 | Single Canvas orchestration point (provision + grade pull) | P1 |
| 4 | Third parties integrate from API docs alone (sandbox + docs) | P2 |
| 5 | Silent data loss impossible: replay + reconciliation + monitor | P1 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Hub Bootstrap and Tenancy](./phase-01-start.md) | Pending |
| 2 | [Hub Core Domain and Tenancy](./phase-02-hub-core-domain-and-tenancy.md) | Pending |
| 3 | [Hub Inbound API v1 and Event Log](./phase-03-hub-inbound-api-v1-and-event-log.md) | Pending |
| 4 | [Hub Placement and Schedule Operations](./phase-04-hub-placement-and-schedule-operations.md) | Pending |
| 5 | [Hub Attendance and Lecturer Portal](./phase-05-hub-attendance-and-lecturer-portal.md) | Pending |
| 6 | [Hub Canvas Orchestration](./phase-06-hub-canvas-orchestration.md) | Pending |
| 7 | [Hub Outbound Webhooks, Finalize, Reconciliation](./phase-07-hub-outbound-webhooks-finalize-and-reconciliation.md) | Pending |
| 8 | [Hub Reports, Exports, Sandbox, API Docs](./phase-08-hub-reports-exports-sandbox-and-api-docs.md) | Pending |
| 9 | [Swinx Adapter Outbound Push](./phase-09-swinx-adapter-outbound-push.md) | Pending |
| 10 | [Swinx Adapter Inbound Materialization](./phase-10-swinx-adapter-inbound-materialization.md) | Pending |
| 11 | [Cutover, Backfill, Pilot Rollout](./phase-11-cutover-backfill-and-pilot-rollout.md) | Pending |

Dependency graph: 1→2→3→4→5; 4→6; {3,6}→7; 7→8; 3→9; 7→10; {5,8,9,10}→11. Workstream B (9-10, this repo) can start as soon as phase 3 contract freezes.

## Success Criteria

- [ ] EGC admin places 2000+ students into classes on hub UI; capacity warnings work
- [ ] Lecturer marks attendance (4 statuses) on own classes only; teaching hours accrue
- [ ] Canvas courses/sections/enrollments (students + teachers) provisioned from hub; grades pulled bulk without rate-limit bans
- [ ] Finalize block (permission-gated) → `block.completed` → Swinx school computes `EgcBlock`/finance identical to today's manual flow
- [ ] Swinx student portal shows EGC schedule/attendance/grades with ZERO portal code changes
- [ ] Kill a school's adapter for 3 days → replay recovers 100% of events; nightly reconciliation shows zero drift
- [ ] Third-party sandbox integration completes using docs only
- [ ] Suspend via API (unpaid tuition) blocks attendance + deactivates Canvas, history intact; reactivate restores

## Open Questions

- PII cross-school data-sharing agreement (legal, outside code — user handles in parallel).

<!-- slug: egc-hub-and-swinx-adapter -->
