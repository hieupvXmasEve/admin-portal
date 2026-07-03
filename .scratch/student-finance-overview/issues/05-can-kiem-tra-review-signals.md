# `Cần kiểm tra` In-Page Review Signals

Status: ready-for-human (implemented; focused checks pass, broad checks have environment/out-of-scope failures)

## Parent

.scratch/student-finance-overview/PRD.md

## What to build

Add a compact **Cần kiểm tra** box under the KPI area on **Học phí sinh viên**. It should surface non-blocking review signals that help staff understand confusing finance states without making the page imply that the student necessarily owes more money.

The first version should cover four signal families: **Còn dư**, paid DNG whose related fees were later voided, installment state that no longer matches invoice state, and invoice cached numbers that differ from recalculated numbers. Each signal should link or scroll to the relevant section in the same page instead of sending staff to another page.

## Acceptance criteria

- [ ] **Cần kiểm tra** appears under the KPI/surplus area only when at least one review signal exists.
- [ ] The box is visually non-blocking and does not imply the student necessarily has more money to pay.
- [ ] A **Còn dư** signal appears when completed paid money is not matched to any current fee obligation.
- [ ] A DNG-paid-but-fee-voided signal appears when a paid DNG/payment is tied to fee lines that are now voided.
- [ ] An installment mismatch signal appears when installment state suggests pending work but the related invoice/fee is already paid or no longer collectible.
- [ ] An invoice cache drift signal appears when cached invoice totals differ from recalculated settlement totals.
- [ ] Each signal has an in-page target to the relevant payment, sổ cái semester/invoice/line, or action card section.
- [ ] The signals do not require opening Audit Workspace, payment detail, DNG detail, or invoice detail for ordinary explanation.
- [ ] Feature tests assert all four first-version signals and absence of signals when data is consistent.
- [ ] Frontend type/lint checks pass for changed Vue/TypeScript files.

## Blocked by

- .scratch/student-finance-overview/issues/02-payment-history-cac-khoan-da-nop.md
- .scratch/student-finance-overview/issues/03-complete-so-cai-with-voided-lines.md
- .scratch/student-finance-overview/issues/04-action-cards-only-for-real-work.md

## Comments

- 2026-07-03: Implemented in `GetStudentFinanceReviewSignalsQuery`, `FinanceStudentOverviewController`, `Show.vue`, and `LedgerLens.vue`.
  - Adds `review_signals` Inertia prop for four first-version families: `Còn dư`, paid DNG with voided related fee lines, stale installment state, and invoice cache drift.
  - Renders a compact non-blocking `Cần kiểm tra` box below the KPI/surplus area only when signals exist.
  - Each signal carries an in-page target to payment history, ledger, or the installment action card section.
- Validation:
  - Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Student360/StudentOverviewShellTest.php`
  - Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent`
  - Passed: `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Finance/Student360/Show.vue resources/js/components/finance/student360/LedgerLens.vue resources/js/types/finance.ts`
  - Passed: `./scripts/dev.sh npm exec -- eslint resources/js/pages/Finance/Student360/Show.vue resources/js/components/finance/student360/LedgerLens.vue resources/js/types/finance.ts`
  - Blocked: `./scripts/dev.sh npm run type-check` and `NODE_OPTIONS=--max-old-space-size=768 ./scripts/dev.sh npm run type-check` were killed with exit 137.
  - Broad-suite note: `./scripts/dev.sh test` exited 255 with no output.
  - Broad-lint note: `./scripts/dev.sh npm run lint` exited 1 on existing/generated files outside this slice, including ignored portal `.nuxt` files and unrelated legacy frontend files.
