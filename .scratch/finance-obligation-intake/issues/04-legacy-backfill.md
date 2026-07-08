# 04 — Legacy backfill (debit retake/resit only)

**Status:** ready-for-agent
**Depends on:** 01 (debit intake spine)
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

## Goal

Existing retake/resit charges fit the new model without re-billing, and without misclassifying credit/discount data.

## Seam

New idempotent backfill artisan command (existing command-test seam).

## Scope

- Existing **active** `retake_fee`/`exam_resit_fee` charges → `accepted` `FinanceObligation` linked to the existing charge. Synthesize the source triple from current `source_type` → `source_kind` and `source_id` → `source_ref`.
- Existing **voided** such charges → `FinanceObligation` with `lifecycle_status = voided` (clean; no "committed" contortion).
- **Do not touch** scholarship/voucher/defer-credit `charge_type`s — those are future Credit/Discount aggregates.
- Legacy `course_retake_registrations.retake_fee` / `exam_resit_attempts.fee_amount` may seed historical `priced_amount` with provenance `legacy_backfill` only — never a pricing source for new obligations.
- Command is idempotent and re-runnable (keyed by the obligation quad); reports counts + any mismatches, creates no charges.

## Acceptance

- Active charge → `accepted` obligation linked to it; voided charge → `voided` obligation.
- Re-running the command creates no duplicates.
- No scholarship/voucher/defer-credit charge produces an obligation.
- Backfilled amounts carry `legacy_backfill` provenance.

## Testing

Command feature test: seed active + voided + credit/discount charges → run → assert obligation lifecycle + untouched credit/discount + idempotent re-run. Prior art: `ExportDuplicateFinanceDataCommandTest`, `tests/Feature/Finance/Cutover/`.

## Out of scope

New-intake write path (01/02), settlement reader/DNG (03), arch enforcement + dropping `active_source_key` (05).
