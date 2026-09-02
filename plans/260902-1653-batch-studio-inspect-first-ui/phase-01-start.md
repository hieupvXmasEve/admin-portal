---
title: "Phase 1: Hub pipeline and inspect shell"
status: completed
priority: P1
effort: "2h"
dependencies: []
---

# Phase 1: Hub pipeline and inspect shell

## Overview

Hub becomes a static 1→2 pipeline. Shared batch UI loses the stepper so job pages can inspect without walking steps.

## Requirements

- Functional: Hub copy + numbered tiles; lock tiles without permission; no worklist fetch on Hub.
- Functional: `BatchWizard` is a layout (slot + sticky primary), not 4 steps.
- Functional: `useBatchStudio` exposes inspect/result, `runPreview`, `commit`; no `step` 1–4.
- Non-functional: reuse `@/components/ui`; no new npm deps.

## Architecture

- `Hub.vue`: two cards in a row with `1.` / `2.` and an arrow. Subtitle from copy table. CTA labels «Mở sinh phí» / «Xem lệnh thu». Keep `jobs.charge_generation` / `jobs.dng_push` gates.
- `BatchWizard.vue`: delete step `<nav>`. Keep Card slot + sticky footer (`summaryText`, primary, optional back). Props: drop `step`; keep `nextLabel` / `nextDisabled` / `canBack`.
- `useBatchStudio.ts`: `mode: 'inspect' | 'result'`. `runPreview` stays POST to existing preview URLs. On success stay inspect (do not advance a step). `commit` onSuccess → `result`. Changing setup keys used in preview must clear `previewToken` before the next preview. Keep `acknowledged` on `useForm` defaults.

Do not call preview from Hub. Do not change PHP. Do not change `financeRoutes.batchStudio.dng()` arity.
<!-- Updated: Validation Session 1 - no dng() params -->

## Related Code Files

- Modify: `resources/js/pages/Finance/BatchStudio/Hub.vue`
- Modify: `resources/js/components/finance/batch/BatchWizard.vue`
- Modify: `resources/js/composables/useBatchStudio.ts`
- Callers in phase 2: `ChargeGeneration.vue`, `DngPush.vue` (will not compile until phase 2 drops `wizard.step`)

## Implementation Steps

1. Flatten `BatchWizard` stepper. Footer primary still `@next`.
2. Replace `step` in `useBatchStudio` with `mode`. `runPreview` does not set step 2. Keep drift/token/selection behavior.
3. Rewrite Hub to pipeline layout + copy table strings. No API.
4. Leave job pages broken on `wizard.step` until phase 2 — same PR, sequential files.

## Todo

- [x] Hub 1→2 layout and copy
- [x] BatchWizard without steps
- [x] useBatchStudio inspect/result, token invalidate on scope change

## Success Criteria

- [x] Hub has no “wizard”, “token”, “Mở wizard”
- [x] BatchWizard has no Thiết lập/Xem trước/Xác nhận/Kết quả nav
- [x] Composable still issues/consumes preview the same way

## Risk Assessment

Phase 2 must land in the same change; Hub+composable alone leave job pages on `step`. Cook both phases before claiming Hub done.

## Next Steps

Phase 2 rewires ChargeGeneration and DngPush.
