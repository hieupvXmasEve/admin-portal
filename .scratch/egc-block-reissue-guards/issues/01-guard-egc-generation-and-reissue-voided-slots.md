# 01 — Guard EGC Generation And Reissue Voided Slots

Status: ready-for-agent

## Parent

[PRD: EGC Block Reissue Guards](../PRD.md)

## What to build

Implement the first recurrence-prevention slice for EGC block generation.

Batch Studio EGC charge preview and commit must stop treating "no active EGC charge" as enough evidence to create new EGC blocks. Before normal generation, classify the student's same-semester EGC block state:

- no existing EGC blocks: eligible for normal generation;
- existing blocks with active collectible charges: skip as already generated;
- existing blocks with voided/missing/non-collectible charges: repair or reissue state, not normal generation;
- existing blocks with live or paid DNG/payment evidence: blocked for DNG/payment review;
- deferred/non-billable semester: blocked;
- inconsistent block shape: blocked for manual repair.

When reissue is safe and explicitly selected by the operator path implemented in this slice, replacement charges must attach to the existing EGC block rows. The implementation must not create Block 3/4 for a semester because Block 1/2 charges were voided.

If the implementation cannot safely support a full operator reissue control in this slice, it must still block normal duplicate generation and expose enough repair reason data for a follow-up repair tool. Do not silently create duplicate blocks as a fallback.

## Acceptance criteria

- [ ] EGC generation no longer creates Block 3/4 after Block 1/2 charges are voided for the same student and semester.
- [ ] Batch Studio EGC preview classifies students with existing same-semester voided/missing/non-collectible EGC block charges as repair/reissue candidates, not normal create rows.
- [ ] Batch Studio EGC commit enforces the same classification even if a preview token is stale.
- [ ] Existing same-semester active EGC charges still skip as already generated.
- [ ] Students with no same-semester EGC blocks still generate the expected Block 1/2 charges.
- [ ] Safe reissue attaches replacement charges to the existing block rows and keeps block numbers unchanged.
- [ ] Reissue/generation is blocked when a live DNG request is linked to the target obligation.
- [ ] Reissue/generation is blocked when a paid DNG request or bridged payment is linked to the target obligation.
- [ ] Reissue/generation is blocked when defer logic marks the selected semester as non-billable.
- [ ] Repair/reissue rows include stable reason codes suitable for the UI and tests.
- [ ] Any still-enabled legacy EGC generation path cannot bypass this guard.
- [ ] The EGC Block Results read path remains evidence-first and does not silently hide existing stale rows.
- [ ] Targeted Pest coverage proves preview, commit, action-level idempotency if needed, DNG/payment blocking, and deferred non-billable blocking.

## Testing notes

- Prefer Batch Studio HTTP feature tests for preview and commit because that is the operator path.
- Add an action-level regression test for the exact void-then-generate bug shape if it is the cleanest way to lock the invariant.
- Reuse existing Finance EGC generation, Batch Studio preview/commit, DNG, void/release, and defer test patterns.
- Run targeted Finance EGC/Batch/DNG/defer tests and Pint for touched PHP files.

## Out of scope

- Cleaning up `AUS121787` or any other historical rows.
- Refund/reallocation of already-paid DNG cash.
- New audit/provenance columns.
- Broad redesign of EGC Block Results.

## Blocked by

None - can start immediately.
