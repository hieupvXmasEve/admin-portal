---
id: ADR-0027
title: "Obligation type registry is code-owned; only pricing rules live in the database"
status: accepted
date: 2026-07-09
owner: "Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Obligation type registry is code-owned; only pricing rules live in the database

## Context

The Finance Obligation v2 migration needs one authoritative definition per
obligation type: financial effect (debit/credit/discount), allowed source
kinds, pricing strategy, DNG fee code mapping, mandatory/missing-inference
behaviour, installment support, cancel policy, and permission. Today these
facts are scattered across `FinanceCharge` constants,
`FeeMonitorExpectedFeeCatalog`, and `DngFeeTypeOptions`. The question was
whether this registry should be a database table (staff-configurable) or code.

## Decision

The obligation type registry is **code-owned**: one declarative PHP registry
in the Finance module, one entry per obligation type, covering all
per-type behaviour wiring. Only **pricing rules** — amounts, currency,
effective dates, active flags — live in the database
(`finance_pricing_catalog_items`) and are managed through the Pricing
Operations UI.

## Why

- Every registry attribute is behaviour wiring: a new obligation type always
  ships with new code (generator, materializer, cancel handler, permission
  gate). A DB registry row without matching code is a dead type; the DB shape
  pretends to be configurable but is not.
- What Finance staff genuinely change at runtime is price and effective date —
  that is exactly the pricing catalog's job, not the registry's.
- A code registry is testable ("every `charge_type` DB value has a registry
  entry" as an arch test), reviewable in diffs, and cannot drift between
  local and production. The production `retake_fee` 500 came from a missing
  **pricing row**, not a missing type — a DB registry would add a second copy
  of that failure mode, not remove it.

## Considered options

- **DB registry table** — rejected: fake configurability, new drift/500
  failure mode, no code to honour arbitrary new rows.
- **Hybrid (code registry + DB flag overrides)** — rejected as speculative;
  the one runtime gate that exists today (`FeeMonitorAcadRetGate`) is a code
  flag and suffices.

## Consequences

- Adding an obligation type is a code change + migration-reviewed deploy,
  never a prod data edit.
- The Pricing Operations UI (migration wave 1) scopes to pricing rules only —
  no type-management screens.
- Existing scattered definitions (`FeeMonitorExpectedFeeCatalog`,
  `DngFeeTypeOptions`) converge into the registry as waves land; they must
  not grow new per-type facts independently.
