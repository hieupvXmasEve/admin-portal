# Exec Plan

## Goal

Track Task 4 from the Milestone 1 plan as its own story: add global Finance
search and the command palette component.

## Scope

In scope:

- `FinanceGlobalSearchController`
- `finance.search` route
- `FinanceGlobalSearchTest`
- `FinanceCommandPalette.vue`

Out of scope:

- Mounting the palette in the topbar.
- Full keyboard/browser smoke.
- New search indexing or search tables.
- Portal APIs.

## Risk Classification

Risk flags:

- Authorization: search is permission-gated.
- PII: search returns student identity and navigation URLs.
- Public contract: JSON endpoint consumed by frontend.
- Existing behavior: reuses audit resolver behavior.
- Weak proof: command palette has no JS unit test runner.

Hard gates:

- Must use `ApiResponse::success()`.
- Must preserve campus scoping from the resolver.
- Must not expose cross-campus matches to ordinary finance users.

## Work Phases

1. Write failing endpoint test.
2. Add controller and route.
3. Add command palette component.
4. Run targeted tests and lint.
5. Leave topbar mounting to Task 7.

## Stop Conditions

Pause if:

- Audit resolver cannot map a target to an owning student.
- Search would need broader permissions than Student 360.
- Non-student target focus behavior becomes full 360 deep-scroll work.
