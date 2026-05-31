# Design

## Domain Model

Use `GpaCalculation` as the academic warning source of truth for current cumulative GPA, credits attempted, credits earned, and `academic_standing`.

Use active-semester `CourseOffering` records grouped by unit and section for attendance warnings. Student absence status should reuse the existing course statistics logic: total absences, allowed absences, absences remaining, attendance percentage, and meets-attendance-requirement.

Attendance warning thresholds are derived from the course offering syllabus template `min_attendance_threshold` and the section's total scheduled class sessions:

- `max_absence_percent = 100 - min_attendance_threshold`
- `early_warning_absence_percent = max_absence_percent * warning_ratio`
- default `warning_ratio = 0.5`
- for `min_attendance_threshold = 80`, max absence is 20% and early warning starts when absences are greater than 10% of total sessions
- `early_warning_absence_count = floor(total_sessions * early_warning_absence_percent / 100)`
- `max_allowed_absence_count = floor(total_sessions * max_absence_percent / 100)`
- early warning is eligible when `absent_count > early_warning_absence_count` and `absent_count <= max_allowed_absence_count`
- exceeded-limit warning is eligible when `absent_count > max_allowed_absence_count`

Notification sending should go through Notification V2 domain event publication, not direct message table writes. Warning messages must request both `realtime` and `email` channels and should use warning-specific `type_key` values so email templates and student UI badges are explicit.

Duplicate suppression is milestone-based. A successful warning for the same student, course offering, warning type, absence count, and triggering class session cannot be sent again. A later absence that increases `absent_count` creates a new milestone and allows another warning.

## Application Flow

Admin warning page:

1. Load active campus and active semester context.
2. Query academic-standing warning rows for current finalized GPA records where cumulative GPA is below 50 or standing is `warning`.
3. Query active-semester course offerings grouped by unit, then by section.
4. Compute each section's early-warning and exceeded-limit absence counts from syllabus `min_attendance_threshold`.
5. Expand a subject to show sections; expand a section to show warning-relevant students.
6. Show staff the threshold math for each section, because sections can have different total session counts.
7. Send a single warning message from the target row.

Warning settings page:

1. Staff open warning settings from the Warning Center header.
2. Staff can configure default warning ratio, enabled channels, message templates, and exceeded-limit wording per campus.
3. Staff can preview academic, attendance early-warning, and attendance exceeded-limit messages using sample variables.
4. Settings are reused by send actions; row-level sends should not require staff to rewrite message text.

Student surfaces:

1. Student notification list in `FE/student-nuxt` shows sent warning messages under academic or attendance category.
2. Student dashboard GPA panel in `FE/student-nuxt/app/pages/(protected)/dashboard.vue` uses the same cumulative GPA, credits attempted, credits earned, and standing rule as admin.
3. Student attendance pages can deep-link from notification actions to the relevant attendance report or course section when the backend provides an `action_url`.

## Interface Contract

Proposed admin route placement:

- `GET /academic/warnings`
- `POST /academic/warnings/academic-standing/{student}/send`
- `POST /academic/warnings/attendance/{courseOffering}/{student}/send`
- `GET /academic/warnings/settings`
- `PUT /academic/warnings/settings`

The UI should avoid free-form per-row message editing in the first slice. Use staff-managed templates with generated values so staff cannot accidentally send inconsistent warning numbers.

Proposed warning notification type keys:

- `academic_standing_warning`
- `attendance_early_warning`
- `attendance_limit_exceeded`

The existing Notification V2 email template enum is closed, so adding these type keys requires enum cases, DB template provisioning, and variable allow-lists.

Academic warning row fields:

- number
- student name
- student code
- intake
- current cumulative GPA
- total credits earned
- total credits attempted
- last warning sent state
- send warning action

Attendance warning row fields:

- student name
- student code
- absence threshold: used / early-warning / allowed, for example `2 / 1 / 3`
- total sessions
- syllabus min attendance percent
- attendance percentage
- status: early warning, at limit, exceeded
- trigger class session/date
- last warning sent state
- send warning action

Section header fields:

- course code and name
- section code
- total sessions
- min attendance
- early-warning starts after N absences
- max allowed absences
- warning student count
- exceeded-limit student count

## Data Model

Warning settings require a persisted configuration surface similar to finance operations settings.

Candidate tables:

- `academic_warning_settings`: campus id, warning ratio, enabled channels, academic template, attendance early-warning template, attendance exceeded-limit template, enabled flags, updated by.
- `student_warning_logs`: warning type, student id, course offering id, class session id, GPA calculation id, absence count, threshold snapshot, payload snapshot, actor user id, notification event id, delivery status, sent at.

These tables are not sources of truth for GPA or attendance. They support staff configuration, audit, and duplicate suppression.

## UI / Platform Impact

The admin page should be a dense operations screen under Reports & Analytics or Academic, not a marketing-style dashboard.

The student-facing impact is split between backend API contracts and `FE/student-nuxt`. The backend must return explicit `standing`, `standing_label`, GPA, credit values, notification category, warning type key, and optional `action_url`. The Nuxt portal should render warning badges in the dashboard, notification list, and notification detail views.

## Observability

Each send action should log actor, campus, student, warning type, source metrics, duplicate key, and notification event id. Notification delivery remains observable through existing Notification Ops pages.

## Alternatives Considered

1. Extend the generic Send Notification page only. Rejected because staff need warning-specific metrics and generated message text.
2. Add warning buttons inside many existing GPA/attendance pages. Rejected for the first slice because it spreads one workflow across unrelated screens.
3. Dedicated Warning Center page. Preferred because it matches the operational task: find warning cases and send warning messages.
4. Hardcode "10%" as a global attendance warning threshold. Rejected because the product rule depends on each class's syllabus minimum attendance and total class session count.
