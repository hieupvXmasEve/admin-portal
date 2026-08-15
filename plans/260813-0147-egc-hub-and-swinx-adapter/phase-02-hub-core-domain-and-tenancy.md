---
phase: 2
title: "Hub Core Domain"
status: pending
priority: P1
effort: "5d"
dependencies: [1]
---

# Phase 2: Hub Core Domain

## Overview
Domain model for EGC operations: students (shadow records), enrollments with lifecycle status, blocks/levels calendar, classes, sessions, rooms.

## Requirements
- Functional: entities below with invariants; enrollment status machine `active|suspended|deferred|withdrawn` + reason; cross-school duplicate `student_code` detection producing a warning record
- Non-functional: no UI to create/edit student identity fields (API-only writes, phase 3); every grade/attendance/placement-affecting mutation writes an audit log row

## Architecture
```
School 1─* Student (student_code unique per school; global dup → warning)
Student 1─* Enrollment (level, status, reason, source school, starting/current/total levels)
Semester 1─* Block (number, level, start/end)  ← shared academic calendar (all schools same)
Block 1─* EgcClass (level, capacity, room_id?, lecturer_id)
EgcClass 1─* ClassSession (date, start/end, room_id, lecturer_id override for substitutes, status: scheduled|cancelled|makeup)
Room *─1 School (pushed by owning school, reference data)
AuditLog (actor, action, subject, before/after JSON)
```
Statuses as varchar + backend allow-list (no DB enums — repo convention).

## Related Code Files (egc-hub/)
- Create: `app/Models/{Student,Enrollment,Semester,Block,EgcClass,ClassSession,Room,AuditLog,DuplicateCodeWarning}.php`
- Create: migrations for all above; unique index `(school_id, student_code)`; index `student_code` global for dup scan
- Create: `app/Services/EnrollmentStatusService.php` (transition rules + side effects hooks)
- Create: `app/Services/AuditLogger.php`
- Create: `app/Http/Controllers/Admin/{SemesterController,BlockController}.php` + Vue pages (calendar setup)
- Create: Pest tests per model invariant + status machine

## Implementation Steps
1. Migrations + models + factories.
2. `EnrollmentStatusService`: allowed transitions, reason required for suspend/defer/withdraw; suspend flags render in later phases.
3. Duplicate-code scan on student insert → `DuplicateCodeWarning` row (never blocks).
4. Block/semester admin CRUD (EGC admin defines calendar; all schools share it).
5. AuditLogger wired via model observers on Enrollment, EgcClass, ClassSession.

## Success Criteria
- [ ] Status machine rejects invalid transitions; suspend requires reason
- [ ] Same code in 2 schools → warning row + visible in admin list; same code same school → 422
- [ ] Audit log captures before/after for placement-affecting changes

## Risk Assessment
- Over-modeling: keep Room as flat reference (school_id, campus_name, room_name) — no booking engine v1.
