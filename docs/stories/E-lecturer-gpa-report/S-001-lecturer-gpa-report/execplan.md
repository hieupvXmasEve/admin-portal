# S-001 Lecturer GPA Report Exec Plan

## Goal

Add a staff-facing Lecturer GPA report for the active semester, with Survey
Results linking and Excel export.

## Scope

In scope:

- Add `/lectures/lecturers-GPA` report page.
- Default selected semester to `Semester::getActiveSemester()`.
- Compute one GPA row per lecturer by averaging each class's Lecturer
  Evaluation rating score first, then averaging those class-level scores across
  selected-semester classes taught by that lecturer.
- Show No, Lecturer name, email account, Employee ID, Type, Courses taught, and
  GPA.
- Add Excel export for the same filters.
- Link from the Survey Results aggregate page to the Lecturer GPA page.
- Update product docs and test matrix.

Out of scope:

- Survey template editing or migration.
- New database tables.
- Raw response/student detail display.
- Lecturer API/mobile changes.

## Risk Classification

Risk flags:

- Authorization.
- Audit/security.
- Public contract.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Authorization/permission handling for a new sensitive report route.

## Work Phases

1. Discovery: confirm survey answer joins, route order, permission convention,
   export pattern, and frontend table pattern.
2. Design: keep calculation in a read-only query and reuse it for page/export.
3. Validation planning: add targeted feature tests for calculation,
   authorization, campus/semester scope, and export.
4. Implementation: backend query/controller/request/export, route constants,
   Vue page, Survey Results link, docs.
5. Verification: run targeted Laravel test, targeted format/lint, and
   TypeScript check.
6. Harness update: update story evidence, matrix row, and trace.

## Stop Conditions

Pause for human confirmation if:

- The live survey template does not identify Lecturer Evaluation by section
  title.
- The report needs a new distinct permission instead of reusing existing
  lecturer/survey permissions.
- Product wants weighted GPA by class, by student response, or by course instead
  of answer-level average.
- Any schema change appears necessary.
