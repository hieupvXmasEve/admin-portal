# Design

## Domain Model

No domain model changes.

## Application Flow

The sidebar configuration groups existing links by staff workflow. Items keep
their existing permission requirements and route destinations, now using
`financeRoutes` where the route belongs to the Finance module.

## Interface Contract

Frontend config:

- `resources/js/constants/menu-sidebar.ts`

Navigation groups:

- Hom nay
- Sinh phi
- Thu & Doi soat
- Ngoai le
- Tra cuu & Audit
- Discounts & Funding as separate group

## Data Model

No database changes.

## UI / Platform Impact

Visible sidebar IA change. The old duplicate DNG Due Reminders entry is deduped
into the collection/reconciliation group.

## Observability

No logs or audit records. Validation is lint/build plus manual/sidebar smoke.

## Alternatives Considered

1. Keep the object-based menu.
   - Rejected because the design goal is a staff operator console.
2. Fold Discounts & Funding into Sinh phi.
   - Rejected because those routes are outside the Finance module and need an
     explicit separate-group decision.
