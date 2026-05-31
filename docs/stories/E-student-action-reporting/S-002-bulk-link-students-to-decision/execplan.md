# Exec Plan

## Goal

Allow staff to paste many student codes, preview the matched students/action
logs, and link all eligible action logs to a selected student decision quickly.

## Scope

In scope:

- Add preview and confirm routes to the existing student decision web route
  group.
- Add FormRequests for bulk student-code input and selected action type.
- Add Academic module Query/Action classes for preview and linking.
- Add feature tests for preview, confirm, campus scope, and invalid input.
- Extend the existing decision detail Vue page.
- Update product/architecture docs if route or behavior contract changes.

Out of scope:

- Creating new student action logs.
- Student or lecturer portal changes.
- Database schema changes.
- Unlinking existing decision associations.

## Risk Classification

Risk flags:

- Audit/security.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- Audit/security because the write path mutates administrative action records.

## Work Phases

1. Discovery: confirm existing decision/action relation, route names, and UI
   patterns.
2. Design: define preview and confirm contracts with campus scope.
3. Validation planning: add focused backend feature coverage.
4. Implementation: add Query/Action/Requests/routes/controller methods and UI.
5. Verification: run targeted tests and frontend checks.
6. Harness update: mark story status and record trace.

## Stop Conditions

Pause for human confirmation if:

- Product behavior requires choosing among multiple action logs per student after the selected action-type filter is applied.
- Existing linked decisions should be overwritten instead of skipped.
- Validation commands cannot run through the Docker wrapper.
- The implementation requires a schema migration.
