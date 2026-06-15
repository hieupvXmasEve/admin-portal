# Exec Plan

## Goal

Track Task 7 from the Milestone 1 plan as its own story: mount topbar
affordances and record milestone validation evidence.

## Scope

In scope:

- Update `resources/js/components/AppSidebarHeader.vue`.
- Gate Finance shell controls with `usePermissions`.
- Run full Milestone 1 backend tests and frontend build checks.
- Record validation evidence in story docs and Harness.

Out of scope:

- Implementing the underlying search/semester endpoints.
- Full automated browser test harness.
- Money/DNG write behavior.

## Risk Classification

Risk flags:

- Authorization: permission-gated topbar controls.
- PII: search entry point is visible in global shell.
- Existing behavior: app header changes.
- Cross-platform/browser shell: topbar keyboard/search behavior.
- Weak proof: manual smoke is required for rendered UI.

Hard gates:

- Do not show Finance search to users lacking Finance permissions.
- Do not mark the milestone complete if backend tests or build fail.
- Record manual smoke gaps honestly when automation is unavailable.

## Work Phases

1. Mount components.
2. Lint the header.
3. Run full Milestone 1 backend suites.
4. Run production build.
5. Perform/record browser smoke.
6. Record Harness trace/evidence.

## Stop Conditions

Pause if:

- Permission gating is unclear.
- Search or semester controls fail to render inside the existing header layout.
- Browser smoke finds a broken navigation path.
