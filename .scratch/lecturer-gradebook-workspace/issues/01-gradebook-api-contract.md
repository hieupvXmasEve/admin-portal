# 01 - Gradebook API Contract

Status: done
Portal impact: lecturer

## Goal

Create a lecturer Gradebook API that represents the Gradebook workspace directly instead of reusing report-shaped assessment endpoints.

## Requirements

- Add `GET /api/v1/lecturer/courses/{courseOffering}/gradebook`.
- Add `POST /api/v1/lecturer/courses/{courseOffering}/gradebook/scores`.
- Use a dedicated lecturer `GradebookController`.
- Keep controller orchestration thin: auth, validation, action/query, response.
- Create query/action under `app/Modules/Academic`.
- Read payload is matrix-first:
  - `course_offering`
  - `summary`
  - `items`
  - `students`
- `items` must include `detail_id`, `component_id`, component code/name/type, detail name, weight, `max_points`, graded/pending/missing counts, average percentage.
- `students[].cells[]` must include `detail_id`, `score_id`, `points_earned`, `percentage_score`, status, editable flag.
- Save payload accepts only add/update:
  - `{ scores: [{ detail_id, student_id, points_earned }] }`
- Save is all-or-nothing.
- Validate detail belongs to the offering's current `syllabus_template_id`.
- Validate student is enrolled in the course.
- Validate `points_earned` is between 0 and the detail's `max_points`.
- Save sets `status=graded`, reuses existing score persistence behavior where practical, and aggregates once after commit.
- Do not support clearing/null points in this phase.

## Done

- Feature tests cover read shape, successful matrix save, invalid detail rejection, over-max rejection, and all-or-nothing behavior.
- Backend docs under `docs/api/lecturer/` are updated for the new endpoints.
