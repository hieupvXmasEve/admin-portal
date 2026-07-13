# Exec Plan

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Goal

Create a reliable before-state and remove immediate operator hazards before any
deep Finance money or DNG rewrite.

## Scope

In scope:

- Invariant run and evidence capture.
- Failure sample export for INV-6 duplicate invoices and INV-11 payload hashes.
- UI/action safety gates that do not require schema or provider changes.
- `Settlement.vue` runtime safety repair.

Out of scope:

- DB migrations.
- New ledger model.
- DNG checksum/dedup implementation.
- BOD dashboard.

## Risk Classification

Risk flags:

- Audit/security.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Money-affecting staff actions.
- Audit-sensitive finance operations.

Portal impact:

- None.

## Work Phases

1. Verify the current audit command and run it against the intended snapshot.
2. Save structured evidence for all non-zero invariants.
3. Add impact preview/confirm guards for already-exposed dangerous actions.
4. Disable or replace the temporary manual allocation form with a safe blocked
   state and follow-up link if the full selector is not in scope.
5. Repair `Settlement.vue` layout/import/runtime errors.
6. Remove Finance console logging found in the review.
7. Run targeted backend/frontend checks and `git diff --check`.
8. Record Harness trace and attach invariant evidence.

## Stop Conditions

Pause for human confirmation if:

- The target dataset is not clearly local snapshot vs production.
- An audit query needs to mutate data to become runnable.
- A dangerous action cannot be guarded without changing its business behavior.
- Product wants to keep the temporary manual allocation form active.
