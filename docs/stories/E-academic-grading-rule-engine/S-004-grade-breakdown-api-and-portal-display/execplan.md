# Exec Plan

## Goal

Expose safe grade breakdown display data and update student and lecturer
portals to consume it.

## Scope

In scope:

- Backend grade display presenter.
- Student grade response additions.
- Lecturer grade matrix/statistics response additions.
- Portal shared types and display components.
- API docs.

Out of scope:

- Lecturer grade input changes.
- Rule editing UI.
- Historical recalculation.

## Risk Classification

Risk flags:

- Public contracts
- Existing behavior
- Weak proof
- Multi-domain

Hard gates:

- Portal contract changes across two nested repos.

## Work Phases

1. Run portal status.
2. Add backend presenter tests and implementation.
3. Add student API contract tests and implementation.
4. Add lecturer API contract tests and implementation.
5. Update portal types and components.
6. Run backend and portal verification.
7. Record Harness trace.

## Stop Conditions

Pause for human confirmation if:

- A portal requires a redesigned grade page.
- Existing portal validation is blocked by unrelated dependency installation
  failure.
- Backend response shape needs a breaking rename instead of optional additions.
