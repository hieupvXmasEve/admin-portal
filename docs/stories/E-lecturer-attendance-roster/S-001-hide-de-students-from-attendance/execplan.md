# Exec Plan

## Status

Superseded by `S-002-show-inactive-students-in-roster`.

The shared eligibility/filtering work is retained for active counts and marking
guards. The UI visibility portion changed after user review: inactive students
are now shown separately with locked attendance controls.

## Goal

Ensure students who become DE after entering a class are inactive in all EGC and
Major lecturer attendance rosters, and remove obsolete FE agent workflow packs
so Harness is the single operating workflow.

## Scope

In scope:

- Backend roster eligibility/filtering for lecturer attendance and course
  roster APIs.
- Lecturer portal impact review/update if the API contract requires it.
- Removal of FE Khuym/ClaudeKit/agent workflow remnants discovered under
  `FE/`.
- Targeted tests and Harness trace/evidence.

Out of scope:

- Data deletion or migration of historical registrations.
- Student portal feature changes unrelated to workflow cleanup.
- Admin report behavior changes unless shared code makes them unavoidable.

## Risk Classification

Risk flags:

- Public contracts.
- Cross-platform.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- None identified at intake.

## Work Phases

1. Discovery: identify lecturer roster queries, status constants, and FE
   consumers.
2. Design: choose the smallest shared eligibility filter.
3. Validation planning: add or update targeted backend tests.
4. Implementation: backend filter, docs/tests, FE cleanup/update.
5. Verification: targeted tests and portal status/search checks.
6. Harness update: story evidence and trace.

## Stop Conditions

Pause for human confirmation if:

- DE status is stored ambiguously across multiple fields with conflicting
  business meaning.
- Excluding students requires destructive mutation of registration/attendance
  records.
- Existing tests show lecturers are expected to see DE students for grading or
  audit screens.
- FE changes require a broad redesign rather than consuming the same filtered
  API.
