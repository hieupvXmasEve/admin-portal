# Short Report: Student Lifecycle Yearly Analysis Page Plan

Date: 2026-02-25
Plan: `/Users/hunt2412/hieupvdev/project/swinx/plans/20260225-student-lifecycle-yearly-analysis-page/plan.md`

## What was planned
- New yearly analysis page for student lifecycle metrics.
- New route under Academic module web routes (`app/Modules/Academic/routes/web.php`).
- New sidebar menu entry under `Reports & Analytics`.
- Route helper update in `resources/js/utils/routes.ts`.

## Contract locked into plan
- Intake year bucket from `students.intake_semester_id -> semesters.start_date`.
- `NE = students.user_id IS NOT NULL`.
- Rates divide by `NE`.
- `Graduated = students.status='graduated'`.
- `DO-Transfer = students.status='dropout_transfer'`.
- Permission for page/menu/route: `view_student_action`.

## Unresolved questions
None.
