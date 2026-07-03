# Action Cards Only For Real Work

Status: ready-for-human (implemented; focused checks pass, broad checks have environment/out-of-scope failures)

## Parent

.scratch/student-finance-overview/PRD.md

## What to build

Refine the operational cards on **Học phí sinh viên** so they only surface work that is actually actionable. Rename the DNG card to **DNG cần xử lý** and stop treating paid DNG as a current DNG action. Paid DNG should be explained through **Còn dư** and **Các khoản đã nộp** when relevant.

The installment card should only show schedules tied to real current **Còn phải thu**. Pending installments on already-paid, voided, or otherwise non-collectible obligations should not appear as next actions.

## Acceptance criteria

- [ ] The DNG card label is **DNG cần xử lý**.
- [ ] Paid DNG alone does not make the DNG card look actionable.
- [ ] Pending, pushed, failed, or review-needed DNG still appears in **DNG cần xử lý** when it genuinely needs staff attention.
- [ ] DNG errors remain visible and actionable according to existing permissions.
- [ ] Paid DNG that leaves **Còn dư** is represented through the KPI surplus message and **Các khoản đã nộp**, not as current DNG work.
- [ ] The installment card only appears when there is an installment tied to real current **Còn phải thu**.
- [ ] Pending installments on paid invoices do not appear as next installment actions.
- [ ] Pending/cancelled installments on voided charges do not appear as active work.
- [ ] Feature tests cover paid-DNG-not-actionable, live-DNG-actionable, paid-invoice-installment-hidden, and voided-charge-installment-hidden cases.
- [ ] Frontend type/lint checks pass for changed Vue/TypeScript files.

## Blocked by

- .scratch/student-finance-overview/issues/01-first-viewport-student-finance-kpis.md

## Comments

- 2026-07-03: Implemented in `GetStudent360StatusCardsQuery` and `StatusCards.vue`.
  - `DNG cần xử lý` now only surfaces DNG requests in provider-work statuses: `pending`, `pushed_to_dng`, or `failed`.
  - Paid DNG stays represented through `Còn dư` / `Các khoản đã nộp` instead of the actionable DNG card.
  - Installment cards now use `SettlementService::getOutstandingLinesForStudent()` so stale schedules on fully-paid or voided obligations do not appear as current work.
- Validation:
  - Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Student360/StudentOverviewShellTest.php`
  - Passed: `./scripts/dev.sh composer exec pint -- --format agent app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php tests/Feature/Finance/Student360/StudentOverviewShellTest.php app/Providers/AppServiceProvider.php`
  - Passed: `./scripts/dev.sh npm exec -- prettier --check resources/js/components/finance/student360/StatusCards.vue`
  - Passed: `./scripts/dev.sh npm exec -- eslint resources/js/components/finance/student360/StatusCards.vue`
  - Blocked: `./scripts/dev.sh npm run type-check` was killed with exit 137, including with `NODE_OPTIONS=--max-old-space-size=1024`.
  - Broad-suite note: `./scripts/dev.sh test` / `./scripts/dev.sh artisan test --compact` exited 255 with no output; `./scripts/dev.sh artisan test --compact tests/Feature/Finance` ran but had broad-suite failures outside this slice.
