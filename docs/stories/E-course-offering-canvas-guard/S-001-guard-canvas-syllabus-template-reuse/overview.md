# Overview

## Current Behavior

Course offering create and edit forms list active syllabus templates without
distinguishing reusable templates from templates reserved for Canvas LMS sync.
The backend only checks that `syllabus_template_id` exists.

Canvas assignment sync clones a syllabus template for one course offering,
renames the clone with `Canvas Sync`, assigns the clone to that offering, and
marks the offering as Canvas-synced. Reusing that clone for another offering can
cause the two offerings to share Canvas-specific assessment components and can
lead to incorrect grade sync.

The supplied URLs include `/course-offerings/180/edit` and
`/course-offerings/180/add`. The repository route for creating a course offering
is `/course-offerings/create`; `/course-offerings/{courseOffering}/class-sessions/add`
is a class-session modal and does not choose a syllabus template.

## Target Behavior

- Course offering create does not list active syllabus templates whose title
  contains `Canvas`, which are attached to a Canvas-synced course offering, or
  which are attached to a course offering already mapped to Canvas.
- Course offering edit applies the same filter.
- A Canvas-synced offering may keep its own currently assigned Canvas template
  while editing unrelated fields.
- Store and update requests reject a protected Canvas template even if a client
  submits its id directly. Mapping reserves the template immediately; assignment
  sync does not need to run first.
- Duplicate offering rejects a source offering with a protected Canvas template.
- Existing non-Canvas syllabus templates remain reusable.

## Affected Users

- Admin and academic staff creating or editing course offerings.
- Staff operating Canvas LMS assignment and grade sync.

## Affected Product Docs

- `docs/features/canvas/CANVAS_SYLLABUS_SYNC_SPEC.md`
- `docs/features/canvas/CANVAS_INTEGRATION.md`
- `docs/TEST_MATRIX.md`

## Non-Goals

- Change Canvas mapping, assignment sync, or grade sync behavior.
- Add a migration or retroactively rewrite existing course offerings.
- Change class-session add flows.
