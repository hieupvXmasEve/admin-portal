# 02 - Tuition legacy backfill and DNG auto-create removal

Status: done
Depends on: 01
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Backfill existing `tuition_term` rows into Finance obligations and remove tuition DNG auto-create behavior for the migrated group. The wave closes only when the backfilled settlement position reconciles with the old computed balance or every exception has an explicit accepted reason.

## Acceptance criteria

- [x] Active and voided tuition rows are linked to Finance obligations with legacy provenance and no duplicate rows on rerun.
- [x] Reconciliation compares every backfilled row's derived settlement to the old computed balance and reports exceptions.
- [x] DNG tuition push reads existing payables and blocks missing obligations rather than creating charges.
- [x] Fee Monitor, reports, and exports continue to read the materialized read model without semantic drift.
- [x] Tests cover backfill idempotency, reconciliation hard-gate behavior, and DNG missing-obligation blocking.

## Blocked by

- `.scratch/finance-obligation-v2-wave-3-tuition-term/issues/01-tuition-term-intake-installments.md`
