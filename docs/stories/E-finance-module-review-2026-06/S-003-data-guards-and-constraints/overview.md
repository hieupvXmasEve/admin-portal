# Overview

> **Superseded rule notice (2026-07-12):** Story này dùng giả định cũ “một invoice mỗi student/kỳ”. Kiến trúc hiện tại cho phép nhiều invoice theo fee stream; không thêm unique `(student_id, semester_id)` và không coi multi-invoice là dirty data. `INV-6` đã retired; scope integrity chuyển sang `INV-17`.

## Current Behavior

Finance relies too heavily on application code for uniqueness, status validity,
FK integrity, and cache correctness. The review found known dirty data:
duplicate invoices by `student_id + semester_id` and duplicate DNG webhook
payload hashes, so constraints cannot be added safely before cleanup.

## Target Behavior

Dirty data is reviewed and remediated first. Then additive DB guards prevent the
same classes of double-billing, webhook duplication, orphan vouchers, invalid
statuses, invalid model enum values, and invalid money signs from recurring.

Where operation-level idempotency from the settlement story needs schema
support, this story adds only the minimal operation-token/storage guard. It must
not add natural-key unique constraints to append-only ledger rows.

## Affected Users

- Finance staff relying on invoice and payment integrity.
- Developers and operators running migrations.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

None unless a future implementation changes student or lecturer API contracts.

## Non-Goals

- Do not bulk-delete duplicate invoices.
- Do not add constraints before proving data is clean.
- Do not use DB-level uniqueness that conflicts with append-only ledger rows.
