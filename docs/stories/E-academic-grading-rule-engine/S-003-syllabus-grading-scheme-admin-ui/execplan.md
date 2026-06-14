# Exec Plan

## Goal

Add a small admin UI for editing, validating, and previewing syllabus grading
schemes.

## Scope

In scope:

- Web-authenticated scheme options endpoint.
- Web-authenticated preview endpoint.
- Create/edit/show page integration.
- Focused Vue editor and preview components.

Out of scope:

- Portal display.
- Historical recalculation.
- Automatic scheme import.

## Risk Classification

Risk flags:

- Authorization
- Data model
- Audit/security
- Public contracts
- Existing behavior
- Weak proof
- Multi-domain

Hard gates:

- Authorization-sensitive admin write path.

## Work Phases

1. Add backend preview tests.
2. Add request/action/controller/routes.
3. Add frontend types and components.
4. Integrate create/edit/show pages.
5. Run targeted verification.
6. Record Harness trace.

## Stop Conditions

Pause for human confirmation if:

- A visual rule builder becomes required.
- Existing syllabus page form patterns need a broad rewrite.
- Admin permissions are missing or named differently than expected.
