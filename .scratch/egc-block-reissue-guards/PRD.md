# PRD: EGC Block Reissue Guards

Status: ready-for-agent

> Source: `/to-prd` synthesis from a debugging and grilling session around `AUS121787`, `SUMMER2026`, Batch Studio EGC generation, EGC block results, DNG payment bridging, and defer settlement behavior. Use the glossary vocabulary in `CONTEXT.md`: **EGC**, **Finance**, **DNG payment gateway**, **Student Action**, and **Academic Progression**. Respect the existing defer finance rule: a deferred original enrollment is non-billable, while real paid cash remains a Finance receipt until repaired, reallocated, refunded, or otherwise settled.

## Problem Statement

Finance staff can void EGC charges and then run Batch Studio EGC generation again for the same student and semester. The current flow treats the absence of active EGC charges as proof that the student still needs new EGC charges, but it separately uses existing EGC block rows to choose the next block number. When the old charges are voided but the old EGC blocks remain, the system can create Block 3 and Block 4 in a semester that should only have Block 1 and Block 2. Those duplicate/stale blocks then appear on EGC Block Results and can feed downstream DNG payment creation as if they were a real collectible obligation.

The concrete case is `AUS121787` in `SUMMER2026`: the student had Block 1 and Block 2 charges generated, those charges were voided, then generation ran again and produced Block 3 and Block 4 with new charges. A later DNG push collected 30,000,000 against those replacement charges. After the charges were voided/released, the student's Finance 360 showed 30,000,000 unapplied cash. The page was not inventing data; it was faithfully showing stale EGC block rows that should never have been auto-created.

From the user's perspective, the system must stop creating duplicate EGC block slots. If Finance staff intentionally need to bill again after a void, the system must reissue charges against the existing EGC block slots or route the case to manual repair, never create Block 3/4 as a side effect of "generate again".

## Solution

Treat an EGC block as a semester billing/progression slot for a student. A voided Finance charge cancels the financial document for that slot; it does **not** release the slot or authorize the system to create another block number in the same semester.

Batch Studio EGC generation must classify students with existing same-semester EGC blocks before offering or committing generation:

- If no same-semester EGC blocks exist, normal generation may create Block 1/2.
- If same-semester EGC blocks exist with active, collectible charges, the student is skipped as already generated.
- If same-semester EGC blocks exist but their charges are voided, missing, linked to cancelled invoices, or otherwise not collectible, the student is a manual repair or reissue candidate; the preview must not show them as a normal create line.
- If staff explicitly reissue for valid existing blocks, the replacement charges attach to the original block rows instead of creating new block numbers.
- If the student is deferred or the semester is otherwise non-billable, generation and reissue are blocked and routed to manual review.
- If a paid or live DNG request is linked to the old or replacement charge path, generation/reissue must stop and require DNG/payment review first.

This fixes the repeatability bug while preserving the current Finance ledger semantics: void releases allocations, DNG webhook creates real payments, and unapplied cash remains cash evidence until separately repaired.

## User Stories

