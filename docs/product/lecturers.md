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

## Unresolved Questions

- Should the product rename this UI filter from `Program` to `Unit Type` to
  match the underlying academic model?
