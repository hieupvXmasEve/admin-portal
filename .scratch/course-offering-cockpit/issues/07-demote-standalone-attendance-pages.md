# Demote standalone Attendance pages to cross-offering reporting only

Status: ready-for-agent

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

Now that attendance recording lives in the cockpit, remove the per-offering recording entry points from the standalone staff Attendance pages (`resources/js/pages/attendance/{Index,Create,Edit}.vue`, `AttendanceController`), leaving them as cross-offering reporting surfaces only. Where it makes sense, deep-link from the Attendance report views back to the relevant offering's cockpit for action, consistent with the "reports link to operations, not the reverse" boundary from ADR 0013.

The lecturer attendance API remains untouched — this issue only affects the staff-facing web pages.

## Acceptance criteria

- [ ] The standalone Attendance pages no longer expose a way to create/edit attendance records for a session (that action only exists in the cockpit now).
- [ ] Attendance Index (or equivalent) remains available as a cross-offering reporting view.
- [ ] Where a staff member would previously have jumped from Attendance to record something, the reporting view now deep-links to the relevant course offering's cockpit instead.
- [ ] Any routes/controller actions that only existed to support per-offering recording on the standalone pages are removed or redirected, not left as dead/duplicate code paths.
- [ ] Pest HTTP feature tests confirm the removed recording routes are gone (404/removed) or redirect appropriately, and that the reporting views still function.

## Blocked by

- Record attendance directly from cockpit sessions view
