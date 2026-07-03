# Record attendance directly from cockpit sessions view

Status: ready-for-agent

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

Let staff record attendance for a session directly from the cockpit's sessions view, instead of switching to the standalone staff Attendance pages (`resources/js/pages/attendance/{Index,Create,Edit}.vue`, `AttendanceController`). Reuse the existing attendance-recording logic/validation rather than reimplementing it — expose it through a route reachable from the cockpit.

The lecturer attendance API (`routes/api/v1/lecturer.php`, `attendance` group, `Api/V1/Lecturer/AttendanceController`) is a fully separate surface and must not be modified by this work.

After recording attendance for a session, the cockpit's `operational_state` (readiness blockers, available actions) and the per-session attendance status (previous slice) both refresh via the existing Inertia partial-reload mechanism — this is the same "refresh after every mutating action" contract established for Finalize/Recalculate.

## Acceptance criteria

- [ ] Staff can record attendance for a session from the cockpit sessions view without navigating to the standalone Attendance pages.
- [ ] The recording path reuses existing attendance validation/business logic (no parallel implementation).
- [ ] After recording, `operational_state` and per-session attendance status both reflect the change via a partial reload — no full page reload required.
- [ ] The lecturer attendance API routes and controller are unchanged; a regression test (or existing test re-run) confirms lecturer attendance marking still works as before.
- [ ] Pest HTTP feature test covers: recording attendance from the cockpit endpoint, and that recording clears the corresponding readiness blocker when all sessions become recorded.

## Blocked by

- Per-session attendance status visible in cockpit sessions view
