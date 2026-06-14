# Design

## Domain Model

Use the existing audit command as the baseline proof surface:
`app/Console/Commands/AuditFinanceInvariants.php`.

The first slice treats unsafe UI/actions as operational safety issues, not as a
new finance math model.

## Application Flow

- Run `finance:audit-invariants --sample` against the intended snapshot.
- Record counts for INV-1..INV-11 and a small sample for failures.
- Add/repair UI confirmation and impact preview where a dangerous action is
  already available.
- Remove or disable temporary/manual flows that can misallocate money before the
  safer replacement is ready.

## Interface Contract

No public API or portal contract changes.

Admin/staff Finance pages may change only to add confirmations, warnings, and
safe disabled states.

## Data Model

No migrations in this story.

## UI / Platform Impact

Targeted surfaces from the review:

- Void charge impact preview.
- Payments/Show temporary manual allocation form.
- Bulk actions on settlement, due calendar, exception queue, and auto allocate.
- `Settlement.vue` layout/import/runtime drift.
- Console logging in Finance formatting and operation pages.

## Observability

The invariant output becomes evidence for later data guard stories. Dangerous
actions should leave the existing audit trail unchanged or stronger.

## Alternatives Considered

1. Start directly with DB constraints.
   - Rejected because INV-6 and INV-11 are known dirty-data blockers.
2. Start with UI redesign.
   - Rejected because the immediate value is safety and measurement, not a new
     visual system.