1. As a Finance staff member, I want Batch Studio EGC preview to hide normal create actions for students who already have EGC blocks in the selected semester, so that I do not accidentally generate duplicate block slots.
2. As a Finance staff member, I want students with existing EGC blocks and active charges to appear as already generated, so that I understand no further action is needed.
3. As a Finance staff member, I want students with existing EGC blocks but voided charges to appear as manual repair or reissue candidates, so that I know the old billing slot needs attention rather than a new block.
4. As a Finance staff member, I want the commit step to enforce the same guard as preview, so that stale preview tabs or crafted requests cannot create duplicate blocks.
5. As a Finance staff member, I want a reissue operation to attach replacement charges to existing Block 1/2 rows, so that regenerated billing preserves the original block identity.
6. As a Finance staff member, I want reissue to be blocked when the old block/charge path has a live DNG request, so that I do not create a duplicate provider debt while the provider still holds a collectible request.
7. As a Finance staff member, I want reissue to be blocked when the old block/charge path has a paid DNG request or bridged payment, so that payment review happens before new billing.
8. As a Finance staff member, I want reissue to be blocked when the student is deferred for the selected semester, so that a non-billable defer period does not get resurrected by generation.
9. As a Finance staff member, I want reissue to be blocked when the selected semester has no valid EGC enrollment/billing basis, so that the system does not bill a student who is not expected to study that period.
10. As a Finance staff member, I want manual repair candidates to include a human-readable reason, so that I know whether the issue is voided charge, missing charge, cancelled invoice, paid DNG, live DNG, deferred student, or another blocker.
11. As a Finance staff member, I want the EGC Block Results page to keep showing the actual stored blocks, so that it remains an audit/read page rather than silently hiding data problems.
12. As a Finance staff member, I want EGC Block Results or related summaries to make stale/voided block rows visible as repair evidence, so that I can distinguish "pending study" from "broken finance linkage".
13. As a Finance manager, I want normal generation to be idempotent for the same student and semester, so that repeated staff actions do not create additional financial obligations.
14. As a Finance manager, I want a clear distinction between "void charge" and "remove block slot", so that operational staff do not treat voiding as permission to generate a new slot.
15. As a Finance manager, I want data cleanup for historical bad rows to be handled separately after the guard lands, so that we stop creating new bad data before repairing old data.
16. As an Academic staff member, I want EGC block numbering to remain meaningful for semester halves, so that Block 1/2 reports are not polluted by synthetic Block 3/4 rows.
17. As an Academic staff member, I want defer actions to keep their own audit metadata instead of relying on EGC block rows, so that defer reporting remains stable even when Finance repairs block billing data.
18. As a student, I want the portal and Finance 360 to reflect only real collectible obligations, so that I am not asked to pay for duplicate EGC blocks.
19. As a student, I want a real DNG payment to remain visible as cash even when the related obligation is voided, so that my paid money is not lost during repair.
20. As an operator reviewing `AUS121787`-style incidents, I want the system to show that old data is stale rather than creating more stale data, so that the incident root cause is contained.
21. As a developer implementing this fix, I want one canonical classification for same-semester EGC block state, so that preview, commit, and future repair paths cannot drift.
22. As a developer implementing this fix, I want tests that reproduce "void then generate again", so that the specific Block 3/4 bug can never return.
23. As a developer implementing this fix, I want the DNG guard included in the same behavior contract, so that reissue cannot bypass live or paid provider state.
24. As a developer implementing this fix, I want legacy EGC generation entry points to either share the same guard or refuse the old path, so that duplicate blocks cannot be created through a secondary route.
25. As a product owner, I want the first delivery to focus on preventing recurrence, so that old data can be repaired after the new invariant is protected.

## Implementation Decisions

