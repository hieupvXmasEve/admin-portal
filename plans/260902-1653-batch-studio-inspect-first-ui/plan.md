---
title: "Batch Studio inspect-first UI"
description: "Replace charge and DNG wizards with inspect-first pages: scope selects, preview before commit form, Hub as 1→2 pipeline, staff copy without jargon."
status: completed
priority: P1
effort: "6h"
branch: dev
tags: [feature, frontend, finance, docs]
blockedBy: []
blocks: []
created: 2026-09-02
---

# Batch Studio inspect-first UI

## Overview

Two jobs stay two pages. Hub teaches 1 sinh phí → 2 lập lệnh thu. Each job page: chọn kỳ/loại phí (Select) → xem danh sách/số lượng → form gửi chỉ khi bấm tạo. No stepper. No preview-token copy. Menu and on-screen text use staff language.

Portal impact: none.

## Brainstorm contract

- **Outcome:** Staff open Hub, see ordered 1→2. Open either job, see counts/table without filling commit fields. Change fee type in place. After HP/EGC sinh phí, a CTA opens the DNG page with **no query** (default HP + header kỳ). Staff pick kỳ/loại phí on DNG.
- **Constraints:** Keep preview token + recompute-on-commit. DNG commit still description/due_date/estimate_time, ack for replace or >50, max 100. Charge preview still needs non-academic fee_type+amount. Permissions unchanged. One `ListDngWorklistQuery` per DNG preview. No flash `student_ids`. No `batchStudio.dng()` query params. CTA uses `usePermission().can('create_finance_payments')`. Hide CTA after non-academic generation.
- **Non-goals:** Merge into one wizard. Auto-push DNG. Prefill created student_ids. 9 fee-type Hub counts. Count-only SQL. Uncap 200-line table. Fold full-set 4-bucket aggregates. Change commit/preview PHP contracts. Whole-sidebar English→Vietnamese rewrite.
- **Acceptance:** See Success Criteria.

## Cross-Plan Dependencies

| Relationship | Plan | Status |
|---|---|---|
| None | `260902-1525-remove-batch-studio-reminders` completed | ignore |
| Do not edit | `260818-2139-dng-push-over-collection-replacement` still in_progress on DNG reserve/coverage | this plan does not touch `AssembleBatchDngPreviewQuery` / commit token rules |

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Hub is a 1→2 pipeline, two separate pages | P1 |
| 2 | Charge + DNG: Select scope, preview first, commit form later | P1 |
| 3 | Staff copy/menu/docs-site without jargon | P1 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Hub pipeline and inspect shell](./phase-01-start.md) | Completed |
| 2 | [Inspect-first job pages](./phase-02-inspect-first-job-pages.md) | Completed |
| 3 | [Copy, menu, docs, tests](./phase-03-copy-menu-docs-tests.md) | Completed |

## Staff copy (chrome)

| Surface | From | To |
|---|---|---|
| Hub H1 + menu (Fee Generation) | Batch Studio | Sinh phí & lệnh thu |
| Hub subtitle | token / wizard | Sinh phí trước, lập lệnh thu sau. Lập lệnh thu dùng được riêng nếu đã có khoản phải thu. |
| Menu (Collections) | Create DNG Payment Request | Lập yêu cầu thanh toán DNG |
| Tile 1 CTA | Mở wizard | Mở sinh phí |
| Tile 2 CTA | Mở wizard | Xem lệnh thu |
| Charge primary | Chạy sinh phí | Sinh phí |
| DNG primary | Gửi yêu cầu sang DNG | Tạo lệnh thu |
| Charge result CTA | (none) | Xem / lập lệnh thu kỳ này |
| Bucket create | Tạo mới | Cần tạo |
| Bucket update | Cập nhật-gộp | Cần thay |
| Bucket skip | Bỏ qua | Không cần |
| Bucket warning | Cảnh báo | Cần kiểm tra |

Route names `finance.batch-studio.*` stay. Head `<title>` matches H1.

## Architecture

```
Hub (static, no worklist query)
  ├─ 1. Sinh phí hàng loạt → ChargeGeneration
  └─ 2. Lập yêu cầu thanh toán DNG → DngPush

ChargeGeneration / DngPush
  [Kỳ Select] [Loại phí Select]  (+ non-academic amount if needed)
  PreviewDiffTable (auto preview, debounce)
  Commit Card under table (same page, not Dialog)
  Result + DNG CTA after HP/EGC only (plain `dng()` link)
```

`useBatchStudio`: drop step 1–4. Modes `inspect` | `result`. `runPreview` on scope change. Commit panel is local UI, not a wizard step.

## Success Criteria

