# Exec Plan

## Goal

Track Task 3 from the Milestone 1 plan as its own story: add the minimal Student
360 route shell.

## Scope

In scope:

- `Student360ShowRequest`
- `FinanceStudentOverviewController`
- `finance.students.overview` route
- `resources/js/pages/Finance/Student360/Show.vue`
- shared Finance TS types needed by the page
- feature tests for render, focus, permission, and campus scope

Out of scope:

- Full Student 360 milestone.
- Write actions or destructive-action UI.
- Student/lecturer portal APIs.

## Risk Classification

Risk flags:

- Authorization: permission and campus scope.
- PII: student identity and balances.
- Public contract: new route and Inertia props.
- Weak proof: frontend has no JS unit runner.

Hard gates:

- Cross-campus students must be hidden as 404 unless all-campus permission is
  present.
- Money values must come from existing Finance read models.
- Deferred prop syntax must use Inertia v3 patterns.

## Work Phases

1. Write failing feature test.
2. Add request/controller/route.
3. Add page/types.
4. Run targeted test and lint.
5. Record acceptance evidence.

## Stop Conditions

Pause if:

- Existing balance query output differs from the planned prop names.
- Campus context cannot be resolved safely.
- Full 360 behavior starts entering this minimal shell story.
