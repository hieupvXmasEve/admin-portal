# Phase 3b — Student Portal (Merchandise Store) — Completion Report

Date: 2026-08-02
Plan: [260801-0203-merchandise-store](../260801-0203-merchandise-store/index.md) · Phase [3](../260801-0203-merchandise-store/phase-3-redemption.md) §3b
Status: **DONE** (portal `pnpm typecheck` + `pnpm build` green). Runtime smoke pending (needs a live 3a backend).
Portal impact: student.

## Where the work lives
`/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` — separate Nuxt 4 repo, independent git history, git-ignored by Swinx. **Changes are UNCOMMITTED in the portal's own git** (per docs/portal-repos.md: Swinx and portal git states stay separate; the user commits in the portal repo). The Swinx branch was untouched except the contract doc.

## Delivered (portal, Nuxt 4 + vue-query + Pinia + shadcn-vue + vee-validate/zod)
- `shared/types/merchandise.ts` — types matching the backend contract exactly.
- `app/composables/merchandise.ts` — vue-query data layer (store list, detail, dashboard, orders list/detail, checkout, cancel) via `$api`.
- `app/stores/cart.ts` — Pinia persisted, **client-side-only** cart (no backend hold).
- `app/lib/merchandise.ts` — tabs, status/availability badges, gold/date formatters, `getVariantLabel` (client-built from color/size), image-path resolver.
- Pages under `app/pages/(protected)/merchandise/`: hub (Store / History / Gold-History tabs), product `[merchandise].vue` (variant picker, add-to-cart), `checkout.vue` (pickup/shipping + address, Idempotency-Key per attempt, stale-price/stock reconciliation + "Refresh cart"), `orders/[order].vue` (detail + timeline + cancel).
- `app/components/merchandise/{StoreTab,HistoryTab,GoldHistoryTab}.vue` — Gold History **reuses** the existing `useGoldWallet` (not rebuilt).
- Menu entry "Merchandise Store" added to `studentNavMain` only (D13 — student-only, no parent access); wired the gold-wallet "Redeem" button to `/merchandise`.

## Contract reconciliation (important — done during review)
The portal's first pass **inferred** store-catalog shapes (`image_url`, `variant.label`) because the contract doc had left them unspecified. Verified against the real 3a controllers and found drift that would break at runtime. Fixed:
- Canonical contract `docs/api/student/merchandise.md` now pins the exact store list/detail JSON (committed on Swinx branch, `8526464d`).
- Portal types + components reconciled: list item uses `image` (storage path, not `image_url`); detail uses `images[].path` + `variants[{id,color,size,stock_quantity}]` with **no** `variant.label` (label built client-side); order `items[].variant_label` kept (that snapshot IS real); cancellation object rebuilt to `{requested_at, reason, result, handled_at, note}`; price sourced from product `gold_price` (there is no per-variant price). typecheck + build re-verified green.

## Validation (in FE/student-nuxt)
- `pnpm typecheck` — exit 0, no `error TS`.
- `pnpm build` — exit 0 (one pre-existing unrelated warning).
- `pnpm lint` — whole-project run has a pre-existing `pnpm-workspace.yaml` gap (reproduces on clean stash); targeted lint of the new files is clean.

## Unresolved questions
1. **Runtime smoke** against a live 3a backend not done here — verify the portal end-to-end once the backend is deployed/running.
2. Portal changes are **uncommitted** in `FE/student-nuxt` — commit them in that repo (two unrelated pre-existing untracked files, `scholarshipReview.ts` + `scholarship-review/`, are NOT part of this work; do not co-commit).
3. Backend `image`/`images[].path` are storage paths — confirm the portal's image-base-URL resolution matches the deployed storage/S3 URL.
