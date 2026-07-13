# 03 — Shadow-check và chặn settlement bypass mới

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Đưa canonical reader vào shadow-only verification mà chưa thay consumer production. Shadow runner so raw ledger evidence với canonical output tại cùng snapshot/version, phân loại mismatch bằng một issue catalog thống nhất với Finance invariants, và lưu đủ evidence để staff/developer điều tra. Đồng thời thêm architecture guard chặn công thức balance hoặc direct money-table writer mới ngoài một legacy allowlist có chủ sở hữu.

## Acceptance criteria

- [x] Shadow comparison không dùng legacy consumer formula làm oracle.
- [x] Comparison đọc raw evidence và canonical result tại cùng settlement version/snapshot để tránh false mismatch do concurrent mutation.
- [x] Existing Finance invariants được map vào Settlement Position issue codes; balance checks bao gồm credit applications.
- [x] Runner báo explained/unexplained mismatch, target identity, issue code và version mà không thay đổi money data.
- [x] Architecture test fail khi thêm một local settlement formula hoặc direct money writer ngoài allowlist.
- [x] Legacy allowlist liệt kê owner/wave loại bỏ cho từng entry và không cho entry mới xuất hiện âm thầm.
- [x] Single/batch query count, p95 test baseline và representative query plan được ghi nhận cho các wave sau.

## Blocked by

- [01 — Đọc Settlement Position hiện tại cho một khoản phí](01-current-payable-settlement-position.md)
- [02 — Tổng hợp Settlement Position theo business scope](02-aggregate-batch-as-of-settlement-position.md)

## Comments

- 2026-07-11: `SettlementPositionShadowRunner` verifies raw evidence against the canonical position inside one transaction and returns target identity, captured time, snapshot version, raw evidence, issue mapping, and explained/unexplained result without a money write. The runner does not import or invoke `SettlementService`.
- 2026-07-11: `SettlementPositionIssueCatalog` maps `INV-1` through `INV-16` into stable Settlement Position issue codes. `INV-8` now includes `credit_applications` in both count and sample SQL.
- 2026-07-11: Baseline fixture (`SettlementPositionPerformanceBaselineTest`): single target and four-target batch are each at most 6 queries, with equal query count; batch p95 is at most 100 ms across 15 in-process runs. Representative current-cash `EXPLAIN` on local MySQL: `payment_applications` uses `payment_applications_invoice_line_id_index` with `type=range`, `rows=4`, `Extra=Using index condition`; joined `payments` uses `PRIMARY` with `type=eq_ref`, `rows=1`.
- 2026-07-11: `SettlementBypassAllowlist` is an explicit reviewed inventory pinned by the architecture test. Each legacy formula/direct writer records its Finance owner, removal wave, and reason; any new matching source path or allowlist entry fails tests until this reviewed inventory is deliberately changed.
- 2026-07-12: integrity catalog adds `INV-18` (`active_line_on_void_charge`) after rehearsal found line `1167` still active on void charge `1149`; scoped/global audit samples return the affected invoice id. Defer live-DNG detection now checks installment, request header, charge pivot, and reservation target evidence before any void.
