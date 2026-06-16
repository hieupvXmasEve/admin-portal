# Overview

## Current Behavior

Finance Office redesign milestones M1-M5 are implemented and verified:

- M1 Finance shell and Student 360 foundation.
- M2 full Student 360 surface and safe action drawers.
- M3 Cockpit "Hôm nay" triage.
- M4 Batch Studio.
- M5 Lookup & Audit.

However, the module can still feel unfinished operationally if legacy object-first
entrypoints, old dashboard links, duplicate list pages, and repair/deep-link
surfaces remain mixed with the new task-first navigation without a clear cutover
contract.

## Target Behavior

M6 makes the new Finance Office structure the primary operating path. It defines
and validates the cutover from legacy entrypoints to the new workflow groups:

- **Hôm nay** → Cockpit triage.
- **Sinh phí** → Batch Studio / charge-generation surfaces.
- **Thu & Đối soát** → DNG, settlement, allocation, reminders.
- **Ngoại lệ** → lifecycle and billing exception review.
- **Tra cứu & Audit** → Lookup standard and Audit Workspace.

The implementation must produce a route/menu/page inventory and classify each
legacy Finance surface as `keep`, `hide`, `redirect`, `deep-link`, or `retire`.
It must then apply the minimal safe cutover and record operator UAT evidence for
the main Finance workflows.

## Affected Users

- Finance staff using the admin/staff Finance Office daily.
- Finance leads validating role-based visibility and hand-off paths.
- Auditors and repair operators who still need deep links into detail pages.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`
- `docs/stories/E-finance-module-review-2026-06/S-010-student-360-full/`
- `docs/stories/E-finance-module-review-2026-06/S-010-cockpit/`
- `docs/stories/E-finance-module-review-2026-06/S-010-batch-studio/`
- `docs/stories/E-finance-module-review-2026-06/S-010-lookup-and-audit/`

## Portal Impact

Portal impact: none.

This story targets the admin/staff Finance Office web console. It must not change
`/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- Do not change ledger, DNG, settlement, charge-generation, allocation, or
  invariant money logic.
- Do not implement `S-011-student-360-installment-plan-guards`.
- Do not run production data repair.
- Do not delete backend Actions, Queries, or detail routes that are still needed
  for audit, repair, or deep links.
- Do not remove legacy routes until every replacement path and rollback/deep-link
  behavior is documented.

## Dependencies

| Dependency | Required for | Blocks M6? |
| --- | --- | --- |
| M1 `FIN-REV-010-finance-staff-workspace` | Route constants, shell, global search, semester context, sidebar IA | Yes |
| M2 `FIN-REV-010-student-360-full` | Student-centric landing and action drawers | Yes |
| M3 `FIN-REV-010-cockpit` | New "Hôm nay" primary landing | Yes |
| M4 `FIN-REV-010-batch-studio` | Bulk task destination | Yes |
| M5 `FIN-REV-010-lookup-and-audit` | Lookup and audit destination | Yes |
| `S-011-student-360-installment-plan-guards` | Installment safety hardening | No — explicitly deferred |

## Acceptance Summary

M6 is accepted when:

- The route/menu/page inventory exists and every Finance entrypoint has a
  cutover decision.
- The sidebar and route constants route normal operators into the new M1-M5
  structure.
- Legacy pages that remain are documented as detail, repair, or audit deep links.
- Role-based UAT confirms the main Finance workflows are reachable with the
  right permissions and hidden/disabled without them.
- Validation evidence proves no money-write behavior was changed by the cutover.
