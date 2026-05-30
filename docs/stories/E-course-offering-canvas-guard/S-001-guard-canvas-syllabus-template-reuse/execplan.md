# Exec Plan

## Goal

Prevent accidental reuse of Canvas-specific syllabus templates across course
offerings so Canvas LMS grade sync stays isolated to one class.

## Scope

In scope:

- Shared eligibility scope for syllabus-template selection.
- Backend validation for store and update.
- Create and edit dropdown filtering.
- Targeted feature coverage.
- Canvas documentation and test-matrix update.

Out of scope:

- Canvas provider API calls.
- Canvas mapping redesign.
- Data cleanup for already-shared Canvas templates.
- Class-session modal changes.

## Risk Classification

Risk flags:

- External systems.
- Existing behavior.
- Weak proof.
- Data integrity.

Hard gates:

- External provider behavior.

## Work Phases

1. Confirm protected-template semantics with the human.
2. Add the model scope and validation rule.
3. Apply the scope to create and edit props.
4. Add targeted feature tests.
5. Run targeted tests and formatting checks.
6. Update Canvas docs, matrix evidence, and Harness trace.

## Stop Conditions

Pause for human confirmation if:

- Mapping-only course offerings must reserve their original reusable template.
- Existing invalid template sharing requires automatic cleanup.
- `/course-offerings/180/add` refers to a custom route outside the checked-in
  repository.
- Validation requirements need to be weakened.
- Architecture direction changes.
