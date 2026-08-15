---
phase: 4
title: "Hub Placement and Schedule Operations"
status: pending
priority: P1
effort: "7d"
dependencies: [3]
---

# Phase 4: Hub Placement and Schedule Operations

## Overview
EGC-admin UI: create classes per block/level, place students, manage sessions — the screens that replace Excel.

## Requirements
- Functional:
  - Class CRUD: level, capacity, room (from synced catalog), default lecturer, weekly schedule pattern → generates sessions
  - Placement board: filter unplaced students by level/school/campus/retake; assign to class; capacity warning on overflow (warn, not block); mid-block transfer with attendance history following student
  - Session ops: reschedule (time/room), cancel, create makeup session, assign substitute lecturer per session
  - Hub-internal room conflict check (same room, overlapping time → warn)
  - Suspended students visibly flagged in class lists; removal = manual admin action
- Non-functional: placement/transfer/session mutations audit-logged + recorded as events (`session.scheduled`, `session.updated`, `placement.changed`)

## Architecture
Placement writes `class_memberships` (class_id, student_id, joined_at, left_at, transfer link) — membership intervals, never hard delete, so attendance history survives transfers. Session generation from weekly pattern is a plain service, editable results (no regeneration after manual edits — additive only).

## Related Code Files (egc-hub/)
- Create: `app/Models/ClassMembership.php` + migration
- Create: `app/Services/{SessionGenerator,PlacementService,RoomConflictChecker}.php`
- Create: `app/Http/Controllers/Admin/{EgcClassController,PlacementController,ClassSessionController}.php`
- Create: `resources/js/pages/Admin/Classes/*.vue`, `Admin/Placement/Board.vue`, `Admin/Sessions/*.vue`
- Create: Pest tests: capacity warn, transfer preserves history, conflict checker, cancel/makeup flows

## Implementation Steps
1. ClassMembership + PlacementService (assign/transfer/remove with interval semantics).
2. SessionGenerator from weekly pattern within block dates.
3. Placement board UI: two-pane (unplaced pool ↔ class roster), filters, bulk assign.
4. Session management UI: cancel with reason, makeup creation, substitute assignment.
5. RoomConflictChecker on session save (hub-internal only, v1).
6. Emit events for every mutation consumers care about.

## Success Criteria
- [ ] Place 200 students across 10 classes in one sitting without errors; overflow warns
- [ ] Transfer mid-block: old class attendance intact, rate computed across both memberships
- [ ] Cancel + makeup + substitute all emit `session.updated`/`session.scheduled` events
- [ ] Two classes same room same slot → warning shown

## Risk Assessment
- UX is make-or-break (operators leave Excel only if faster) → pilot with real operator early, iterate board UI.
