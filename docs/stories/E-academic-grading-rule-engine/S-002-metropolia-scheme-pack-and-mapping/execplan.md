# Exec Plan

## Goal

Create the canonical Metropolia scheme pack and a safe per-database application
path.

## Scope

In scope:

- Scheme catalog JSON.
- Scheme catalog loader.
- Scheme validator.
- Dry-run-first import command.
- Mapping documentation.

Out of scope:

- Admin UI.
- Student or lecturer portal display.
- Historical recalculation.

## Risk Classification

Risk flags:

- Data model
- Audit/security
- Existing behavior
- Weak proof
- Multi-domain

Hard gates:

- Writes to `syllabus_templates.grading_scheme` in commit mode.

## Work Phases

1. Lock catalog shape and fixture keys.
2. Add catalog loader and validator tests.
3. Add mapping action and command tests.
4. Add operator mapping docs.
5. Run targeted verification.
6. Record Harness trace.

## Stop Conditions

Pause for human confirmation if:

- A Metropolia prose rule cannot be represented by `metropolia_v1`.
- Local syllabus titles or unit codes are required but unavailable.
- Applying schemes requires shared-database assumptions.
