# Overview

## Current Behavior

Finance UI uses several generations of table/filter/form patterns, duplicated
formatters, inconsistent badges, mixed Vietnamese/English terminology, and weak
navigation between charge, invoice, payment, settlement, and DNG records.

## Target Behavior

Finance UI has a shared foundation for money/date/status display, list pages use
`useDataTable` when refactored, dangerous actions use consistent confirmation,
and staff can navigate through the transaction lifecycle without dead ends.

## Affected Users

- Finance staff.
- Finance managers.
- Admin users who operate all Finance pages.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/design-guidelines.md`
- `docs/rules/frontend.md`
- `docs/rules/filtering.md`

## Portal Impact

None.

## Non-Goals

- Do not build BOD oversight in this story.
- Do not change Finance math.
- Do not rewrite every page in one PR; migrate page groups incrementally.
