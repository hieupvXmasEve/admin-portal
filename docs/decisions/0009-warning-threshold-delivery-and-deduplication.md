# 0009 Warning Threshold Delivery And Deduplication

Date: 2026-05-31

## Status

Accepted

## Context

The Warning Center must surface academic-standing warnings and attendance warnings for admin staff, then notify students through the student-facing portal. Attendance warning eligibility cannot use a fixed absence count because each class can have a different number of scheduled sessions and each syllabus can define a different minimum attendance percentage.

The student portal is implemented in `FE/student-nuxt`, while admin workflows are in the Laravel/Inertia app. Notification V2 already supports realtime and email delivery, but the existing manual notification action currently defaults to realtime-only payloads.

## Decision

Attendance warnings are derived from the course offering syllabus minimum attendance percentage and section total sessions:

- `max_absence_percent = 100 - min_attendance_threshold`
- `early_warning_absence_percent = max_absence_percent * warning_ratio`
- default `warning_ratio = 0.5`
- for `min_attendance_threshold = 80`, early warning starts when absences are greater than 10% of total sessions and exceeded-limit starts when absences are greater than 20% of total sessions
- percent thresholds convert to counts with `floor(total_sessions * percent / 100)`; a warning triggers only when `absent_count` is greater than that count

Warning notifications must request both `realtime` and `email` channels. Warning-specific type keys should be introduced so templates, badges, and logs are explicit:

- `academic_standing_warning`
- `attendance_early_warning`
- `attendance_limit_exceeded`

Duplicate suppression is milestone-based. A warning can be sent successfully only once for the same student, course offering, warning type, absence count, and triggering class session. A later absence that increases the absence count creates a new eligible milestone.

Staff-configurable warning settings are required for template text and exceeded-limit wording. The settings are an operational configuration surface, similar in spirit to finance operation settings, and do not change GPA or attendance source-of-truth calculations.

## Alternatives Considered

1. Fixed global attendance warning at 10%. Rejected because it does not respect syllabus-specific minimum attendance rules.
2. Send realtime only. Rejected because the product requires both in-app and email delivery.
3. Block all repeat warnings per student/course. Rejected because staff need a new warning when a later missed session increases the absence count.
4. Let staff edit message text on every row send. Rejected for the first slice because generated template values reduce inconsistent warning numbers.

## Consequences

Positive:

- Warning thresholds remain consistent with syllabus attendance rules.
- Staff can explain why a student appears in the warning list.
- Duplicate sends are prevented without hiding later escalation events.
- Student portal receives consistent notification metadata across in-app and email paths.

Tradeoffs:

- Implementation requires a warning settings model and warning send log.
- Notification V2 template enum/provisioning must be extended for warning-specific email type keys.
- `FE/student-nuxt` must be updated alongside the Laravel/Inertia admin app.

## Follow-Up

- Confirm whether `warning_ratio = 0.5` should be campus-configurable or globally fixed.
- Confirm exact exceeded-limit wording and whether the student should see "exam blocked" text immediately after the limit is crossed.
