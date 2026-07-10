# 03 - Adjustment three-way split (new path)

Status: ready-for-agent
Depends on: 01
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Implement the PRD **adjustment 3-way split** for new writes. Wave 6 audit found **zero** legacy `adjustment` rows on `asia`, so **no backfill classifier** is required — only stop the unconstrained signed charge shape going forward and route the known generators correctly.

## Context (from 01 audit)

| Shape | Evidence | Target |
|---|---|---|
| Positive debit | Manual create allows any sign; defer **FORFEIT** auto-creates positive `TYPE_ADJUSTMENT` + cash allocate (`ApplyDeferFinancePolicyAction`) | `FinanceObligation` debit (`adjustment` type) via intake |
| Negative credit | Money-sign CHECK leaves `adjustment` unconstrained; staff can enter negative amounts on manual form | `FinanceCreditEntitlement` (manual credit memo) — **never** a negative charge on the new path |
| Settlement correction | No generator creates pure reallocation as `adjustment` today | Ledger reallocation operation — **never** a charge row |

Legacy rows to classify/backfill: **none** on sample. If remote prod later shows rows, re-open a backfill ticket with sign/source classification.

## Acceptance criteria

- [ ] New staff adjustments **declare** which of the three shapes they are (UI + validation); default must not be "free-signed charge".
- [ ] Positive debit path goes through Finance Intake (`source_system=finance`, appropriate `source_kind`), not bare `FinanceCharge::create` / unconstrained `CreateFinanceChargeAction` for this type.
- [ ] Negative credit path creates a credit entitlement (or is rejected with a clear message pointing at the credit-memo flow) — no new negative `finance_charges.adjustment` rows.
- [ ] Settlement correction is not expressible as `charge_type=adjustment`.
- [ ] Defer FORFEIT settlement either (a) keeps minting a positive debit obligation via intake, or (b) is redesigned as an explicit ledger settlement op — choice documented in the issue Comments; prefer (a) minimal change unless (b) is already available.
- [ ] Tests cover: positive debit intake; negative rejected/routed; FORFEIT still settles without inventing unpaid debt (existing defer forfeit invariants).
- [ ] Zero-row sample: no legacy backfill command required; add a data guard or one-shot check that active signed adjustments, if any appear later, are exception-listed.

## Out of scope

- Applicant admission fees / PRE.
- `course_fee` retirement (issue 02).
- Wave 7 global materializer-only arch test (depends on this cutover for adjustment).

## Blocked by

- `.scratch/finance-obligation-v2-wave-6-audit-tail/issues/01-admission-course-adjustment-dng-audit.md`
- Prefer wave 4 credit entitlement spine already landed (for negative path): `finance-obligation-v2-wave-4-credit-discount-entitlements`
