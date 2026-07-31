---
phase: 5
title: "Phase 5: Restoration Flow"
status: todo
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

**Approval:** `restore_scholarship` (propose) + `approve_scholarship_adjustment` (approve, different user, campus-verified server-side). On approval: notification to student; open dossier (if any) → `closed` via P3 decision service. Rejected → adjustment stays `applied`, generation gate stays closed; re-proposal allowed with new reason.

**P2 apply-path hook (small modification, listed here, implemented against P2 code):** the scholarship apply path consults, besides the target-semester adjustment lookup, a "prior unresolved adjustment" check: latest `applied` adjustment for the student with `target_semester_id.start_date < current semester.start_date` and no `approved` restoration proposal → resolve provisionally with that adjustment's `adjusted_amount` (carry-forward) + flag review. One query object (`GetUnresolvedPriorAdjustmentQuery`), covered by the generation parity tests.

**Guards:** prior target-semester invoices untouched (restoration affects future generation only). **Late-charge regression test:** raise a corrective `tuition_term` charge on the OLD target semester AFTER a restoration is approved → discount must still resolve the ADJUSTED amount for that semester (adjustment still `applied`, still in lookup).

**UI:** proposals list + approve/reject co-located with dossier UI (P3), gated `restore_scholarship`/`approve_scholarship_adjustment`.

## Related Code Files

- Create: migration `create_scholarship_restoration_proposals_table`
- Create: `app/Modules/Finance/Models/ScholarshipRestorationProposal.php`
- Create: evaluation service split (Academic verdict query via shared contract + Finance proposal writer) + `app/Console/Commands/EvaluateScholarshipRestorations.php` (manual trigger, no scheduler in pilot)
- Create: `GetUnresolvedPriorAdjustmentQuery` (Finance) + wiring into the P2 apply path + batch path
- Create: controller + routes + UI list/approve
- Reuse: P3 candidate criteria query object; P2 contract/authorization helpers

## Implementation Steps

1. Migration + model + factory.
2. Evaluation service + command; tests: clean→proposal, failing→none (and P3 picks up new pair), unfinalized/incomplete→skip, component-appeal→skip, idempotent re-run, explicit-args validation.
3. Generation-time gate query + wiring; tests: no approved restoration → prior REDUCED rate applied provisionally + flagged; approved → original applies; new adjustment for N → new adjusted amount applies; rejection → reduced rate persists (never original).
4. Approve/reject with maker≠checker + campus re-verification; dossier close hook.
5. Late-charge regression test (old semester keeps adjusted amount post-restoration).
6. Routes/UI + notification.
7. E2E: adjust FALL2026 → finalize clean → proposal → approve → SPRING2027 generation uses original award; FALL2026 records + invoices intact.

## Todo

- [ ] Migration/model
- [ ] Evaluation command + tests
- [ ] Generation-time gate
- [ ] Approval flow
- [ ] Late-charge regression
- [ ] UI + notification
- [ ] E2E

## Success Criteria

- [ ] No restoration without approval — proven by test: rejected/absent proposal ⇒ next-semester resolves the prior REDUCED rate + review flag, never the original rate.
- [ ] Approved restoration → next-semester generation uses original award.
- [ ] Continued failure produces a NEW dossier, never extends the old decision.
- [ ] Late/corrective charge on old target semester still resolves adjusted amount (regression test).
- [ ] Prior semester's deduction records and invoices untouched (E2E assert).

## Risk Assessment

- Gate too aggressive (flags students whose adjustment predates feature rollout) → gate only considers adjustments created by this system (all of them, by definition of the new table) — no legacy rows exist.
- Evaluation before completeness → per-student all-units-finalized precondition.
- Boundary inversion (Finance querying AcademicRecord) → Academic-side verdict via contract; broadened arch test (P3) fails otherwise.