- **EGC block slot invariant.** An EGC block row reserves a student + semester + block-number slot. Voiding a Finance charge does not free that slot. Normal generation must not create a later block number just because the old charge is no longer active.
- **Preview and commit share classification.** Batch Studio EGC preview and commit must classify same-semester EGC state through the same concept, even if the implementation uses separate Query and Action code. The commit path is the enforcement boundary; preview is the operator explanation.
- **Normal generation only creates new slots when no same-semester slots exist.** Existing same-semester EGC blocks convert the row into one of: already generated, repair needed, or reissue candidate. They do not become normal create rows.
- **Reissue is slot-based.** A replacement charge created after a void attaches to the original block slot. It does not create Block 3/4. Reissue may create a new Finance charge, invoice line, and invoice snapshot as needed, but it preserves the EGC block identity.
- **Manual repair is explicit.** If the system cannot safely reissue because there is live DNG, paid DNG, bridged payment, cancelled invoice ambiguity, missing invoice line, defer non-billable state, or inconsistent block count, it must stop and surface a repair reason. It must not silently choose a billing action.
- **Deferred semester stays non-billable.** If the defer rules say the student's original enrollment for the semester is non-billable, neither generation nor reissue may resurrect the obligation. The existing paid cash behavior remains a settlement concern, not proof that a new charge should be created.
- **DNG provider state blocks reissue.** A live provider request means DNG still has a collectible debt; a paid request means there is real cash evidence. Both must be reviewed before replacement charge creation.
- **Block Results remains evidence-first.** The EGC Block Results surface may show stale/voided linkage as a repair state, but it must not hide stored rows to make the data look clean. Data cleanup is separate.
- **Legacy EGC generation paths must be safe.** Any still-enabled legacy path that can generate EGC obligations must either call the same guard or refuse generation. The current canonical path is Batch Studio.
- **No schema change required for the first slice.** The guard can be implemented with existing EGC block, charge, invoice, DNG, payment, defer, and registration data. Audit/provenance improvements can be a future story.
- **No historical data repair in this PRD.** Existing bad rows such as `AUS121787` are documented evidence and later repair candidates. This PRD stops recurrence first.

## Testing Decisions

- A good test asserts externally observable behavior: preview buckets, commit outcomes, created block counts, created charge links, DNG/payment blockers, and final database state. It must not assert on private helper methods.
- Preferred highest seam: Batch Studio HTTP feature tests for EGC charge preview and commit. This drives the real operator path: preview token issuance, server-side recompute, commit authorization, and charge/block creation.
- Add a direct EGC generation action regression test only where the HTTP seam cannot reach a low-level invariant cleanly. The action-level test must reproduce the exact bug shape: create Block 1/2, void their charges, run generation again, and assert no Block 3/4 is created.
- Test the reissue branch with existing voided block slots: replacement charges attach to the original block rows, block numbers remain unchanged, and active invoice lines point to the replacement charges.
- Test the "repair needed" branch with paid/live DNG state: preview marks the row as blocked or warning, and commit refuses to create or reissue charges.
- Test the defer branch: a deferred/non-billable semester stays excluded from generation and reissue, even if old voided EGC blocks exist.
- Test already-generated behavior: existing active charges for the selected semester still skip normally.
- Test no-block behavior: a student with no same-semester EGC blocks can still generate Block 1/2 as before.
- Test stale preview safety: a token issued before data changes cannot commit a now-blocked EGC reissue or normal generation.
- Prior art exists in the Finance EGC generation tests, Batch Studio charge preview/commit tests, DNG worklist tests, void/release tests, and defer generation non-billable tests.
- Run targeted Pest tests for the Finance EGC, Batch Studio, DNG/void, and defer branches affected by the implementation. Run Pint for modified PHP files.

## Out of Scope

- Repairing or deleting existing bad EGC block rows such as `AUS121787` in this PRD.
- Refunding, reallocating, or manually settling already-paid DNG cash.
- Changing DNG webhook settlement semantics.
- Changing the meaning of payment applications, invoice line settlement, or void release behavior.
- Changing Academic defer audit metadata or historical defer reports.
- Adding new audit/provenance columns to EGC blocks or DNG payment requests in the first slice.
- Redesigning the EGC Block Results page beyond showing repair states needed for this guard.
- Changing student portal finance behavior unless an API contract must be updated by the implementation.

## Further Notes

- The `AUS121787` timeline is the canonical repro story: Block 1/2 created, charges voided, generation ran again, Block 3/4 created, DNG paid, later void/release exposed 30,000,000 unapplied cash.
- The root defect is not that EGC Block Results renders the wrong source. It reads stored EGC blocks. The root defect is that generation allowed stale/voided block slots to turn into new block numbers.
- "Sinh lại" after void means **reissue charge for the existing block slot**, not "create the next block".
- Historical data repair should be planned only after this guard is in place, otherwise cleanup can be undone by the next generation run.
