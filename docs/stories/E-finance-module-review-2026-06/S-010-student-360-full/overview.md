# Overview

## Current Behavior

Milestone 1 created the Finance shell and a minimal Student 360 route. That
route gives staff a valid destination for global search, but the first shell is
not the full operator surface.

Before this milestone, the full Student 360 work was split across seven
task-sized story packets. Those packets are now historical notes. This is the
single canonical Milestone 2 story.

## Target Behavior

Grow the Milestone 1 Student 360 shell into the full operator surface while
keeping the same URL and route:

- Four status-card groups for balance, DNG, installments, and exceptions.
- Grouped ledger plus the existing timeline lens.
- DNG state stepper.
- Permission-aware action menu.
- Record-payment, allocation-preview, and reviewed-DNG-cancel drawers.
- Installment retry push entry point.
- `focus=<type>:<id>` scroll and highlight behavior.

The milestone must reuse existing Finance actions/services. It must not rewrite
money math.

## Historical Task Mapping

| Former task packet                    | Now owned by this story                                          |
| ------------------------------------- | ---------------------------------------------------------------- |
| `S-010-08-student-360-status-ledger/` | Status cards, grouped ledger, action flags, unapplied payment id |
| `S-010-09-manual-allocation-preview/` | Read-only allocation preview endpoint                            |
| `S-010-10-record-payment-adapter/`    | Thin record-payment adapter                                      |
| `S-010-11-reviewed-dng-cancel/`       | Cancel impact endpoint and reviewed DNG cancel                   |
| `S-010-12-student-360-display/`       | Status cards, stepper, and ledger UI                             |
| `S-010-13-student-360-actions/`       | Action menu, drawers, installment push, focus highlight          |
| `S-010-14-student-360-m2-evidence/`   | Final validation and invariant evidence                          |

## Affected Users

- Finance staff who inspect and act on one student's finance state.
- Finance leads/admins who need safer manual intervention points.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story targets admin/staff Inertia UI and web routes. It does not change
`/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- Do not change the Student 360 URL created in Milestone 1.
- Do not rewrite money calculations.
- Do not replace `PaymentService`, allocation actions, DNG actions, or invariant
  checks.
- Do not add new ledger tables or campaign tables.
- Do not build the Cockpit or Batch Studio.
