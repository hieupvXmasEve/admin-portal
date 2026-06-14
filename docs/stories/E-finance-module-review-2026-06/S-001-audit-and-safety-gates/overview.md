# Overview

## Current Behavior

The Finance review identified critical correctness and safety risks, but the
current local/prod proof is scattered across the review document. The app also
has immediate operator hazards: dangerous finance actions without impact
preview/confirmation, a temporary manual allocation form, `Settlement.vue`
runtime drift, and noisy console logging.

## Target Behavior

Create a verified baseline before larger finance rewrites:

- Run and archive the current Finance invariant report for the target dataset.
- Identify the exact rows blocking future uniqueness/idempotency constraints.
- Close immediate safety gaps that do not require the ledger redesign.
- Keep all larger money, DNG, and DB work out of this first slice.

## Affected Users

- Finance operations staff.
- Campus finance managers.
- Developers implementing later Finance review stories.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/code-standards.md`
- `docs/design-guidelines.md`

## Portal Impact

None. This story is limited to admin/staff Finance web and internal commands.

## Non-Goals

- Do not rewrite `SettlementService`.
- Do not add DB constraints.
- Do not change DNG provider protocol or webhook security.
- Do not build BOD oversight.
