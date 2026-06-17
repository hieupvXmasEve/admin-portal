# Overview

## Status

implemented

## Lane

high-risk

## Current Behavior

Batch Studio already has a charge-generation wizard that accepts
`fee_category=major` and `fee_category=egc`, previews through the existing
Major/EGC preview queries, and commits through the existing generation Actions
behind the preview-token contract.

Finance Office still exposes standalone generation surfaces:

- `finance.major.charges.index` / `finance.major.charges.store` for HP/Tuition.
- `finance.egc.charges.index` / `finance.egc.charges.store` for EGC.

Those pages keep operators split between the old direct generation forms and
Batch Studio. They also make the cutover inventory ambiguous: Batch Studio is
the intended bulk workspace, but HP/Tuition and EGC still look like primary
standalone destinations.

One current parity risk must be checked during implementation: EGC preview
allows `generate_egc_finance_charges`, while the shared Batch Studio charge
commit request currently requires `create_finance_charges` before the controller
can apply the EGC-specific permission check. An EGC-only operator may therefore
preview but fail to commit unless the authorization boundary is corrected.

## Target Behavior

Batch Studio is the only primary surface for normal HP/Tuition and EGC charge
generation.

Normal navigation, shortcuts, and internal links for these jobs route to
`finance.batch-studio.charges` with a safe prefill for:

- HP/Tuition: `fee_category=major`
- EGC: `fee_category=egc`

The old HP/Tuition and EGC generation URLs become compatibility paths only:

- `GET` redirects or forwards to Batch Studio charges with safe query
  preservation, or remains as an explicitly labelled repair/deep-link page if
  product confirms a repair-only need.
- `POST` must not remain an alternate direct write path that bypasses the Batch
  Studio preview-token verification.

EGC support surfaces remain separate and are not migrated by this story:

- EGC Block Results.
- EGC Retake Adjustments.
- EGC Carry Forward.

## Affected Users

- Finance staff generating HP/Tuition charges.
- EGC operators generating EGC charges.
- Finance leads validating that bulk charge generation has one safe primary
  workflow.

## Affected Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-010-batch-studio/`
- `docs/stories/E-finance-module-review-2026-06/S-010-cutover-and-uat/`
- `docs/stories/E-finance-module-review-2026-06/S-010-lookup-and-audit/`
- `docs/stories/E-finance-module-review-2026-06/README.md`

## Portal Impact

Portal impact: none.

This story targets the admin/staff Finance Office Inertia UI and web routes. It
must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, student portal code,
lecturer portal code, or the student-facing payment contract.

## Non-Goals

- Do not change HP/Tuition, EGC, scholarship, discount, installment, or retake
  money math.
- Do not introduce new charge-generation Actions or duplicate resolvers.
- Do not remove EGC Block Results, Retake Adjustments, or Carry Forward.
- Do not delete old URLs until redirects, tests, and internal links prove no
  operator or bookmarked path is broken.
- Do not implement this story without separate explicit implementation
  approval.
