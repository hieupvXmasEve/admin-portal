# Execution Plan

Migrate HP/Tuition and EGC charge generation so Batch Studio is the only primary
operator workflow, while preserving existing money logic and compatibility for
old URLs.

Portal impact: none.

## Constraints

- Do not change charge amount, discount, scholarship, installment, EGC block, or
  retake eligibility math.
- Do not widen permissions. Authorization must be per fee category.
- Do not remove old URLs before tests prove redirects or blocked writes are safe.
- Do not touch student or lecturer portal files.

## Steps

1. **Discovery and parity check**
   - Compare standalone HP/Tuition and EGC pages against Batch Studio charges.
   - Confirm supported filters, due date behavior, block count selection, result
     summaries, and warning/skip handling.
   - Identify any Batch Studio gaps required before redirecting the old pages.

2. **Authorization correction**
   - Ensure Batch Studio charge preview and commit authorize by `fee_category`.
   - HP/Tuition requires `create_finance_charges`.
   - EGC requires `generate_egc_finance_charges`.
   - Add regression coverage for EGC-only operators.

3. **Prefill contract**
   - Add safe prefill support for `fee_category=major` and `fee_category=egc`.
   - Preserve supported selected student ids or filters only if the wizard can
     validate them server-side.
   - Reject or ignore unknown prefill keys.

4. **Navigation cutover**
   - Update sidebar, Cockpit shortcuts, Lookup bulk actions, and internal links
     so HP/Tuition and EGC generation open Batch Studio charges.
   - Keep EGC Block Results, Retake Adjustments, and Carry Forward unchanged.

5. **Legacy route compatibility**
   - Convert old GET routes to redirect/forward to Batch Studio or label them as
     secondary repair-only pages if product confirms a repair need.
   - Block or retire old POST routes so normal generation cannot bypass Batch
     Studio preview-token verification.

6. **Validation and evidence**
   - Run targeted feature tests for HP/Tuition and EGC Batch Studio preview and
     commit.
   - Run redirect/blocking tests for legacy routes.
   - Run targeted frontend lint/type checks for changed Batch Studio/navigation
     files.
   - Run `finance:audit-invariants` after exercising write paths, or document why
     no write-capable validation was performed.
   - Update this story validation and the M6 cutover inventory with final
     evidence.

## Stop Conditions

- Batch Studio cannot reproduce an existing standalone generation behavior
  without changing money math.
- EGC-only authorization cannot be represented cleanly with the shared commit
  request.
- Product requires standalone pages to remain primary.
- Validation would need to skip direct-write bypass coverage.
