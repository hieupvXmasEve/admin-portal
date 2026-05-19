## Why

Staff need a consistent way to define fixed-amount scholarships from a total scholarship budget and the number of payable terms. Today the scholarship stores only the resulting amount, so the source calculation is lost and cannot be reused for display or review on other scholarship pages.

## What Changes

- Add persisted scholarship calculation fields for fixed-amount scholarships:
  - total scholarship amount
  - total number of terms
- On both scholarship create and edit forms, allow staff to enter total amount and total terms when discount type is fixed amount.
- Automatically calculate the stored discount amount as `ceil(total_amount / total_terms / 1000) * 1000`.
- Keep the calculated discount amount visible to staff before submission.
- Display the saved total amount and total terms on scholarship detail/list surfaces where scholarship amounts are shown.
- Clear or ignore total-based calculation fields when discount type is percentage.

## Capabilities

### New Capabilities
- `scholarship-total-based-fixed-discount`: Persist and display total-based fixed scholarship calculation inputs and derive fixed discount amount from them.

### Modified Capabilities

## Impact

- Database: add nullable calculation fields to `scholarship_definitions`.
- Backend: update `ScholarshipDefinition`, `ScholarshipRequest`, and scholarship create/update flow to validate and persist calculation fields.
- Frontend: update `resources/js/pages/Scholarships/Create.vue`, `Edit.vue`, `Show.vue`, and likely `Index.vue` plus scholarship TypeScript/schema definitions.
- Validation: add focused coverage for fixed-amount calculation, rounding, percentage behavior, and persisted display fields.