- [x] Hub shows numbered 1→2, no live counts, no token/wizard copy
- [x] Charge page loads preview for HP+kỳ without a 4-step wizard
- [x] DNG page loads preview without description/due_date/estimate_time
- [x] Changing fee type Select re-previews in place; old token discarded
- [x] DNG create Card still validates commit fields; ack still required
- [x] Charge result CTA (HP/EGC only) → `financeRoutes.batchStudio.dng()` with no query; hidden without `create_finance_payments`; hidden after non-academic
- [x] Menu + page chrome match copy table; cutover tests updated
- [x] docs-site VI/EN/KO/ZH finance-office updated; `source:` includes Hub, ChargeGeneration, DngPush, menu-sidebar
- [x] No PHP preview/commit contract change
- [x] Targeted tests + file-scoped eslint/prettier

## Risks

| Risk | Signal | Response |
|---|---|---|
| Auto-preview hammer on Select | Slow/timeout | Debounce 400ms; skeleton; do not parallelize fee types |
| Truncated DNG table vs “how many” | Staff trust windowed buckets | Show `summary.total_students`; alert truncated; buckets = loaded window |
| Sidebar IA English titles vs this Vietnamese chrome | Cutover test / IA plan | User override for these two items only |


## Validation Log

### Session 1 — 2026-09-02
**Trigger:** `/ak:plan validate` after fast plan
**Questions asked:** 4

#### Questions & Answers

1. **[Architecture]** `financeRoutes.batchStudio.dng()` hiện không nhận query. CTA đưa semester_id + dng_fee_type thế nào?
   - Options: Thêm params vào `dng()` giống `charges()` | Gọi `route()` trực tiếp | Không gửi query
   - **Answer:** Không gửi query
   - **Rationale:** Helper `dng: () => route(...)` has no params (`routes.ts:520`). User chose a plain link over extending Ziggy helpers.

2. **[Assumptions]** Ẩn CTA khi không có quyền tạo lệnh thu?
   - Options: Client `usePermission().can('create_finance_payments')` | Thêm `can_dng` Inertia prop
   - **Answer:** Client helper only
   - **Rationale:** `Charges/Index.vue` already uses this pattern. Avoid PHP.

3. **[Tradeoffs]** Sau sinh phí phi học vụ, CTA map loại phí ra sao?
   - Options: CTA chỉ HP/EGC → ẩn phi học vụ | Hardcode client map | Luôn HP
   - **Answer:** CTA chỉ khi HP/EGC; ẩn với phi học vụ
   - **Rationale:** No `fromChargeType` on the client; do not invent a map.

4. **[Architecture]** Form gửi hiện thế nào?
   - Options: Card ngay dưới bảng | Dialog/drawer
   - **Answer:** Card ngay dưới bảng trên cùng trang
   - **Rationale:** Matches inspect-first, no extra overlay.

#### Confirmed Decisions
- CTA: `financeRoutes.batchStudio.dng()` no query
- CTA permission: `usePermission().can('create_finance_payments')`
- CTA visibility: major/egc only
- Commit UI: inline Card under table, not Dialog

#### Action Items
- [x] Drop query-string CTA and `can_dng` PHP from contract
- [x] Propagate to phase 2

#### Impact on Phases
- Phase 1: no `routes.ts` `dng()` signature change
- Phase 2: CTA + Card + client permission; no BatchStudioController prop
- Phase 3: docs CTA has no “kỳ này đã chọn sẵn”

### Verification Results
- **Tier:** Standard (3 phases, Fact Checker + Contract Verifier)
- **Claims checked:** 18
- **Verified:** 16 | **Failed:** 1 | **Unverified:** 1
- **Tier:** Standard

#### Failures
1. [Contract Verifier] Plan claimed `financeRoutes.batchStudio.dng({ semester_id, dng_fee_type })` — `dng()` takes no params (`resources/js/utils/routes.ts:520`). **Resolved by validation Q1:** no query.

#### Unverified
1. Non-academic charge_type → DNG code on client — **Resolved by Q3:** do not map; hide CTA.

Verified (sample): Hub `jobs.*` (`Hub.vue`); `useBatchStudio` `step` 1–4 (2 callers: ChargeGeneration, DngPush); `PreviewBatchDngRequest` no commit fields; `PreviewBatchChargesRequest` non-academic amount; `validateSetup` before DNG preview (`DngPush.vue:129-133`); cutover strings; `BATCH_BUCKET_META`; docs-site `source:` Hub only; `usePermission().can`.

### Whole-Plan Consistency Sweep
- Files reread: `plan.md`, `phase-01-start.md`, `phase-02-inspect-first-job-pages.md`, `phase-03-copy-menu-docs-tests.md`
- Decision deltas checked: 4 (no-query CTA, client can, hide non-academic CTA, inline Card)
- Reconciled stale references: query CTA, `can_dng` PHP, client fee-type map, Dialog, duplicate do-not-modify
- Unresolved contradictions: 0
<!-- slug: batch-studio-inspect-first-ui -->
