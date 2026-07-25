---
id: ADR-0046
title: "Derive warning thresholds and deduplicate by milestone"
status: accepted
date: 2026-05-31
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Derive warning thresholds and deduplicate by milestone

## Context

Attendance warnings cannot use one absence count because each section has its
own scheduled sessions and each syllabus may define a different minimum
attendance percentage. Staff need the same warning semantics in the admin app,
student portal, realtime delivery, email, and audit logs.

## Decision

Derive thresholds from the syllabus minimum and section total:

- `max_absence_percent = 100 - min_attendance_threshold`
- `early_warning_absence_percent = max_absence_percent * warning_ratio`
- the default `warning_ratio` is `0.5`
- percentage thresholds become counts with
  `floor(total_sessions * percent / 100)`
- a warning triggers only when `absent_count` is greater than the derived count

Warning notifications request both `realtime` and `email` channels and use
explicit type keys: `academic_standing_warning`,
`attendance_early_warning`, and `attendance_limit_exceeded`.

Deduplication is milestone-based: the same student, course offering, warning
type, absence count, and triggering class session can succeed once. A later
absence count is a new eligible milestone. Staff may configure message
templates and exceeded-limit wording, but not the GPA or attendance source
calculations.

## Consequences

Thresholds remain explainable and syllabus-aligned while repeated sends are
suppressed without hiding later escalation. Notification type provisioning and
the student portal contract must change together when warning metadata changes.
