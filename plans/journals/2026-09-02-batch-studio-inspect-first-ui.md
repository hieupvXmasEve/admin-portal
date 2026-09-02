---
title: Batch Studio inspect-first UI
date: 2026-09-02
summary: Replaced charge/DNG wizards with inspect-first Hub 1→2 pages; staff copy and docs-site updated.
---

# Batch Studio inspect-first UI

## What happened
Cooked `plans/260902-1653-batch-studio-inspect-first-ui`. Hub is a static 1→2 pipeline (Sinh phí & lệnh thu). ChargeGeneration and DngPush inspect on load: kỳ/loại phí Selects, debounce 400ms preview, commit Card under the table. HP/EGC result CTA is a no-query `financeRoutes.batchStudio.dng()` gated by `usePermission().can('create_finance_payments')`.

`useBatchStudio` dropped steps 1–4 for `inspect` | `result`. `runPreview` clears token/lines before POST. PHP preview/commit contracts untouched.

## Decision
No query on DNG CTA. No `can_dng` Inertia prop. Hide CTA after non-academic. Inline Card, not Dialog.

## Verification
- pest `FinanceOfficeCutoverTest`: 10 passed (81 assertions) via `vendor/bin/pest` (`php artisan test` exits 255 empty in this docker exec)
- eslint + prettier: clean on changed frontend files
- vue-tsc: OOM 137 in docker — not used as a pass
- `check-docs-freshness.sh`: red only on unrelated RetakeCourse course-delivery EN/KO/ZH

## Next steps
Commit when asked. Browser smoke of Hub → Charge HP preview → DNG inspect without commit fields.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
