# Run defer, resume, and dropout through Program Enrollment and Finance contracts

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Move defer, resume, dropout, return, and related lifecycle previews onto Academic Progression & Lifecycle-owned Program Enrollment transitions. Replace Academic calls to Finance implementation services with narrow Finance query/command contracts while preserving existing settlement choices, durable cancellation handoffs, audit evidence, and staff workflow.

## Acceptance criteria

- [x] Lifecycle actions transition Program Enrollment Status and related Study Stage facts without mutating Student Identity or Account Status.
- [x] Eligibility, target state, course scope, and required Decision rules are enforced by Progression ownership.
- [x] Finance previews and mutations run through owner contracts; Academic does not import Finance concrete Actions or services.
- [x] Existing preserve/forfeit, DNG cancellation, paid-evidence, pending-cancellation, and return-study behavior remains consistent with Finance ADRs.
- [x] Atomic local writes and durable cross-context handoffs prevent partial or lost lifecycle operations.
- [x] Existing staff routes, audit timeline, reports, student-facing lifecycle behavior, and API shapes remain compatible.
- [ ] Lifecycle, Finance handoff, failure recovery, architecture, and student portal tests pass.

## Blocked by

- [Issue 01: Publish Academic lifecycle notifications through the shared Domain Event boundary](01-publish-academic-lifecycle-notifications-through-shared-boundary.md)
- [Issue 08: Use Student Reference throughout Finance collection and DNG flows](08-use-student-reference-in-finance-collection.md)
- [Issue 10: Materialize Program Enrollment and separate its state machines](10-materialize-program-enrollment.md)
- [Issue 14: Manage Course Registration and roster through Course Delivery](14-manage-course-roster-through-delivery.md)

## Verification

- Passed: focused Progression lifecycle, staff filter/report, Program Enrollment materialization, Finance defer policy/runtime/itemization, failure recovery, architecture, and student portal compatibility suite — 58 tests, 306 assertions.
- Passed: PHP Pint, `git diff --check`, student portal `pnpm typecheck`, and student portal production build (existing Nuxt warnings only).
- Passed: required two-axis review (repository standards and issue specification) with no remaining findings.
- Blocked outside this slice: `./scripts/dev.sh test` exits 255 without diagnostics. Root type-check exhausts Node's default heap; root lint scans generated nested-repository output and reports pre-existing errors; root format check reaches pre-existing malformed Vue templates and formatting drift.
- Blocked outside this slice: student portal `pnpm lint` cannot load `eslint-plugin-pnpm` because the nested repository has no `pnpm-workspace.yaml`.

## Comments

- 2026-07-19: Lifecycle transitions now lock and update the Progression-owned Program Enrollment while retaining Student Registry and Account Status unchanged. A central compatibility projection preserves the existing public lifecycle vocabulary, exact staff filters, report output, and portal response shapes without reintroducing `students.status` as lifecycle truth.
- 2026-07-19: Academic now uses narrow Finance reader/command/evidence contracts and a Course Delivery registration gateway. Defer case creation, itemization, preserve/forfeit settlement, canonical DNG cancellation, EGC credits, lifecycle audit, and Program Enrollment transition remain within the same rollback boundary.
- 2026-07-19: The retired `dng_payment_requests.finance_charge_id` fixture now explicitly asserts that the removed header pointer is unsupported; installment, pivot, and reservation-target cancellation coverage remains green.
