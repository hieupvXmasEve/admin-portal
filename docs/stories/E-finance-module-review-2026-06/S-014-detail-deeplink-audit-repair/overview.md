# Overview

## Status

implemented

## Lane

high-risk

## Current Behavior

Finance Office M5/M6 has made Lookup, Student 360, Cockpit, Batch Studio, and
Audit Workspace the primary operator paths. The legacy object detail routes still
exist and are referenced by lookup row actions, audit graph source routes,
webhook/DNG pages, and repair actions:

- `finance.charges.show` — `/finance/charges/{charge}`
- `finance.invoices.show` — `/finance/invoices/{invoice}`
- `finance.payments.show` — `/finance/payments/{payment}`
- `finance.dng.payment-requests.show` — `/finance/dng/payment-requests/{dngPaymentRequest}`

Because these are no longer primary navigation destinations, future cleanup work
could accidentally remove or redirect the backend routes while trying to simplify
the UI. That would break bookmarked operator paths, audit evidence links, repair
entrypoints, and cross-surface drilldowns.

## Target Behavior

Keep the backend detail routes and route names intact, but make their product
role explicit:

- **Charges detail**: secondary detail/deep-link/audit/repair page for a single
  charge, including existing void/installment repair actions under their current
  permissions.
- **Invoices detail**: secondary detail/deep-link/audit/repair page for invoice
  lines and line-void repair.
- **Payments detail**: secondary detail/deep-link/audit/repair page for payment
  allocations and reconciliation follow-up.
- **DNG request detail**: secondary detail/deep-link/audit/repair page for DNG
  request state, cancel impact/review, linked payment, and webhook trail.

Normal daily navigation should lead operators to task-first pages first:
Cockpit, Lookup, Student 360, Batch Studio, Settlement, and DNG request lists.
The detail pages stay reachable from named-route deep links only: lookup row
actions, Audit Workspace graph/timeline links, Cockpit queue drilldowns, Student
360 contextual links, DNG/webhook cross-links, and documented repair flows.

## Affected Users

- Finance staff who follow deep links from Lookup, Student 360, Cockpit, or DNG
  queues into object details.
- Finance leads who need repair controls to remain permission-gated.
- Auditors who need stable evidence links into charge, invoice, payment, and DNG
  records.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/features/finance/dng-payment-integration.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-lookup-and-audit/`
- `docs/stories/E-finance-module-review-2026-06/S-010-cutover-and-uat/`
- `docs/stories/E-finance-module-review-2026-06/S-013-dng-payment-request-batch-studio-cutover/`

## Portal Impact

Portal impact: none.

This story targets authenticated admin/staff Finance Office web routes and
Inertia pages. It must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, the
student Nuxt portal, or the lecturer Nuxt portal.

## Non-Goals

- Do not delete, rename, redirect away from, or remove backend handlers for the
  existing detail routes listed above.
- Do not make detail routes primary sidebar destinations.
- Do not change money math, DNG provider behavior, webhook processing,
  reconciliation, settlement allocation, or charge generation rules.
- Do not add database migrations or production data repair.
- Do not loosen permissions on detail pages or their repair actions.
- Do not replace Student 360, Lookup, Cockpit, Batch Studio, or Audit Workspace
  as the primary operator paths.

## Dependencies

| Dependency | Required for | Blocks this story? |
| --- | --- | --- |
| M5 `FIN-REV-010-lookup-and-audit` | Lookup row actions and Audit Workspace source-route links | Yes |
| M6 `FIN-REV-010-cutover-and-uat` | Route inventory and primary-vs-secondary navigation decisions | Yes |
| `FIN-REV-013-dng-payment-request-batch-studio-cutover` | DNG creation path no longer competing with DNG detail/audit route | No, but coordinate labels |

## Acceptance Summary

This story is accepted when:

- Route contract tests prove the four backend detail route names still exist and
  keep their current authorization gates.
- Normal sidebar/task-first navigation does not promote these detail routes as
  primary destinations.
- Audit Workspace, Lookup, Student 360, Cockpit/DNG, and webhook surfaces can
  still generate valid named-route deep links to the detail pages.
- Existing repair actions under detail pages remain available only through their
  existing stricter permissions.
- Documentation and story evidence clearly classify these pages as
  detail/deep-link/audit/repair surfaces, not removable legacy pages.
