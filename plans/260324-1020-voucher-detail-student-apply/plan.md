---
title: "Voucher Detail Student Apply Flow"
description: "Add in-page student voucher application on voucher detail and align redeem writes with voucher_applications."
status: in_progress
priority: P1
effort: 12h
branch: dev
tags: [feature, frontend, backend, finance, vouchers]
blockedBy: []
blocks: []
created: 2026-03-24
---

# Voucher Detail Student Apply Plan

## Overview
Add one operator flow directly inside voucher detail so staff can apply the current voucher to one student without leaving the page. Keep scope tight: no new standalone page, no voucher CRUD redesign, no bulk apply.

## Scope Challenge
- Existing code: voucher detail page already exists; `vouchers.redeem` route exists; `StudentCombobox` and student search API already exist; Finance now reads `voucher_applications`.
- Minimum changes: reuse voucher detail page, reuse current route name if possible, reuse current-campus student search, keep semester defaulted from active semester instead of building a multi-semester workflow.
- Complexity: medium-high because current write path is legacy (`voucher_redemptions`) while billing path is canonical (`voucher_applications`).
- Selected mode: HOLD SCOPE.

## Current-State Findings
- `resources/js/pages/Vouchers/Show.vue` only shows definition data + legacy redemption history. No UI to apply voucher.
- `app/Http/Controllers/VoucherController::redeem()` still validates inline and writes through `VoucherService::redeemVoucher()`.
- `app/Services/VoucherService` still writes `voucher_redemptions`; delete guard also only checks `redemptions()`.
- Finance preview/generate flows read `student.voucherApplications`, not `voucher_redemptions`.
- `voucher_applications` requires `semester_id`, but current redeem contract does not provide or derive semester.
- `VoucherApplication` has no `invoice()` relation, so voucher detail cannot render canonical usage history cleanly yet.
- Voucher schema already has `max_discount_amount` and `max_uses_per_student`, but current redeem flow does not honor them.
- Permission config has `view/create/edit/delete_voucher`; `vouchers.redeem` currently has no explicit `can:*` middleware.

## Target Behavior
1. User opens voucher detail page.
2. Page shows an `Apply Voucher` card above usage history.
3. User searches and selects one student via existing campus-scoped student search.
4. Page shows the current active semester that will receive the voucher.
5. User submits.
6. Backend validates voucher state, duplicate usage, active semester presence, and usage limits.
7. Backend creates canonical `voucher_applications` record with computed `discount_amount`, `applied_by_user_id`, `semester_id`, and `status=applied`.
8. Page reloads with success flash and updated usage history for the voucher.
9. Voucher detail history reflects canonical applications, including student, semester, amount, status, invoice, and applied timestamp.

## Scope
In scope:
- Add apply-to-student form inside `Vouchers/Show`.
- Align manual redeem write path to `voucher_applications`.
- Compute `discount_amount` for manual apply in a reusable way.
- Show canonical usage history on voucher detail.
- Harden permission + delete guard around voucher application usage.

Out of scope:
- New student-page entry point for voucher apply.
- Bulk apply/import redesign.
- Full rule engine for `is_stackable`.
- CRUD UI for `max_discount_amount`, `max_uses_per_student`, `is_stackable`.
- Reworking old invoice-discount legacy paths beyond compatibility notes.

## Assumptions
- Manual apply targets the current active semester only in this phase.
- Historic legacy data can be normalized via existing `php artisan migrate:vouchers` rollout step.
- Current-campus student search is the correct operator boundary for this UI.

## Context Links
- [Voucher routes](/Users/hunt2412/hieupvdev/project/swinx/routes/web/vouchers.php)
- [Voucher controller](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php)
- [Voucher service](/Users/hunt2412/hieupvdev/project/swinx/app/Services/VoucherService.php)
- [Voucher detail UI](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue)
- [Student combobox](/Users/hunt2412/hieupvdev/project/swinx/resources/js/components/StudentCombobox.vue)
- [Student search API](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Web/Admin/StudentController.php)
- [Voucher application schema](/Users/hunt2412/hieupvdev/project/swinx/database/migrations/2026_01_19_133749_refactor_vouchers_table.php)
- [Finance preview query](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Operations/PreviewChargeGenerationQuery.php)
- [Finance generate action](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php)
- [Code standards](/Users/hunt2412/hieupvdev/project/swinx/docs/code-standards.md)
- [Design guidelines](/Users/hunt2412/hieupvdev/project/swinx/docs/design-guidelines.md)

## Phase Status / Progress
| Phase | Focus | Status | Effort | Link |
|---|---|---|---:|---|
| 01 | Canonical redeem contract + guardrails | completed | 4h | [phase-01](/Users/hunt2412/hieupvdev/project/swinx/plans/260324-1020-voucher-detail-student-apply/phase-01-canonical-redeem-contract-and-guardrails.md) |
| 02 | Detail payload + usage history alignment | completed | 3h | [phase-02](/Users/hunt2412/hieupvdev/project/swinx/plans/260324-1020-voucher-detail-student-apply/phase-02-detail-payload-and-history-alignment.md) |
| 03 | Voucher detail UI apply flow | completed | 3h | [phase-03](/Users/hunt2412/hieupvdev/project/swinx/plans/260324-1020-voucher-detail-student-apply/phase-03-voucher-detail-ui-apply-flow.md) |
| 04 | Validation + rollout compatibility | in_progress | 2h | [phase-04](/Users/hunt2412/hieupvdev/project/swinx/plans/260324-1020-voucher-detail-student-apply/phase-04-validation-and-rollout.md) |

## Cross-Plan Dependencies
No direct overlap detected with unfinished plans in `plans/`. `blockedBy` and `blocks` stay empty.

## Execution Notes
- Keep `voucher_applications` as canonical write/read model for new usage.
- Prefer one shared voucher amount resolver instead of duplicating `%` discount math in controller/service/query/action.
- Keep route/API changes backward-compatible where cheap; UI should not need a new top-level route.
- Use FormRequest for redeem/apply validation to align with current backend standards.

## Success Criteria
- Staff can apply voucher to a student from voucher detail.
- New applications are persisted to `voucher_applications`, not `voucher_redemptions`.
- Percentage and fixed-amount vouchers both produce correct `discount_amount`.
- Voucher detail shows canonical usage history after apply.
- Duplicate apply for same student + semester + voucher is blocked cleanly.
- Delete guard rejects deleting a voucher with canonical applications.
- Route auth is explicit and consistent with permission rules.

## Rollout Notes
- If production/dev still has legacy `voucher_redemptions`, run `php artisan migrate:vouchers` before relying on detail history/counts.
- If a new permission is introduced, follow with `php artisan permissions:sync`.

## Implementation Update
- Manual apply now writes canonical `voucher_applications` with active-semester resolution, duplicate/use-cap checks, shared discount calculation, and explicit `can:edit_voucher` protection on `vouchers.redeem`.
- Voucher detail now includes an inline `Apply Voucher` card, reuses `StudentCombobox`, and renders canonical usage history instead of legacy redemption history.
- Finance preview/generate flows now fall back to the shared voucher amount resolver when old application rows are missing `discount_amount`, reducing drift during mixed-data rollout.
- Validation phase is still open because this environment has no `php` binary and repo-wide `npm run type-check` is already failing on unrelated pre-existing TypeScript errors.

## Unresolved Questions
- None. Plan assumes current active semester is the manual-apply target for this phase.
