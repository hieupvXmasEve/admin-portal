# Exec Plan

## Goal

Track Task 5 from the Milestone 1 plan as its own story: add the shared semester
context contract and switcher.

## Scope

In scope:

- `HandleInertiaRequests` shared `semester` prop.
- `FinanceSemesterContextController`.
- `finance.semester-context.update` route.
- `SemesterSwitcher.vue`.
- `SharedData`/Finance TS type updates.
- `SemesterContextTest`.

Out of scope:

- Retrofitting semester-bound Finance pages.
- Student/lecturer portal context.
- New persistence table.

## Risk Classification

Risk flags:

- Public contract: shared Inertia prop and route.
- Existing behavior: app shell context changes.
- Cross-platform/browser shell: topbar selector.
- Weak proof: no JS unit runner.

Hard gates:

- The shared prop must be omitted/null for users without Finance permissions.
- Select values must use valid non-empty strings in shadcn select.
- Session write must not mutate Finance money state.

## Work Phases

1. Write failing feature test.
2. Add shared prop and update route/controller.
3. Add TS type and switcher.
4. Run targeted test and lint.
5. Leave topbar mounting to Task 7.

## Stop Conditions

Pause if:

- Finance permission detection in middleware becomes expensive or ambiguous.
- The switcher requires changing unrelated page filters.
