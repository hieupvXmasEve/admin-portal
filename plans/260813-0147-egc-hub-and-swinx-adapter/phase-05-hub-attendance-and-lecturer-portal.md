---
phase: 5
title: "Hub Attendance and Lecturer Portal"
status: pending
priority: P1
effort: "5d"
dependencies: [4]
---

# Phase 5: Hub Attendance and Lecturer Portal

## Overview
Lecturer-facing screens (own classes only) + attendance capture with 4 statuses + teaching-hours accrual.

## Requirements
- Functional:
  - Lecturer sees own schedule + class rosters; admin sees all
  - Attendance per session: `present | absent | late | excused` (+ optional note). Excused = absent for rate purposes; note surfaces for attitude assessment
  - Suspended students: row visible but attendance input disabled
  - Teaching hours: accrued per delivered session to the session's actual lecturer (substitute gets the hours); admin audit export (xlsx) per period/lecturer
  - Admin can correct attendance after the fact (audit-logged)
- Non-functional: attendance mutations emit `attendance.recorded` events; mobile-friendly marking UI (lecturers use phones in class)

## Architecture
`attendance_records` (session_id, student_id, status, note, marked_by, marked_at) unique per (session, student), upsert semantics. `teaching_hour_entries` derived on session completion — recomputable, not hand-edited.

## Related Code Files (egc-hub/)
- Create: `app/Models/{AttendanceRecord,TeachingHourEntry}.php` + migrations
- Create: `app/Services/{AttendanceService,TeachingHoursCalculator}.php`
- Create: `app/Http/Controllers/Lecturer/{MyScheduleController,AttendanceController}.php`, `Admin/TeachingHoursController.php`
- Create: `app/Exports/TeachingHoursExport.php` (maatwebsite/excel)
- Create: `resources/js/pages/Lecturer/{Schedule,Attendance}.vue`, `Admin/TeachingHours/Index.vue`
- Create: Pest tests: lecturer scoping (cannot mark other class), excused-counts-as-absent in rate math, suspended blocked, substitute hours attribution

## Implementation Steps
1. Attendance model + service (upsert, status allow-list, suspend guard).
2. Lecturer portal: today's sessions → tap-to-mark roster grid.
3. Event emission per save (batch per session, not per student — payload = full session sheet).
4. TeachingHoursCalculator + export.
5. Admin correction screen with audit trail.

## Success Criteria
- [ ] Lecturer marks a 30-student session on mobile in under 2 minutes
- [ ] Rate math: excused counted as absent; note visible in roster views
- [ ] Substitute lecturer's hours land on substitute, not default lecturer
- [ ] `attendance.recorded` event carries full session sheet, idempotent on re-save

## Risk Assessment
- Per-student event spam → batch per session sheet (decided above).
