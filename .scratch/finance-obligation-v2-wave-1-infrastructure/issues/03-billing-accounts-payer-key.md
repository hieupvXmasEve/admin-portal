# 03 - Billing accounts payer key for new aggregates

Status: ready-for-human
Depends on: -
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0029-billing-account-is-payer-key-for-new-finance-aggregates.md
Portal impact: none

## What to build

Introduce Billing Account as the Finance-owned payer key for new aggregate truth. Every Student should have exactly one billing account, existing Finance obligations should be backfilled with a billing account, and new intake should resolve the payer inside Finance instead of requiring source systems to pass billing-account identity. The legacy ledger remains student-keyed, so materialization must still project accepted student obligations into the existing charge and invoice-line shape.

## Acceptance criteria

- [x] Billing accounts exist for all current Students and are auto-provisioned for new Students.
- [x] Existing Finance obligations are backfilled to a billing account using current charge/source evidence.
- [x] New debit intake stores the billing account on the obligation while the materialized charge remains compatible with the student-keyed ledger.
- [x] Source systems do not pass or store Finance billing-account identifiers.
- [x] Tests cover student auto-provisioning, obligation backfill, and materialization through the legacy ledger.

## Blocked by

- None - can start immediately
