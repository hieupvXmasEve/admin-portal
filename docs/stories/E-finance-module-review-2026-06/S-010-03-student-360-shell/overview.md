# Overview

## Current Behavior

Finance staff can inspect charges, invoices, DNG requests, payments, and audit
graphs through separate technical pages. The global search in Milestone 1 needs
a real landing destination for a student, but the full Student 360 is planned
for a later milestone.

## Target Behavior

Track Task 3 from the Milestone 1 plan as its own story: create a minimal,
read-only Student 360 route shell.

The shell shows:

- Student identity and lifecycle label.
- Four balances from existing Finance read models.
- Optional `focus=<type>:<id>` echo for later deep-scroll behavior.
- Deferred basic ledger using the existing audit graph/timeline builder.

## Affected Users

- Finance staff using global search to land on a student.
- Finance admins who need cross-campus visibility when authorized.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`

## Portal Impact

Portal impact: none.

This story is admin/staff web only.

## Non-Goals

- Do not build full Student 360 status cards, action menu, DNG stepper, or
  focus scroll/highlight.
- Do not recalculate money in Vue or controller code.
- Do not change money-write logic.
