# Overview

## Status

implemented

## Lane

high-risk

## Current Behavior

Finance Office currently has two DNG bulk-creation surfaces:

- `finance.operations.dng-worklist` renders `Finance/Operations/DngWorklist` and
  lets operators select students, override amounts, choose DNG metadata, and
  create DNG payment requests directly.
- `finance.batch-studio.dng` renders `Finance/BatchStudio/DngPush` and already
  uses the Batch Studio preview-token workflow for the same DNG payment-request
  job.

The visible naming is mixed. The sidebar and legacy page say `DNG Worklist`,
Batch Studio says `Đẩy DNG hàng loạt`, and some shortcuts still route to the
legacy worklist. That makes the business job sound like a technical queue or
provider push instead of what staff are actually doing: creating payment
requests for students through DNG.

The DNG audit/read surfaces are separate and still valid:

- `finance.dng.payment-requests.index`
- `finance.dng.payment-requests.show`
- `finance.dng.webhook-events.index`
- `finance.dng.webhook-events.show`

## Target Behavior

Batch Studio is the only primary surface for bulk DNG payment-request creation.
Every normal entry point that starts the DNG creation job routes to
`finance.batch-studio.dng`, with any supported filters or selected student ids
carried into the wizard as prefill.

The canonical user-facing business name is:

- Page / tile / shortcut: `Lập yêu cầu thanh toán DNG`
- Confirm action: `Gửi yêu cầu sang DNG`
- Audit list: `Yêu cầu thanh toán DNG`

`DNG Worklist` and `Push DNG` must not remain as primary user-facing labels.
They may survive only as temporary technical identifiers while the route is a
redirect shim or while tests are being renamed.

The old `finance.operations.dng-worklist` route becomes compatibility only:

- `GET` redirects or forwards to Batch Studio DNG with safe query preservation.
- `POST` must not remain an alternate write path that bypasses Batch Studio
  preview-token verification.
- The old page is removed from normal navigation and shortcuts.

## Affected Users

- Finance staff creating DNG payment requests in bulk.
- Finance leads validating role-based Finance Office navigation.
- Audit/reconciliation users who still need DNG payment request and webhook
  history pages.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-batch-studio/`
- `docs/stories/E-finance-module-review-2026-06/S-010-cutover-and-uat/`
- `docs/stories/E-finance-module-review-2026-06/S-010-lookup-and-audit/`
- `docs/stories/E-finance-module-review-2026-06/README.md`

## Portal Impact

Portal impact: none.

This story targets the admin/staff Finance Office web console. It must not
change `/api/v1/student/*`, `/api/v1/lecturer/*`, student portal routes, lecturer
portal routes, or the student-facing DNG payment contract.

## Non-Goals

- Do not change DNG provider API behavior, checksum, webhook processing, or
  reconciliation logic.
- Do not change money math, installment math, charge selection math, or the
  `CreateBatchDngFromChargesAction` replacement rule.
- Do not remove DNG payment request or webhook audit/detail pages.
- Do not delete the old URL until redirects, tests, and internal links prove no
  operator or bookmarked path is broken.
- Do not implement this story without a separate explicit implementation
  approval.
