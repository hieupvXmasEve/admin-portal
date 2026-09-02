---
title: "Phase 3: Copy, menu, docs, tests"
status: completed
priority: P1
effort: "1.5h"
dependencies: [2]
---

# Phase 3: Copy, menu, docs, tests

## Overview

Replace jargon on menu, buckets, reasons staff see, and the finance-office guide. Update cutover string tests. Verify scoped.

## Requirements

- Functional: copy table in `plan.md` applied to Hub, both job pages, `menu-sidebar.ts`, `batchStudioDisplay.ts` bucket labels.
- Functional: docs-site VI first, then EN/KO/ZH. Pages document Hub + both job screens. `source:` lists `Hub.vue`, `ChargeGeneration.vue`, `DngPush.vue`, `menu-sidebar.ts`.
- Functional: `FinanceOfficeCutoverTest` expects new menu string `Lập yêu cầu thanh toán DNG` (not `Create DNG Payment Request`); DNG primary `Tạo lệnh thu` (not `Gửi yêu cầu sang DNG`). Still forbid `DNG Worklist` and `Đẩy DNG hàng loạt`.
- Non-functional: do not rename remaining English Finance menu items. Cockpit shortcuts keep routes; labels may say «Lập yêu cầu thanh toán DNG» / «Sinh học phí» if those strings are user-visible.

## Architecture

User-guide steps become:

1. Finance → Sinh phí → **Sinh phí & lệnh thu**
2. Chọn 1. Sinh phí hàng loạt hoặc 2. Lập yêu cầu thanh toán DNG
3. Chọn kỳ và loại phí — danh sách hiện ra
4. Kiểm tra số lượng; chỉ điền hạn/mô tả khi bấm tạo lệnh thu
5. Sau sinh học phí HP/EGC: **Xem / lập lệnh thu kỳ này** mở trang lệnh thu (chọn lại kỳ và loại phí trên trang đó)
<!-- Updated: Validation Session 1 - CTA has no prefill -->

Do not mention token, wizard, Batch Studio, preview_token, step.

Reason strings in `batchStudioDisplay.ts` that contain `charge`, `curriculum_version`, `TuitionPlanTerm`: rewrite to staff Vietnamese in the same file (table still shows them). Keep keys.

## Related Code Files

- Modify: `resources/js/constants/menu-sidebar.ts`
- Modify: `resources/js/utils/batchStudioDisplay.ts`
- Modify: `resources/js/components/finance/cockpit/PhaseShortcuts.vue` (visible labels only if still jargon)
- Modify: `tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php`
- Modify: `tests/Feature/Finance/Cockpit/CockpitOverviewTest.php` if it asserts “Batch Studio DNG wizard” only in test name — test name can stay; assertion strings if any
- Modify: `docs-site/src/content/docs/finance-office/index.md`
- Modify: `docs-site/src/content/docs/en/finance-office/index.md`
- Modify: `docs-site/src/content/docs/ko/finance-office/index.md`
- Modify: `docs-site/src/content/docs/zh/finance-office/index.md`

## Implementation Steps

1. Apply copy table. Grep staff-facing `Batch Studio`, `wizard`, `token`, `Mở wizard`, `Create DNG Payment Request` under `resources/js/pages/Finance/BatchStudio`, Hub, menu, PhaseShortcuts.
2. Bucket labels in `BATCH_BUCKET_META`.
3. Update cutover test literals.
4. Rewrite finance-office Batch Studio section (all 4 locales) + `source:` list.
5. Verify:

```bash
./scripts/dev.sh artisan test --compact --filter=FinanceOfficeCutoverTest
./scripts/dev.sh npm exec eslint -- resources/js/pages/Finance/BatchStudio resources/js/components/finance/batch resources/js/composables/useBatchStudio.ts resources/js/constants/menu-sidebar.ts resources/js/utils/batchStudioDisplay.ts
./scripts/dev.sh npm exec prettier --check -- resources/js/pages/Finance/BatchStudio resources/js/components/finance/batch/BatchWizard.vue resources/js/composables/useBatchStudio.ts
./scripts/check-docs-freshness.sh
```

Do not run full PHP suite.

## Todo

- [x] Menu + chrome + bucket copy
- [x] Cutover tests
- [x] docs-site 4 locales + source list + freshness

## Success Criteria

- [x] No user-visible “Batch Studio”, “wizard”, “preview token”, “Create DNG Payment Request”
- [x] Guide steps match inspect-first + 1→2 Hub
- [x] Cutover + eslint + prettier pass. `check-docs-freshness.sh` red only on unrelated `RetakeCourse/Index.vue` course-delivery EN/KO/ZH — finance-office pages not flagged.

## Risk Assessment

`FinanceOfficeCutoverTest` is a string freeze — update in the same change as menu or CI reds.

CockpitOverviewTest title mentions “Batch Studio DNG wizard” — code assertion is the route, not the Hub label; leave unless it reads Hub copy.
