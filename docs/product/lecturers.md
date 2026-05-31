# Lecturer Directory

Last updated: 2026-05-31
Owner: Academic Operations
Status: Active product contract

## Directory Filtering

The web lecturer directory at `/lectures` is scoped to the current campus.

By default, when an active semester exists, the list shows lecturers assigned to
course offerings in that active semester. Staff can switch the semester filter
to `All Semesters` to review historical lecturers.

The Program filter is backed by `units.unit_type` from assigned course
offerings. This includes EGC (`egc`) lecturers when EGC course offerings exist
for the current campus.

## Teaching Hours Summary

The `/lectures/teaching-hours` report is scoped to the current campus. When
staff open the page without filters, the Semester filter defaults to the active
semester and the From date / To date filters stay blank.

The summary table shows one row per lecturer with lecturer name, email account,
employee ID, employment type, taught course offerings, teaching subject codes,
and total hours. Date filters are optional; when staff choose a date range, the
course list and total hours are calculated from class sessions inside that range.

Staff with export permission can download the current filtered teaching-hours
view as an Excel workbook.

## Lecturer GPA Report

The `/lectures/lecturers-GPA` report is scoped to the current campus. When staff
open the page without filters, the Semester filter defaults to the active
semester.

The summary table shows one row per lecturer with No, lecturer name, email
account, employee ID, employment type, taught course offerings, and GPA. The
email account is the local part before `@`, such as `trangnk16`.

GPA is calculated per lecturer by first calculating each course offering's
Lecturer Evaluation average, then averaging those class-level scores for the
lecturer in the selected semester. For example, a lecturer teaching AU001.1,
AU001.2, AU002.1, and AU002.2 gets one GPA from those four class-level Lecturer
Evaluation scores.

Staff can export the current filtered Lecturer GPA view as an Excel workbook.
The Survey Results aggregate page links to this report for the survey target's
semester.

## Unresolved Questions

- Should the product rename this UI filter from `Program` to `Unit Type` to
  match the underlying academic model?
- Should the `Lecturer Evaluation` survey section marker become a versioned
  template contract instead of title matching?
