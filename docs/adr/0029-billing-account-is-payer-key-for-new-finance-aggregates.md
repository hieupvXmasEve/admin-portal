# Billing account is the payer key for new Finance aggregates; legacy ledger stays student-keyed

**Status:** Accepted
**Date:** 2026-07-09
**Owner:** Finance

## Context

Finance must eventually bill people who are not (yet) Students — an Applicant
paying an admission fee (DNG `PRE`) is the known case. Slice-1 created
source-keyed `finance_obligations` without a payer key; its materializer still
uses `student_id` from intake facts to project the student-keyed legacy ledger
(`finance_charges`, `student_invoices`, `payments`). The choice was: keep
aggregate truth source-keyed and bolt on payer special cases later, introduce a
payer abstraction only for admission fees, or re-key everything including the
legacy ledger.

## Decision

Introduce **`billing_accounts`** as the Finance-owned payer identity, and key
**new aggregate tables only** by it:

- `finance_obligations`, `finance_credit_entitlements`, and
  `finance_discount_entitlements` reference `billing_account_id` (from
  migration wave 1; existing source-keyed obligations are populated by resolving
  the billing account from their materialized charge/source facts).
- Every **Student** auto-provisions exactly one billing account.
- An **Applicant** is provisioned a billing account only when a fee actually
  arises (wave 6); on Approve, the same account carries over to the Student.
- The **legacy ledger stays student-keyed** — `finance_charges`,
  `student_invoices`, `payments` are not re-keyed. The materializer resolves
  `billing_account → student` when projecting.
- **Pre-Approve collection is not supported by this ADR.** A pre-student
  obligation may exist as aggregate truth (`requested`/`accepted`), but it
  cannot materialize, be invoiced, or enter DNG/payment collection until
  Approve links the account to a Student — the entire collection ledger
  (invoices, payments, DNG requests) is student-keyed. If the wave-6 audit
  finds a genuine business need to *collect* admission fees before Approve,
  that requires a separate ADR specifying an applicant-scoped collection path
  (holding ledger, deterministic carry-over, and reconciliation on Approve);
  it must not be improvised from this decision.

## Why

Two payer mechanisms forever (source-keyed obligations plus a special admission
path) would make every query and report carry two key shapes.
Re-keying the legacy ledger has an enormous blast radius (settlement math,
DNG, all reports, finished backfills) for no behavioural gain. Keying only the
new aggregates is the smallest change that makes "payer who is not a Student"
a first-class concept where truth lives, while the read-model layer keeps its
existing shape per ADR-0028.

## Consequences

- Wave 1 of the migration program includes the `billing_accounts` table,
  student auto-provisioning, adding `billing_account_id` to
  `finance_obligations`, and backfilling it from existing materialized
  charge/source evidence.
- Financial Clearance and settlement contracts keep their source-triple keying;
  the billing account is resolved inside Finance, never passed by sources.
- Pre-student obligations exist as aggregate truth but are not billable,
  collectible, or visible to student-scoped ledgers/reports until Approve
  links the account to a Student.
