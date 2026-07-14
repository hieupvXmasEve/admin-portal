# Finance Legacy Retirement

**Status:** ready-for-agent
**Predecessor:** [Finance Settlement Position Convergence](../finance-settlement-position-convergence/PRD.md)
**Portal impact:** student
**Primary ADRs:** [ADR-0028](../../docs/adr/0028-finance-charge-is-materialized-ledger-read-model.md), [ADR-0030](../../docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md), [ADR-0026](../../docs/adr/0026-academic-finance-boundary-and-retake-resit-candidate-cutover.md)

> This is an umbrella cleanup PRD. Implement it through the dependency-ordered issues in `issues/`, not as one change.

## Problem

The canonical Finance architecture is live and production data has been backfilled, but the repository still contains legacy collection entry points, duplicate settlement formulas, direct money writers, source-side charge pointers, one-time backfill tooling, compatibility routes, retired type identifiers, and temporary cutover/shadow code.

Leaving these paths in place creates two risks:

- a future caller can bypass the canonical Settlement Position or Settlement Mutation Guard;
- maintainers cannot distinguish permanent Finance ledger code from migration-only compatibility code.

The permanent settlement ledger remains `finance_charges`, invoice lines, discount/credit applications, payments, and their historical evidence. This project removes obsolete interpretations and bypasses; it does not delete legitimate ledger history.

## Approved decisions

1. The successfully backfilled canonical production data is the new data truth.
2. Restoring a database snapshot from before canonical backfill is not supported by the current code. Such a recovery would require checking out the pre-cleanup release.
3. The old DNG worklist compatibility URL and deprecated bulk-generation APIs may be removed immediately. Old bookmarks or external scripts may receive `404`; no compatibility period is required.
4. No additional seven-day production shadow period is required. Existing migration and manual verification are accepted as the cutover evidence; later exceptions may be repaired manually.
5. Existing migration files and legitimate historical settlement evidence remain in the repository/database.
6. `FinanceCharge` remains the materialized settlement ledger/read model. “Legacy Finance” means obsolete formulas, entry points, pointers, commands, constants, and bypasses—not the permanent ledger itself.

## Known data closure

- Student **AUH113129**: charge `#1184` is a legitimate active retake charge for `3,000,000 VND` and must receive a canonical obligation if missing. Charge `#1176` is already void and must remain void as historical evidence.
- Student **STU69150356** is a generated test fixture, not a real semester-8 student. `semester_id = 8` is a database key. The fixture graph includes charge `#1188`, invoice `#1471`, invoice line `#1203`, payment `#526`, payment application `#774`, billing account `#236`, and student row `#675`; cleanup must handle the graph consistently rather than deleting only the charge.

## Outcomes

When all issues are complete:

1. Every authoritative collectible read comes from Settlement Position.
2. Every money-changing operation passes through the Settlement Mutation Guard.
3. Every DNG creation path uses exact guarded reservation targets and canonical collectible.
4. No runtime consumer depends on legacy source-side `finance_charge_id` pointers.
5. Deprecated Finance routes, pages, helpers, backfill commands, and cutover-only code are gone.
6. The architecture guard has no bypass allowlist and detects all supported Eloquent writer variants.
7. Canonical production data remains arithmetically unchanged except for the two explicitly approved data repairs above.

## Delivery order

| Issue | Outcome | Blocked by |
|---|---|---|
| 01 | Close known data exceptions | None |
| 02 | Remove retired Finance and DNG surfaces | None |
| 03 | Converge all DNG creation on guarded reservations | None |
| 04 | Converge settlement reads on Settlement Position | None |
| 05 | Route settlement mutations through the guard | 04 |
| 06 | Remove legacy source pointers | 01, 03, 05 |
| 07 | Retire migration-only tools and legacy type registry | 01, 06 |
| 08 | Remove cutover compatibility and enforce zero bypass | 02–07 |

## Global safety rules

- Measure canonical gross, discount, cash, credit, remaining collectible, invoice totals, and payment totals before and after each money-affecting slice.
- Fail and stop on any unexplained data or money difference; do not clamp or silently repair it.
- Never infer a missing DNG/source link from amount or timing.
- Preserve void rows, payment evidence, DNG provider evidence, and already-run migrations.
- Use focused tests and Finance invariant audits after every slice.
- Student API contract changes require matching inspection and verification in the student portal repository.

## Done

The umbrella is complete only when all eight issues are complete, the full Finance invariant audit passes, focused architecture tests prove zero bypasses, and no production runtime reference to the retired interfaces remains.
