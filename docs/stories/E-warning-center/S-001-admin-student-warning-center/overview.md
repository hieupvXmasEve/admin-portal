# Overview

## Current Behavior

Academic standing is finalized into `gpa_calculations.academic_standing` with the current 100-point rule where cumulative GPA below 50 is `warning`.

Admin users can inspect GPA and attendance data through separate report surfaces. Manual student notifications already exist through the Notification V2 pipeline, but there is no dedicated warning workbench that combines academic-standing warnings and attendance warnings with one-click student messaging.

Attendance completion rules already derive from the course offering syllabus template `min_attendance_threshold`. The current course statistics surface computes allowed absences from the minimum attendance percentage, but there is no configurable early-warning point, no warning-specific send log, and no staff-managed warning templates.

Student-facing APIs expose dashboard, GPA, attendance, and notification data. The student Nuxt portal lives in `FE/student-nuxt`. Some older student GPA presentation still describes standing using legacy labels, so the warning/normal language needs to be made consistent with the 100-point GPA rule.

## Target Behavior

Create a warning-focused admin experience with two operational panels:

- Academic standing warnings: students whose current cumulative GPA is below 50, with one-row send action.
- Attendance warnings: active-semester subjects, expandable sections, and students whose absence count crosses the configured early-warning or exceeded-limit thresholds, with one-row send action.

Student-facing surfaces should show warning notifications after staff send them and should present academic status as `Warning` below 50 and `Normal` at or above 50, using the same GPA and credit numbers shown to admin users.

Warning notifications must be delivered through both in-app/realtime and email channels. Duplicate sends are blocked for the same warning milestone; a later missed class that increases the absence count creates a new eligible milestone.

## Affected Users

- Academic/admin staff who monitor warning cases.
- Students receiving academic or attendance warning messages.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/design-guidelines.md`
- `app/Http/Resources/Api/V1/Student/NOTIFICATION-API.md`
- `FE/student-nuxt/docs/notification-api.md`

## Non-Goals

- Automated scheduled warning dispatch.
- Changing GPA calculation rules.
- Changing attendance source-of-truth calculations.
