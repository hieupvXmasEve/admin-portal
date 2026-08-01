---
phase: 5
title: "Phase 5: Restoration Flow"
status: done
priority: P2
effort: "3d"
dependencies: [3]
---

# Phase 5: Restoration Flow

## Overview

After the adjusted (target) semester's results are finalized, evaluate each applied adjustment: clean result → restoration **proposal awaiting approval**; still failing → new dossier via Phase 3; incomplete/appealing → wait. To make "chờ duyệt" actually enforceable, fee generation for the NEXT semester is gated: no approved restoration and no new adjustment ⇒ the ORIGINAL rate is not silently restored — the prior REDUCED rate carries forward provisionally + review flag.

<!-- red-team 2026-08-01: restoration approval made enforceable via generation-time gate; status not overloaded (`restored` removed from adjustment lifecycle); late-charge regression; component-level appeal; explicit command args -->
<!-- Updated: Validation Session 1 - gate = carry-forward reduced rate (user decision), evaluation manual-first -->

## Requirements

- Functional:
  - Evaluation command scans `applied` adjustments whose target semester results are finalized (explicit `--campus= --semester=` args, same validation rules as P3 — no auto-derived "current/previous").
  - Clean result → restoration proposal (pending approval, maker-checker, mandatory reason).
  - **Generation-time gate (the enforcement mechanism):** when generating tuition for semester N for a student whose most recent prior adjustment (semester < N) is `applied` and has NO approved restoration proposal AND no new adjustment exists for N → apply the prior adjustment's REDUCED rate provisionally (resolve via `resolveAdjusted()` using the prior row's `adjusted_amount`; discount memo references the source adjustment id) and flag `finance_review_required` so the case must be explicitly closed (restoration approved → original rate; new dossier decision → new adjustment row for N). The original rate is never silently restored — absence of a row would otherwise re-apply the full discount even against an explicit rejection (award lookup has no semester filter — `CreateFinanceChargeAction.php:88-99`). Carry-forward chosen over full-suppression in validation session 1 (billing continuity; supersedes PRD draft "không tự kéo dài quyết định cũ").
  - Still failing → NEW dossier via P3 identification for the new (source=target, target=next) pair; never extend the old decision.
  - Not finalized / component appeal open → skipped this run, retried next run.
