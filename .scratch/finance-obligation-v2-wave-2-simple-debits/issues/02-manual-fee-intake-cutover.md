# 02 - Manual fee page intake cutover

Status: ready-for-human
Depends on: wave 1 issues 01, 02, 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Convert the manual charge page into a debit intake client for `manual_fee`. Staff still create an ad-hoc payable from the same staff workflow, but the request enters through the Finance Intake Contract with a Finance-owned source reference and only the materializer writes the debit read model.

## Acceptance criteria

- [x] The manual fee form creates an accepted FinanceObligation plus materialized charge and invoice line through intake.
- [x] The manual fee workflow rejects source-supplied final pricing values and surfaces missing-pricing errors clearly to staff.
- [x] Manual `manual_fee` creation no longer calls the shared direct charge creation path from the controller or page workflow.
- [x] Existing permissions, validation, and charge-detail navigation continue to work for manual fee rows.
- [x] Feature and Inertia tests cover successful creation, validation failure, and pricing-rule failure.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/02-pricing-operations-coverage-warning.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/03-billing-accounts-payer-key.md`
