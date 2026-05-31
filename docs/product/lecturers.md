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

## Unresolved Questions

- Should the product rename this UI filter from `Program` to `Unit Type` to
  match the underlying academic model?
