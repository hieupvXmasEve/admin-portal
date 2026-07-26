---
title: "Phase 2 Work Package Ledger"
status: completed
last_verified: 2026-07-26
---

# Phase 2 Work Package Ledger

## Frozen disposition

The Phase 1 inventory declares 23 `legacy_page_directories` findings. All 138
tracked files beneath those roots are live survivors: each root has an active
Inertia render, page import, or both. No file is replaced or deleted by this
work package. The sorted source-path manifest hashes to
`2ab940e4e8a1993c6bd5c4b114b70b653738dd068d0d70ab04b9b1848e440335`.

| Work package | Source root | Destination root | Files | Disposition |
| --- | --- | --- | ---: | --- |
| `phase-02:attendance:legacy_page_directories` | `attendance` | `Attendance` | 1 | move |
| `phase-02:auth:legacy_page_directories` | `auth` | `Auth` | 6 | move |
| `phase-02:class-schedule:legacy_page_directories` | `class-schedule` | `ClassSchedule` | 1 | move |
| `phase-02:class-sessions:legacy_page_directories` | `class-sessions` | `ClassSessions` | 7 | move |
| `phase-02:clubs:legacy_page_directories` | `clubs` | `Clubs` | 4 | move |
| `phase-02:course-offerings:legacy_page_directories` | `course-offerings` | `CourseOfferings` | 14 | move |
| `phase-02:course-registrations:legacy_page_directories` | `course-registrations` | `CourseRegistrations` | 4 | move |
| `phase-02:curriculum-units:legacy_page_directories` | `curriculum-units` | `CurriculumUnits` | 1 | move |
| `phase-02:curriculum-versions:legacy_page_directories` | `curriculum-versions` | `CurriculumVersions` | 8 | move |
| `phase-02:dashboard:legacy_page_directories` | `dashboard` | `Dashboard` | 1 | move |
| `phase-02:lectures:legacy_page_directories` | `lectures` | `Lectures` | 8 | move |
| `phase-02:programs:legacy_page_directories` | `programs` | `Programs` | 2 | move |
| `phase-02:roles:legacy_page_directories` | `roles` | `Roles` | 3 | move |
| `phase-02:room-bookings:legacy_page_directories` | `room-bookings` | `RoomBookings` | 9 | move |
| `phase-02:rooms:legacy_page_directories` | `rooms` | `Rooms` | 5 | move |
| `phase-02:semesters:legacy_page_directories` | `semesters` | `Semesters` | 2 | move |
| `phase-02:settings:legacy_page_directories` | `settings` | `Settings` | 3 | move |
| `phase-02:specializations:legacy_page_directories` | `specializations` | `Specializations` | 4 | move |
| `phase-02:student-applications:legacy_page_directories` | `student-applications` | `StudentApplications` | 5 | move |
| `phase-02:students:legacy_page_directories` | `students` | `Students` | 30 | move |
| `phase-02:syllabus:legacy_page_directories` | `syllabus` | `Syllabus` | 14 | move |
| `phase-02:systems:legacy_page_directories` | `systems` | `Systems` | 1 | move |
| `phase-02:units:legacy_page_directories` | `units` | `Units` | 5 | move |

Every relative path is preserved, except these six case-only Vue filename
moves, which use temporary paths on case-insensitive filesystems:

- `ClassSchedule/index.vue` → `ClassSchedule/Index.vue`
- `StudentApplications/create.vue` → `StudentApplications/Create.vue`
- `StudentApplications/documents.vue` → `StudentApplications/Documents.vue`
- `StudentApplications/edit.vue` → `StudentApplications/Edit.vue`
- `StudentApplications/index.vue` → `StudentApplications/Index.vue`
- `StudentApplications/show.vue` → `StudentApplications/Show.vue`

## Contract and merge gate

- Portal impact: none; neither student nor lecturer API contract changes.
- Keep the `resources/js/pages` root spelling unchanged. Update only direct
  render names, page imports, resolver assertions, and tests.
- Preserve all named routes, props, page-local layouts, authorization, and
  deferred/lazy behavior.
- The auth resolver exception changes from `auth/Login` to `Auth/Login` only;
  `SelectCampus` remains untouched.
- Merge only when the inventory reaches `legacy_page_directories=0`, all page
  component names resolve under case-sensitive paths, and targeted tests plus
  frontend lint, format, typecheck, and production build pass.
