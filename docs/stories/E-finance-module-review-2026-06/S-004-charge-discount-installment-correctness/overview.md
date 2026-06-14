# Overview

## Current Behavior

Charge generation and discount/installment math still have several divergent
paths: scholarship caps differ by flow, EGC amount source is unresolved, voided
charges can block regeneration, PHP casts can truncate money, and installment or
DNG pivot totals can drift after later changes. Voiding a charge also leaves
linked installments and immediate auto-reallocation side effects ambiguous
(`FIN-12`).

## Target Behavior

All charge-generation paths use the same business rules for scholarship caps,
EGC fee source, active charge detection, installment totals, and DNG pivot
rounding. Preview and execute math must match. Voiding a charge must leave
installment and reallocation state explicit, tested, and auditable.

## Affected Users

- Finance staff generating charges.
- Students whose obligations are created through EGC, major, retake, or voucher
  flows.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/project-overview-pdr.md`

## Portal Impact

None unless a future implementation exposes changed finance data through
student APIs.

## Non-Goals

- Do not rewrite the full ledger calculation.
- Do not change DNG webhook security.
- Do not resolve historical duplicates except where a targeted test fixture
  needs them.
