# Exec Plan

## Goal

Show all current-campus query inbox records to authorized inbox users, with department filtering limited to Student HQ and Academic Service.

## Scope

In scope:

- Remove logged-in-user membership and assignee filters from admin query inbox listing.
- Keep current-campus and query-form base scopes.
- Limit department filter options to active `HQ` and `ACA` departments.
- Ensure detail display follows the same current-campus query visibility model.
- Add focused feature tests.

Out of scope:

- Changing student or lecturer portal APIs.
- Changing database schema.
- Changing assignment assignee validation.

## Risk Classification

Risk flags:

- Authorization.
- Existing behavior.
- Weak proof.
- Public contract.

Hard gates:

- Authorization scope.

## Work Phases

1. Register story.
2. Patch controller behavior.
3. Add focused feature tests.
4. Run targeted backend tests and frontend type-check.
5. Record Harness trace.

## Stop Conditions

Pause for human confirmation if:

- The requested behavior would need cross-campus visibility.
- Assignment permissions must also be broadened.
- Tests reveal unrelated broken factories or baseline failures that obscure proof.
