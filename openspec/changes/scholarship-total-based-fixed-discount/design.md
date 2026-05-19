## Context

Scholarship definitions currently persist only `type` and `amount` for the discount value. Fixed-amount scholarships can be derived from a total scholarship budget spread across a number of terms, but that source calculation is not stored, so detail/list pages cannot explain how the amount was produced.

The current implementation is in the legacy scholarship surface:

- `ScholarshipController` renders `Scholarships/Create`, `Edit`, `Show`, and `Index`.
- `ScholarshipRequest` validates create/update payloads.
- `ScholarshipService` persists validated data into `ScholarshipDefinition`.
- `ScholarshipDefinition` maps to `scholarship_definitions`, whose current amount column is `decimal(15, 2)`.
- Existing finance support code already uses small resolver classes for amount calculations, such as `App\Modules\Finance\Support\VoucherDiscountAmountResolver`.

## Goals / Non-Goals

**Goals:**

- Persist the total scholarship amount and total term count for fixed-amount scholarships.
- Calculate the canonical fixed discount amount from those fields on create and edit.
- Use the user's example as the canonical rounding rule: round up to the next 1,000 VND.
- Make backend calculation authoritative even if the frontend submits a stale `amount`.
- Show the saved calculation context on scholarship display surfaces.
- Keep existing percentage scholarship behavior simple and unchanged.

**Non-Goals:**

- Backfill total amount or term count for existing fixed scholarships. The source data cannot be safely inferred from the current `amount` alone.
- Recalculate historical student scholarship awards, invoices, invoice discounts, or discount allocations.
- Change how scholarships are assigned to students.
- Introduce a new scholarship pricing engine or tuition-plan integration.

## Decisions

### Add nullable calculation columns on `scholarship_definitions`

Add `total_amount` as a nullable decimal and `total_terms` as a nullable unsigned integer. The fields live on the scholarship definition because the user needs to reuse them across multiple scholarship pages, and the values describe the scholarship definition itself rather than a student-specific award.

Alternative considered: keep the fields as frontend-only helper inputs. That was rejected because the calculation source must be available for later display.

### Treat backend as the calculation authority

For fixed-amount scholarships, the persisted `amount` must be derived from `total_amount` and `total_terms` using:

```text
ceil((total_amount / total_terms) / 1000) * 1000
```

The frontend will preview the calculated amount, but the backend will normalize the final payload before persistence so stale browser state or manual request tampering cannot persist a mismatched amount.

Alternative considered: let the frontend calculate and submit `amount`. That is weaker because it duplicates a finance rule without server-side enforcement.

### Add a small calculation helper instead of embedding the formula everywhere

Place the calculation in a small, testable backend helper, preferably under Finance support where existing discount amount resolvers live. `ScholarshipRequest` or the create/update flow can call this helper to normalize validated data. The frontend should use a matching tiny utility function for instant preview, with tests or type-check coverage to keep the formula obvious.

Alternative considered: inline the formula in `Create.vue`, `Edit.vue`, and `ScholarshipRequest`. That raises drift risk across create, edit, and server-side validation.

### Require calculation fields for fixed amount, clear them for percentage

When `type = fixed_amount`, `total_amount` and `total_terms` are required and must be positive. When `type = percentage`, those fields are not required and should be stored as `null`, with `amount` continuing to represent the percentage value.

Alternative considered: allow optional total fields for fixed scholarships and fall back to manual amount entry. That preserves legacy behavior but weakens the new invariant and leaves some fixed scholarships without displayable calculation context.

### Keep display additive

`Show.vue` should display the total amount and total terms near the calculated discount amount. `Index.vue` should retain the existing amount column and add compact calculation context for fixed scholarships that have persisted source fields.

Alternative considered: add new columns to the index table. That is noisier for a list page and is not required for the first implementation.

## Risks / Trade-offs

- Existing fixed scholarships will have `null` calculation fields after migration -> leave existing rows untouched and require staff to supply calculation fields when editing fixed scholarships.
- Formula drift between frontend preview and backend persistence -> centralize backend calculation and keep the frontend formula small, explicit, and covered by targeted checks.
- Amount mismatch if a request includes both `amount` and total fields -> backend ignores submitted fixed `amount` and persists the derived value.
- Percentage scholarships accidentally retain stale total fields -> backend nulls total fields whenever `type = percentage`.

## Migration Plan

1. Add a migration with nullable `total_amount` and `total_terms` columns to `scholarship_definitions`.
2. Update the model fillable/casts so the values are serialized to Inertia props.
3. Update validation and normalization for scholarship create/update.
4. Update Create/Edit forms to collect total fields and preview the calculated amount for fixed scholarships.
5. Update Show/Index display surfaces.
6. Add focused tests for validation, calculation, and persistence.

Rollback is safe at schema level by dropping the new nullable columns. No historical invoice or award data is transformed by this change.

## Open Questions

- None. The rounding increment is treated as 1,000 VND because `175000000 / 9` rounding to `19445000` matches that rule.
