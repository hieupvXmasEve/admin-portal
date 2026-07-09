# 01 - Code-owned obligation type registry convergence

Status: ready-for-human
Depends on: -
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0027-obligation-type-registry-is-code-owned.md, docs/adr/0028-finance-charge-is-materialized-ledger-read-model.md, docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Create the code-owned Finance obligation type registry described by ADR-0027 and make it the single source for per-type behavior facts. The registry must describe each debit obligation, credit entitlement, and discount entitlement type in the migration inventory, including financial effect, allowed source kinds, pricing strategy, DNG collection code, missing-fee inference behavior, installment support, cancellation policy, and permission. Existing DNG fee-code mapping and Fee Monitor expected-fee behavior should continue to produce the same staff-visible output while reading from, or being parity-tested against, the registry instead of drifting independently.

## Acceptance criteria

- [x] Every current `finance_charges.charge_type` value and every planned v2 entitlement type has one registry entry with its financial effect and behavior metadata.
- [x] DNG charge-type mapping and Fee Monitor expected-fee metadata no longer maintain independent per-type facts without a registry parity test.
- [x] A data or architecture test fails when a DB charge type has no registry entry.
- [x] Existing DNG and Fee Monitor behavior remains unchanged for the currently supported fee types.

## Blocked by

- None - can start immediately
