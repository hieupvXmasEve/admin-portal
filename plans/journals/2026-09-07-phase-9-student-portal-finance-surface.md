---
title: Phase 9 student portal finance surface
date: 2026-09-07
summary: Portal consumes money_item_status and installment context; snake_case fee labels removed.
---

# Phase 9 student portal finance surface

## What happened
Cooked Phase 9 of `plans/260904-1114-finance-flow-redesign` in nested `FE/student-nuxt` (separate git). Backend payload from Phases 4/8 already had `learner_label`, `money_item_status`, `installment_no`/`installments_total`/`due_date`. Portal was still title-casing snake_case and hardcoding `retake_fee`.

## Decision
Read backend keys; do not recompute money or remap status. Missing labels → `Khoản thu khác` / `Không rõ`. Foxpay copy is partner-gateway language, not school installments. `PTL`/`KHAC` stay as DNG codes, hidden from students. Preserved pre-existing `isInstallmentPaymentUiEnabled = true`. Did not touch `deploy-dev.sh`.

## Verification
`titleCaseFromSnakeCase` = 0 in `app/`. `'retake_fee'` = 0 in `app/`. File-scoped eslint green. `pnpm typecheck` and `pnpm build` green. Full `pnpm lint` still fails pre-existing missing `pnpm-workspace.yaml`.

## Next steps
Commit/PR in `FE/student-nuxt` separately from Swinx. Confirm deploy window (`scripts/deploy-fe.sh`). Plan-wide PHP Finance regression still open (Phase 8 Pest `|` harness).

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
