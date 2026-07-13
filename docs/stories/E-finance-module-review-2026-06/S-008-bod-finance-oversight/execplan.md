# Exec Plan

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Goal

Create a read-only BOD Finance dashboard using reliable aggregate Finance data
and existing chart components.

## Scope

In scope:

- BOD-only read permission and menu entry.
- Aggregate query for semester/all-semester KPIs.
- `DB-13`, `DB-14`, `DB-15`, `DB-22`, and NT4 as prerequisites/risks for
  reliable aggregate math.
- Charts from C9 in the review.
- Drill-down links to read-only filtered lists when available.

Out of scope:

- Write actions.
- Student/lecturer portal changes.
- New chart library.
- Ledger/source-of-truth fixes from earlier stories.

## Risk Classification

Risk flags:

- Authorization.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- New permission-gated finance surface.
- Leadership-facing money aggregates.

Portal impact:

- None.

## Work Phases

1. Discovery: audit production role assignment for staff/admin/BOD and decide
   whether a new BOD role or permission mapping is needed.
2. Confirm whether DNG due items and invoice due items should appear in the
   same KPI, and how to avoid double-counting the same student obligation.
3. Confirm aggregate formulas are allowed to depend on story 002 canonical math,
   story 002 cache rebuild, and story 003 duplicate cleanup.
4. Add read-only query with SQL aggregation, not per-row PHP loops.
5. Add route/controller/page/menu gate.
6. Use existing `BarChart`, `LineChart`, and `DoughnutChart` components.
7. Add drill-down links in read-only mode.
8. Run permission, query, frontend, and browser smoke checks.

## Stop Conditions

Pause for human confirmation if:

- BOD role does not exist or has different production meaning.
- Duplicate invoice cleanup is incomplete.
- DNG-vs-invoice rail reconciliation is still undecided.
- Cached invoice columns are not rebuildable or are known stale.
- Leadership wants write actions on the dashboard.
- Aggregate formulas conflict with Finance accounting policy.
