# Exec Plan

## Goal

Implement a small syllabus/campus grading rule engine that supports
Metropolia-style grading without changing existing weighted-percentage results.

## Scope

In scope:

- Syllabus-level `grading_scheme` JSON.
- Default weighted-percentage calculator.
- `metropolia_v1` calculator.
- Manual grade aggregation integration.
- Course finalization pass/fail integration.
- Canvas custom-scheme boundary protection.
- Backend tests and academic docs.

Out of scope:

- Visual grading-scheme builder UI.
- Historical recalculation job.
- Shared-database tenant isolation.
- Portal UI redesign.

## Risk Classification

Risk flags:

- Data model.
- Audit/security.
- External systems.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Audit/security.
- External provider behavior.

## Work Phases

1. Add storage contract and default config.
2. Add calculator data objects and resolver.
3. Preserve current default weighted-percentage behavior.
4. Implement `metropolia_v1`.
5. Integrate aggregation and finalization.
6. Protect Canvas custom-scheme courses from Canvas total overwrite.
7. Document scheme JSON and validation evidence.

## Stop Conditions

Pause for human confirmation if:

- Metropolia source data does not identify syllabus templates or component
  codes.
- A custom scheme needs to change existing API response shape.
- A production database needs historical recalculation.
- Canvas courses must trust Canvas final totals for custom schemes.
