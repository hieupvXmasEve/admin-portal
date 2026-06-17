# Design

## Domain Model

This story does not add new finance entities. It defines a product contract for
existing object detail surfaces:

- **Primary work surfaces**: task-first pages where operators normally start
  work, such as Cockpit, Lookup, Student 360, Batch Studio, Settlement, DNG
  request list, and Audit Workspace.
- **Secondary detail surfaces**: stable named-route pages for a single charge,
  invoice, payment, or DNG request.
- **Repair controls**: existing write actions nested under or linked from detail
  pages, each guarded by its existing write permission and audit behavior.

Rules:

- Detail pages are not obsolete legacy pages. They are stable evidence and
  repair endpoints.
- Detail route names are part of the internal deep-link contract.
- Write/repair actions must stay separate from read permissions.
- DNG-centric repairs must continue to prefer `CancelDngPaymentRequestAction`
  paths rather than using generic charge voiding as the primary DNG fix surface.

## Application Flow

### Normal navigation

```text
Sidebar / shortcuts
  -> Cockpit, Lookup, Student 360, Batch Studio, Settlement, DNG list, Audit
  -> row/action/drilldown link
  -> detail page only when the operator selected a specific record
```

### Audit/deep-link navigation

```text
Audit graph / timeline / invariant sample / DNG webhook / Cockpit queue
  -> named route for object detail
  -> detail page renders current record and linked evidence
  -> optional repair action appears only when permission allows
```

### Repair navigation

```text
Detail page
  -> repair action preview/confirm
  -> existing Action/Controller path
  -> existing audit/event/log behavior
  -> return to detail, Student 360, or task-first source as appropriate
```

## Interface Contract

### Routes that must remain stable

| Surface | Route name | URL | Read permission | Product role |
| --- | --- | --- | --- | --- |
| Charge detail | `finance.charges.show` | `/finance/charges/{charge}` | `view_finance_charges` | Detail / audit / repair |
| Invoice detail | `finance.invoices.show` | `/finance/invoices/{invoice}` | `view_finance_invoices` | Detail / audit / repair |
| Payment detail | `finance.payments.show` | `/finance/payments/{payment}` | `view_finance_payment_details` | Detail / audit / repair |
| DNG request detail | `finance.dng.payment-requests.show` | `/finance/dng/payment-requests/{dngPaymentRequest}` | `view_finance_dng_payment_requests` | Detail / audit / repair |

### Repair actions that must stay stricter than read-only detail

| Action route | Permission | Notes |
| --- | --- | --- |
| `finance.charges.void` | `void_finance_charges` | Charge repair; not the primary DNG lifecycle fix path |
| `finance.charges.update-description` | `create_finance_charges` | Metadata repair |
| `finance.charges.installments.split` | `split_installment_finance_charges` | Installment repair/split flow |
| `finance.charges.installments.retry-push` | `split_installment_finance_charges` | Installment DNG push retry |
| `finance.invoices.lines.void` | `void_finance_charges` | Invoice-line repair |
| `finance.payments.allocate-preview` | `allocate_finance_payment` | Read-only allocation preview for repair |
| `finance.payments.allocate` | `allocate_finance_payment` | Payment allocation repair |
| `finance.dng.payment-requests.cancel-impact` | `create_finance_payments` | Read-only impact surface before cancel |
| `finance.dng.payment-requests.cancel-reviewed` | `create_finance_payments` plus action-level void gate when linked charges exist | Reviewed DNG cancel path |
| `finance.dng.payment-requests.cancel` | `create_finance_payments` | Existing compatibility path; do not make primary |

### Deep-link sources to preserve

- Lookup row action links for charges, invoices, and payments.
- Student 360 contextual links to object details when a repair/audit task needs
  the underlying object page.
- Audit Workspace `SOURCE_ROUTES` for invoice, charge, payment, and DNG nodes.
- Cockpit queue links to DNG/payment/charge repair destinations.
- DNG request and webhook cross-links.
- Lifecycle exception history links to the DNG request detail.

## Data Model

No migrations, new tables, new indexes, or data repair. The story may add tests
or docs only unless implementation reveals an existing route contract bug.

## UI / Platform Impact

Implementation may touch:

- `resources/js/constants/menu-sidebar.ts`
- `resources/js/lib/financeRoutes.ts` or the current finance route helper file
- `resources/js/pages/Finance/Charges/Show.vue`
- `resources/js/pages/Finance/Invoices/Show.vue`
- `resources/js/pages/Finance/Payments/Show.vue`
- `resources/js/pages/Finance/Payments/DngPaymentRequests/Show.vue`
- Pages that generate deep links into those details

UI rules:

- Detail pages should use existing AppLayout, breadcrumbs, buttons, badges, and
  permission checks.
- Do not add new primary sidebar items for record-specific detail pages.
- Prefer named `route(...)`/finance route helpers over literal URLs.
- Keep repair buttons visibly gated by existing permissions and disabled/hidden
  consistently with current Finance patterns.

## Observability

- Route contract tests prove named routes and permissions stay stable.
- Link-generation tests or component-level checks prove deep-link sources still
  use valid route names.
- Any repair action touched by implementation keeps existing audit/log evidence.
- No `finance:audit-invariants` count should change from this story's read-only
  navigation/contract work.

## Alternatives Considered

1. **Delete or redirect detail routes to Student 360** — rejected because Audit
   Workspace, bookmarked links, and repair flows need object-specific evidence.
2. **Keep detail pages as normal sidebar destinations** — rejected because the
   Finance Office IA is now task-first; detail pages should be reached from a
   selected record or evidence link.
3. **Move DNG repair to charge detail** — rejected because DNG cancellation must
   remain DNG-centric and preserve DNG request audit/state handling.