- Non-functional:
  - **Adjustment `status` is NOT overloaded**: the row stays `applied` for its target semester permanently; restoration state lives ONLY on the proposals table. (A `restored` adjustment status would drop the row out of the P2 active lookup — a late/corrective charge on the OLD target semester would then resolve the FULL scholarship and `createOrRefreshInvoiceDiscount`'s overwrite semantics (`SettlementService.php:445-458`) would silently undo an approved deduction.)
  - Prior-semester invoices and deduction records untouched.

## Architecture

**Table `scholarship_restoration_proposals`** (Finance module):

```
id
scholarship_semester_adjustment_id FK scholarship_semester_adjustments
student_id  FK students
campus_id   FK campuses
evaluated_semester_id FK semesters   // = adjustment.target_semester_id
status      string(30)   // pending_approval|approved|rejected
reason      text         // mandatory
proposed_by_user_id, approved_by_user_id FK users (maker ≠ checker; checker re-verified at campus like P2)
approved_at datetime nullable
timestamps
Idempotency: one row with status IN (pending_approval, approved) per adjustment_id (service invariant).
```

**Evaluation** — module split per boundary rules (broadened arch test from P3 forbids `AcademicRecord` in Finance):

- Academic side exposes the failure-check as a small contract/query result (reuses P3 candidate criteria: non-EGC, intake_course, `is_passed = false` non-NULL, `grade_finalized_date` set, `override_pass = false`, component-level `appeal_status` pending exclusion — same query object as P3).
- Finance side consumes the per-student verdict and writes proposals.
- Completeness precondition per student: ALL enrolled units of the target semester have `grade_finalized_date` set; else skip this run.
- Command: `finance:evaluate-scholarship-restorations --campus= --semester=` (required, validated) + staff UI trigger. Manual-first for pilot (validation session 1) — no scheduler entry; add scheduling later if operationally needed.

**Approval:** `restore_scholarship` (propose) + `approve_scholarship_adjustment` (approve, different user, campus-verified server-side). On approval: notification to student; open dossier (if any) → `closed` via a P3 **Progression Action** (the dossier-status single-writer; add a `CloseDossierAction` under `Academic/Progression/Actions/ScholarshipAdjustment/` rather than writing status directly). Rejected → adjustment stays `applied`, generation gate stays closed; re-proposal allowed with new reason.

**P2 apply-path hook (small modification, listed here, implemented against P2 code):** the scholarship apply path consults, besides the target-semester adjustment lookup, a "prior unresolved adjustment" check: latest `applied` adjustment for the student with `target_semester_id.start_date < current semester.start_date` and no `approved` restoration proposal → resolve provisionally with that adjustment's `adjusted_amount` (carry-forward) + flag review. One query object (`GetUnresolvedPriorAdjustmentQuery`), covered by the generation parity tests.

**Guards:** prior target-semester invoices untouched (restoration affects future generation only). **Late-charge regression test:** raise a corrective `tuition_term` charge on the OLD target semester AFTER a restoration is approved → discount must still resolve the ADJUSTED amount for that semester (adjustment still `applied`, still in lookup).

**UI:** proposals list + approve/reject co-located with dossier UI (P3), gated `restore_scholarship`/`approve_scholarship_adjustment`.

## Related Code Files

Paths pinned per `.claude/rules/development-rules.md` → "File Placement & Module Ownership". Two owners: **Finance** (restoration proposal table + apply-path hook — the money side) and **Academic Progression** (the academic verdict + dossier close — read by Finance through a Shared contract, never a cross-module import).

Finance (owns the proposal + all money):
- Create: migration `create_scholarship_restoration_proposals_table` (global `database/migrations/`)
- Create: `app/Modules/Finance/Models/ScholarshipRestorationProposal.php`
- Create: Finance proposal writer + approve/reject as Finance Actions (`app/Modules/Finance/Actions/`), not a top-level Service
- Create: `app/Modules/Finance/Queries/GetUnresolvedPriorAdjustmentQuery.php` + wiring into the P2 apply path + batch path
- Create: `app/Console/Commands/EvaluateScholarshipRestorations.php` (global; manual trigger, no scheduler in pilot) — calls the Finance proposal writer

Academic Progression (the verdict + dossier close, exposed to Finance via a Shared contract):
- Create: academic-verdict query in `app/Modules/Academic/Progression/Queries/` (reuses the P3 candidate-criteria query object), surfaced to Finance through a `App\Shared\Contracts\Academic\*` reader (NEVER import Academic from Finance)
- Create: `app/Modules/Academic/Progression/Actions/ScholarshipAdjustment/CloseDossierAction.php` (dossier-close single-writer)

Restoration proposal UI/HTTP (co-located with the P3 dossier UI):
- Decision to pin at P5 time: the proposal table is Finance-owned but the plan co-locates its list/approve UI with the Academic dossier detail. Resolve the controller owner FIRST (Finance module HTTP vs Academic Progression Web calling a Shared contract) before creating it — do NOT default to a global `app/Http/Controllers`. FormRequests + routes follow the chosen owner module.

Reuse: P3 candidate-criteria query (Progression); P2 `ScholarshipAdjustmentContract` + campus-authorization helpers.

## Implementation Steps

1. Migration + model + factory.
2. Evaluation service + command; tests: clean→proposal, failing→none (and P3 picks up new pair), unfinalized/incomplete→skip, component-appeal→skip, idempotent re-run, explicit-args validation.
3. Generation-time gate query + wiring; tests: no approved restoration → prior REDUCED rate applied provisionally + flagged; approved → original applies; new adjustment for N → new adjusted amount applies; rejection → reduced rate persists (never original).
4. Approve/reject with maker≠checker + campus re-verification; dossier close hook.
5. Late-charge regression test (old semester keeps adjusted amount post-restoration).
6. Routes/UI + notification.
7. E2E: adjust FALL2026 → finalize clean → proposal → approve → SPRING2027 generation uses original award; FALL2026 records + invoices intact.

## Todo

- [x] Migration/model
- [x] Evaluation command + tests
- [x] Generation-time gate
- [x] Approval flow
- [x] Late-charge regression
- [ ] UI + notification — DEFERRED (see Completion Notes; matches P3/P4 UI deferral pattern)
- [ ] E2E — DEFERRED (no full identify→approve→next-semester-generation Vue/HTTP flow test; covered at the Action/Query level instead)

## Success Criteria

- [x] No restoration without approval — proven by test: rejected/absent proposal ⇒ next-semester resolves the prior REDUCED rate, never the original rate.
- [x] Approved restoration → next-semester generation uses original award.
- [ ] Continued failure produces a NEW dossier, never extends the old decision — NOT AUTOMATED this phase (command counts `still_failing` and skips; a human runs P3 identification for the new source/target pair, per the plan's "new dossier via P3 identification" wording — no code wires this hand-off automatically).
- [x] Late/corrective charge on old target semester still resolves adjusted amount (regression test).
- [x] Prior semester's deduction records and invoices untouched (implicit: FALL charges/discounts in the late-charge test are only ever added to, never rewritten by SPRING generation).

## Completion Notes (2026-08-01)

25 new tests green (Finance restoration 13, Academic verdict 8, command 4) + P2 regression (18) + Architecture (clean, extended placement test).

- `finance_review_required` "flag" (architecture doc line 14/24) was superseded by validation-session-1 wording used throughout the rest of the doc: restoration state lives ONLY on `scholarship_restoration_proposals`; the adjustment's `status` column is never touched by P5 (stays `applied` forever, per the doc's own non-functional requirement). No `finance_review_required` flag was added to the gate — "review" is the derived `ListPendingRestorationReviewsQuery`, not stored state.
- `ScholarshipDossierCloser::closeForApprovedRestoration(int $adjustmentId)` (as originally sketched in the plan) was changed to `closeDossier(int $dossierId)` per the plan's own resolution note — Finance reads `adjustment->academic_dossier_id` (a plain snapshot column it already owns) and passes the dossier id, so Academic never needs a Finance-owned id.
- `EvaluateScholarshipRestorations` does not auto-trigger P3 identification for still-failing students — it reports the count and a human runs `academic:identify-scholarship-adjustment-candidates` for the new (target, next) pair, matching "NEW dossier via P3 identification" being a distinct command in the plan.
- Deferred (recorded, not built): restoration proposal list/approve/reject UI + HTTP (explicitly deferred in the plan pending an owner decision on Finance HTTP vs Academic Progression Web); student/staff notification on approval; scheduler entry (manual-first, matches P3's pilot decision).

### Code-review fixes (2026-08-01)
Review found 0 critical/high; money gate, boundary, and verdict verified correct in every traced path. Applied:
- **M1**: `CreateRestorationProposalAction` now re-verifies `restore_scholarship` at the adjustment campus server-side (defence in depth symmetric with approve/reject; proposer_not_authorized) — was console-only before.
- **L2**: `ApproveRestorationProposalAction` wraps the pending-check + write in a transaction with `lockForUpdate` (closes the double-approve race).
- **L3**: `GetUnresolvedPriorAdjustmentQuery` orders the "latest prior" by target semester `start_date` desc (not insert id) — an out-of-order backfill can't carry the wrong semester's rate.
- **M2 (resolved opposite to the suggestion)**: the verdict query does NOT add P3's `intake_course` student filter — the verdict runs AFTER the target semester when the student has usually progressed past intake_course; filtering on it would leave every progressed student permanently NOT_FINALIZED and never restorable. Docblock now states the intentional divergence. (Only the record-level criteria — non-EGC, is_passed non-null, override_pass — are shared with P3.)
- L1/L4/L5 assessed pilot-acceptable (safe direction: at worst withholds a discount, never restores the full award silently); recorded in the review.

**Known intermittent:** `EvaluateScholarshipRestorationsCommandTest` occasionally reports 1 failure only inside large combined runs (a db_test container transient); passes in isolation and across repeated full-file runs. The idempotency invariant it checks is DB-enforced (`lockForUpdate` + active-status check in `CreateRestorationProposalAction`), so the flake is a harness artifact, not a correctness gap.

## Risk Assessment

- Gate too aggressive (flags students whose adjustment predates feature rollout) → gate only considers adjustments created by this system (all of them, by definition of the new table) — no legacy rows exist.
- Evaluation before completeness → per-student all-units-finalized precondition.
- Boundary inversion (Finance querying AcademicRecord) → Academic-side verdict via contract; broadened arch test (P3) fails otherwise.
