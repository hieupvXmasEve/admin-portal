# Exec Plan

## Goal

Make Batch Studio the single primary surface for bulk DNG payment-request
creation and rename the operator-facing workflow to match the business action:
`Lập yêu cầu thanh toán DNG`.

## Scope

In scope:

- Move all normal navigation and shortcuts for bulk DNG creation to
  `finance.batch-studio.dng`.
- Rename user-facing labels away from `DNG Worklist` and page-level `Push DNG`.
- Convert the old DNG Worklist URL into a compatibility path.
- Prevent the old POST route from remaining a direct write bypass around Batch
  Studio preview-token verification.
- Preserve DNG request, webhook, and audit/read pages.
- Update tests and story/docs evidence for the cutover.

Out of scope:

- Changing DNG provider API calls, checksum validation, webhook processing, or
  reconciliation.
- Changing charge/discount/installment amount calculations.
- Changing student or lecturer portal APIs.
- Removing DNG audit/detail pages.
- Large async/queued Batch Studio redesign.

## Risk Classification

Risk flags:

- Authorization: route/menu cutover must preserve DNG write gates.
- External systems: the story touches the operator path that creates provider
  DNG payment requests.
- Public contract: Finance Office route names, labels, and handoff URLs change.
- Existing behavior: DNG Worklist is already implemented and tested.
- Weak proof: old and new DNG paths currently coexist, so bypass regressions are
  easy to miss.

Hard gates:

- Authorization.
- External provider behavior.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Discovery
   - Inventory every reference to `finance.operations.dng-worklist`,
     `DNG Worklist`, `Push DNG`, and `Đẩy DNG hàng loạt`.
   - Confirm current Batch Studio DNG feature parity against the old page:
     fee type, semester, selected student ids, due date, description, estimate
     time, amount overrides, active-DNG warning, result/retry behavior.

2. Design confirmation
   - Confirm final Vietnamese labels with the product owner before code edits.
   - Confirm whether the old `GET` route should redirect directly to the DNG
     wizard or to the Batch Studio hub.
   - Confirm whether unsupported old query parameters are ignored, mapped, or
     shown as a warning.

3. Implementation
   - Update route helpers and shortcuts so new navigation lands in Batch Studio.
   - Add prefill support to Batch Studio DNG only for explicitly supported keys.
   - Replace old page/menu labels with canonical business labels.
   - Retire or block the old POST route so it cannot write outside the Batch
     Studio preview-token contract.
   - Keep DNG audit/read routes unchanged.

4. Verification
   - Run targeted DNG Worklist compatibility, Batch Studio DNG, and candidate
     query tests.
   - Run frontend lint/type/build checks for touched files.
   - Exercise or test one DNG creation path through Batch Studio and record
     audit/linkage evidence.
   - Run `finance:audit-invariants` if the write path is exercised.

5. Harness update
   - Update cutover inventory and Batch Studio story evidence.
   - Register any follow-up backlog if a compatibility route or code identifier
     rename is intentionally deferred.

## Stop Conditions

Pause for human confirmation if:

- The final Vietnamese business label differs from `Lập yêu cầu thanh toán DNG`.
- Product wants the old DNG Worklist table preserved as a read-only candidate
  review page instead of redirecting to Batch Studio.
- Implementation requires changing DNG provider payloads, replacement rules, or
  webhook/reconciliation behavior.
- Direct old POST compatibility is requested, because it conflicts with the
  Batch Studio preview-token safety contract.
- Validation coverage must be weakened or skipped for DNG writes.
