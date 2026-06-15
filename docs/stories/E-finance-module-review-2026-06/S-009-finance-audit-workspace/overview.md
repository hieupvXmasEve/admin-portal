# Overview

## Current Behavior

Finance staff audit a money case by jumping between separate Charges, Invoices,
Payments, DNG requests, settlement worklists, and exception pages. The menu has
many entry points, and several detail pages show ids as text instead of
permission-checked links.

The data model is not a linear lifecycle. A payment belongs to a student and is
spent through `payment_applications` onto `invoice_lines`; invoice lines point
to charges; DNG requests can point directly to one charge or to many
charge/installment rows through `dng_payment_request_charges`; discounts are
allocated through `discount_allocations`. Settlement totals are derived by
`SettlementService`, while `student_invoices.cached_*` columns are rebuildable
cache.

## Target Behavior

Add a read-mostly Finance Audit Workspace with universal search. A staff user can
enter an invoice number, student code/name, DNG item/payment/transaction id, or a
payment external reference and see a graph-based ledger timeline for the
visible finance records.

The workspace makes the current money truth explicit:

- Ledger events show applications, reversals, discount allocations, discount
  releases, DNG request links, and payment receipts.
- Derived balances come from `SettlementService`.
- Cached invoice snapshot values are shown only as cache and compared against
  derived values so cache drift becomes visible.
- Integrity warnings reuse the invariant catalog currently embedded in
  `AuditFinanceInvariants` after it is extracted into subject-scopeable shared
  code.
- Deep links open the existing source pages for money-changing actions.

## Affected Users

- Finance staff who reconcile student payments.
- Finance managers who review disputed or unusual cases.
- Admin users who currently need to cross-check multiple Finance pages.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/stories/E-finance-module-review-2026-06/S-007-finance-ui-foundation-and-navigation/`
- `resources/js/constants/menu-sidebar.ts`

## Portal Impact

None. The workspace is an authenticated admin/staff Inertia surface and does not
change `/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- Do not change Finance math, allocation rules, DNG webhook behavior, or DB
  constraints in this story.
- Do not build BOD oversight charts; that remains `FIN-REV-008`.
- Do not make the workspace a full action console. Void, cancel DNG, retry
  webhook, manual allocation, and other money-changing commands stay on their
  source pages.
- Do not treat `Settlement` as a persisted node or table.
- Do not accept bare numeric charge ids as a primary universal-search format;
  charge access should come through student/invoice context or an explicit
  prefixed/debug input such as `charge:<id>`.
