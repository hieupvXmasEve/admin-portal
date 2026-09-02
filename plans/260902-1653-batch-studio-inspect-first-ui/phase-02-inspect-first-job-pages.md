---
title: "Phase 2: Inspect-first job pages"
status: completed
priority: P1
effort: "3h"
dependencies: [1]
---

# Phase 2: Inspect-first job pages

## Overview

Charge and DNG pages: scope Selects always visible, preview on load/change, commit fields in a Card under the table after primary click. After HP/EGC success, a no-query link to the DNG page.

## Requirements

- Functional: DNG preview without description / due_date / estimate_time (`PreviewBatchDngRequest` already allows this; UI must stop `validateSetup()` before preview).
- Functional: Charge preview for major/egc needs only fee_category + semester. Non-academic still requires fee_type + amount on the scope bar before preview (`PreviewBatchChargesRequest`).
- Functional: Fee type and semester are Selects. Change → debounce 400ms `runPreview`. Skeleton while `previewing`. Do not show stale counts (clear lines or keep previous with muted overlay — prefer clear + skeleton).
- Functional: Primary «Sinh phí» / «Tạo lệnh thu» reveals a Card under the table (same page, not Dialog, not a step). Card holds DNG commit fields + ack; charge holds ack if >50. EGC block overrides stay on the table.
- Functional: After HP/EGC charge success, sibling Link «Xem / lập lệnh thu kỳ này» → `financeRoutes.batchStudio.dng()` with **no query**. Hide if `!usePermission().can('create_finance_payments')`. Hide when `fee_category === 'non_academic'`. Do not map fee types on the client.
- Non-functional: one preview per selected fee type. Buckets remain loaded-window; show `summary.total_students` and existing truncated alert.
- Non-functional: keep token consume, skip/warning block, replacement ack, max 100.

## Architecture

```
inspect
  scope bar (Selects)
  PreviewDiffTable
  sticky: counts summary + primary
confirm Card (v-if, under table, not Dialog)
  DNG: description, DatePicker due_date, estimate_time, ack
  Charge: ack if >50
  submit → existing commit URLs
result
  BatchResultPanel
  Charge HP/EGC only: Link to `dng()` (no params)
```

Semester: Charge already has Select. DNG currently read-only from `useFinanceSemester` — add the same semester Select as Charge so staff do not leave the page.

Do not pass query or `student_ids` on the CTA. Do not widen `Inertia::flash('batch_result')`. Do not add `can_dng` to the controller. Do not change `batchStudio.dng()` signature.

CTA permission: `usePermission().can('create_finance_payments')` (`Charges/Index.vue` pattern).
<!-- Updated: Validation Session 1 - no-query CTA, client can, hide non-academic, inline Card -->

## Related Code Files

- Modify: `resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue`
- Modify: `resources/js/pages/Finance/BatchStudio/DngPush.vue`
- Modify: `resources/js/components/finance/batch/BatchResultPanel.vue` only if a slot is easier than a sibling Link — prefer sibling in ChargeGeneration, no panel API
- Do not modify: `BatchStudioController.php`, `AssembleBatchDngPreviewQuery.php`, `PreviewBatchDngRequest.php`, `CommitBatchDngRequest.php`, token service, `resources/js/utils/routes.ts` `dng()` arity

## Implementation Steps

1. ChargeGeneration: remove `wizard.step` branches. Scope card always on. `watch` fee_category + semester (+ non-academic fields) → debounce preview. Non-academic: disable preview until amount+type set.
2. Commit: primary reveals Card under table; `validateSetup` only for DNG Card; `wizard.commit()`.
3. DngPush: same layout. `validateSetup` only when opening/submitting create Card. Fee type Select on inspect bar. Semester Select.
4. Result CTA on ChargeGeneration: `Link` to `financeRoutes.batchStudio.dng()` when major/egc and `can('create_finance_payments')`.
5. Truncation: keep alert; footer/summary include total from `wizard.summary`.

## Todo

- [x] Charge inspect-first + Select + commit panel + DNG CTA
- [x] DNG inspect-first + Select + commit panel
- [x] Debounce preview; no stale counts; token refresh on fee type change

## Success Criteria

- [x] Open DNG: counts/table without filling 3 commit fields
- [x] Change HP → BHYT: table updates in place
- [x] Create DNG still requires the 3 fields + ack when replace/>50
- [x] Charge HP preview without walking steps; non-academic still needs amount
- [x] CTA is a no-query `dng()` link; absent for non-academic and without payment permission

## Risk Assessment

Auto-preview = same cost as today's «Xem trước». If Select-spam: debounce. If worklist slow: skeleton, do not invent a count API.

## Next Steps

Phase 3 copy, menu, docs-site, cutover tests.
