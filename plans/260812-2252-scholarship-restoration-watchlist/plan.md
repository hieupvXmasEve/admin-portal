---
title: "Scholarship Restoration Watchlist"
description: "Staff watchlist for students carrying scholarship adjustments (verdict/GPA/attendance) + HTTP decision loop for restoration with partial-restore support"
status: pending
priority: P1
effort: "3.5-4d"
tags: [academic, finance, scholarship, progression, watchlist, restoration]
created: 2026-08-12
blockedBy: []
blocks: []
---

# Scholarship Restoration Watchlist

## Overview

Follow-up to shipped **Scholarship Adjustment Deduction**
([plans/260731-2347-scholarship-adjustment-deduction](../260731-2347-scholarship-adjustment-deduction/plan.md), done)
and **Skip Tuition Generation Pending Scholarship Review**
([plans/260807-0110-...](../260807-0110-skip-tuition-generation-pending-scholarship-review/plan.md), done).

Today an applied adjustment carries forward every subsequent semester
(`GetUnresolvedPriorAdjustmentQuery`) until a `ScholarshipRestorationProposal`
is approved — but approve/reject exists **only as Actions + console command**
(`finance:evaluate-scholarship-restorations`); no HTTP route, no UI, and
restoration is binary (full only). Staff also have no screen listing students
currently carrying an adjustment with the academic evidence (courses,
attendance, GPA, pass/fail verdict) needed to decide.

This plan delivers (decisions locked in brainstorm 2026-08-12):

1. **Partial restoration** — new nullable `restored_amount` on
   `scholarship_restoration_proposals`; carry-forward applies it for later
   semesters. Full restore stays the default (omit field).
2. **Restoration HTTP layer** (Finance-owned): propose / approve / reject
   endpoints reusing permission `approve_scholarship_adjustment` (user
   decision — no new permission).
3. **Watchlist report page** (Academic Progression–owned): students carrying
   adjustments + verdict + GPA + attendance + current courses, decision
   buttons inline. UI copies the defer-return watchlist pattern
   (commits 1fe4f98f/f07f5740).
4. **Generation timing gate**: batch-studio skips tuition generation while a
   restoration proposal is `pending_approval` (mirrors shipped
   "generate XOR reduce" invariant).

## Key Evidence (verified 2026-08-12)

- Carry-forward gate: `app/Modules/Finance/Queries/GetUnresolvedPriorAdjustmentQuery.php:23-47`
  — latest `applied` prior adjustment, excluded when it has an approved proposal.
- Three identical consumers (fallback → `resolveAdjusted`):
  `PreviewMajorChargeGenerationQuery.php:365-375`,
  `CreateFinanceChargeAction.php:214-238`,
  `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php:488-537`.
- Proposal duplicate guard: `CreateRestorationProposalAction.php:39-49` blocks any
  new proposal while one is `pending_approval` OR `approved` (ACTIVE_STATUSES).
- Money semantics: adjustment stores `original_type` (percentage|fixed_amount),
  `original_amount` snapshot, single `adjusted_amount` ∈ [0, original_amount],
  "remaining value not deduction" (migration `2026_08_01_020000`, lines 26-37).
  `ScholarshipDiscountResolver::resolveAdjusted()` clamps [0, base] and never
  above unadjusted (`Support/ScholarshipDiscountResolver.php:58-82`).
- Proposal table has **no money columns** (migration `2026_08_01_050000:14-30`).
- Watchlist backend seeds: `ListPendingRestorationReviewsQuery` (Finance),
  `ScholarshipRestorationVerdictQuery` (Academic Progression — semester is a
  parameter: `verdict(int $studentId, int $semesterId)`),
  `GetStudentAcademicAttendanceSummaryQuery` + `ListStudentCourseRegistrationsQuery`
  (`app/Modules/Academic/Delivery/Queries/`), `GpaCalculation` model.
- Cross-module rule: Finance never imports Academic; cross via
  `App\Shared\Contracts\{Finance,Academic}\*` subdirs only (arch-test enforced).
  Finance contract bindings live in `FinanceServiceProvider.php:88`.
- UI pattern to copy: `GetDeferReturnWatchlistQuery` +
  `AcademicProgressionAuditController` +
  `resources/js/pages/Admin/Reports/AcademicProgressionAudit/DeferReturns.vue`
  + sidebar Academic-reports group (`menu-sidebar.ts:284-289`) + export class
  `Progression/Exports/DeferReturnWatchlistExport.php` + route helpers in
  `resources/js/utils/routes.ts:250-251`.

## Partial Restoration Semantics (locked)

- `restored_amount` decimal(12,2) nullable, **same unit convention as
  `adjusted_amount`** (value of `original_type`; "remaining value").
- NULL ⇒ full restore (backward compatible — all existing approved proposals
  restore fully).
- Validation on propose: `adjusted_amount < restored_amount < original_amount`
  (equal-to-original ⇒ must omit the field = full restore; ≤ adjusted ⇒ not a
  restoration).
- Carry-forward change: exclusion rule becomes "has approved **full**-restore
  proposal". An approved **partial** proposal keeps the row carried, but its
  effective amount = `restored_amount` of the **latest approved** proposal
  (model helper `effectiveAdjustedAmount()`, consumed by `resolveAdjusted`).
- **Repeat proposals** (validated 2026-08-12): after an approved *partial*
  restore, staff MAY propose again at a higher level (further partial or full);
  latest approved wins. Duplicate guard narrows to: block when `pending_approval`
  exists OR an approved **full** restore exists. New partial proposals must be
  `> latest approved restored_amount` (new floor replaces `adjusted_amount`).
- Adjustment row itself is never mutated (status `applied` stays terminal —
  preserves ADR'd invariant "restoration is never a status flip").
- **Console command retired** (validated 2026-08-12): restoration proposals are
  created ONLY via staff UI. `finance:evaluate-scholarship-restorations` +
  scheduler entry removed; `ScholarshipRestorationVerdictQuery` survives as the
  watchlist verdict source.
- **Progressive verdict** (validated 2026-08-12): watchlist verdict = latest
  semester with finalized grades from the adjustment's target semester onward,
  displayed with an "evaluated on semester X" label.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Partial restoration schema + carry-forward math correct across all 3 consumers | P1 |
| 2 | Staff can propose/approve/reject restorations via web (permission `approve_scholarship_adjustment`) | P1 |
| 3 | Watchlist page: carried adjustments + verdict + GPA + attendance + courses, filter/export | P1 |
| 4 | Batch generation skips students with pending restoration proposal (timing invariant) | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Partial restoration schema & carry-forward](./phase-01-start.md) | Done |
| 2 | [Phase 2: Restoration HTTP layer (Finance)](./phase-02-phase-2.md) | Done |
| 3 | [Phase 3: Shared contract & watchlist query](./phase-03-phase-3.md) | Done |
| 4 | [Phase 4: Watchlist UI & decision loop](./phase-04-phase-4.md) | Done |
| 5 | [Phase 5: Generation gate, docs & verification](./phase-05-phase-5.md) | Done |

## Success Criteria

- [x] Approved partial restoration yields next-semester discount computed from `restored_amount` in preview, single-charge, and batch generation (3 consumers verified by tests)
- [x] Approved full restoration (NULL) behaves exactly as today (regression tests green)
- [x] Second proposal after approved partial allowed with floor = latest restored_amount; blocked while pending or after approved full
- [x] Propose/approve/reject reachable via web routes gated by `approve_scholarship_adjustment` + campus scope
- [x] Console command `finance:evaluate-scholarship-restorations` removed (incl. scheduler entry + tests); verdict query retained
- [x] Watchlist lists ALL carrying rows (no proposal / pending / rejected / approved-partial) with progressive verdict + evaluated-semester label, GPA, attendance %, current courses; Excel export works
- [x] Batch-studio preview shows skip reason for pending restoration proposals; generation skips them (gated on all 3 sites: preview, live commit path `GenerateMajorChargesAction`, and the billing-exception repair tool `GenerateBatchChargesAction`)
- [x] Placement arch tests pass (Finance HTTP in Finance module, Progression HTTP in Academic); no Finance→Academic import
- [x] PRD `docs/features/academic/scholarship-adjustment-deduction.md` updated with restoration UI + partial semantics + command retirement

## Known Gaps (shipped, not blocking)

- "Giảm tiếp" link on the watchlist navigates to the dossier list, does not prefill the student — no prefill contract exists on that page today.
- Reject reason is required from staff but only logged, not persisted (no column on `scholarship_restoration_proposals` for it).
- `deferred_scholarship_restoration_pending` stats key (all 3 generation sites) has no reader/notifier yet — unlike its `deferred_scholarship_review` sibling, deferred students aren't notified. Product decision needed.

## Review History

Each phase went through an independent code-review pass before being marked done; real bugs were found and fixed in every phase:
- **Phase 1**: CRITICAL — a partial restoration was leaking into the adjustment's own (already-penalized) target semester instead of only later carry-forward semesters. Fixed by reverting the resolver to read `adjusted_amount` directly and passing the effective amount explicitly only at the 3 carry-forward call sites.
- **Phase 3**: 3 MEDIUM correctness bugs — progressive-verdict lookahead window truncated the wrong end (kept newest system-wide semesters instead of the ones nearest the target), unhandled exceptions could 404 the whole watchlist for one bad row, and tests didn't actually prove the scan-and-stop behavior they claimed to.
- **Phase 4**: unbounded export N+1 (fixed via a course-detail skip flag + trimmed payload), missing confirm dialog on Approve (money-changing, one click, no undo), write actions rendered for read-only users, missing arch placement test.
- **Phase 5**: HIGH — the gate was wired on preview and the billing-exception repair tool, but not on `GenerateMajorChargesAction`, the actual live batch-studio commit path — meaning tuition could still generate at the stale rate through the real UI. Fixed by adding the same gate there, plus a `skipped_count` stats asymmetry and a missing "current-semester adjustment supersedes" guard.

## Validation Log

### Session 1 — 2026-08-12
**Trigger:** post-plan `/ak:plan validate`
**Questions asked:** 6

#### Verification Results
- Claims checked: 18
- Verified: 14 | Failed: 2 (paths only) | Unverified: 0 | Path corrections: 2
- Tier: Full (5 phases)
- Failures (all corrected in plan): contracts live under `Shared/Contracts/{Finance,Academic}/`;
  routes helper is `resources/js/utils/routes.ts`; `GenerateBatchChargesAction`
  under `Actions/Operations/`; DeferReturns.vue under `pages/Admin/Reports/`.
  Behavioral finding: duplicate-proposal guard (`CreateRestorationProposalAction.php:39-49`)
  blocks new proposals after ANY approved one; skip reasons in batch preview are
  free strings, not an enum.

#### Questions & Answers
1. **[Architecture]** Repeat proposal after approved partial? — **Allow** (latest approved wins; guard narrows to pending/approved-full).
2. **[Assumptions]** Watchlist row set? — **All carrying rows**, filter by proposal state.
3. **[Assumptions]** Verdict semester? — **Latest finalized semester ≥ target** with evaluated-semester label (user challenged frozen-target semantics; accepted progressive).
4. **[Scope]** Generation gate P5 now? — **Yes, build now.**
5. **[Architecture]** Extend console command to progressive? — **Retire the command entirely; UI-action-only restoration.**
6. (folded into 3) Both-columns option rejected in favor of single progressive verdict.

#### Confirmed Decisions
- Partial restore: `restored_amount` on proposal, repeatable upward until full.
- Command retirement: UI is the sole proposal entry point.
- Progressive verdict on watchlist.
- P5 gate in scope. Permission reuse `approve_scholarship_adjustment` (from brainstorm).

#### Impact on Phases
- Phase 1: guard narrowing + command removal + floor validation vs latest approved.
- Phase 2: propose endpoint tests for repeat-after-partial; floor bound dynamic.
- Phase 3: verdict = latest finalized semester ≥ target; DTO gains evaluated_semester; include all carrying rows.
- Phase 4: verdict label column; path corrections (pages/Admin/Reports, utils/routes.ts).
- Phase 5: skip-reason as string literal (no enum); path Operations/.

### Whole-Plan Consistency Sweep
Swept plan.md + all 5 phase files after propagation: stale paths corrected
(4 sites), "console command keeps passing no amount" wording removed from
Phase 1, "console command still auto-creates" test dropped, enum-extension risk
note removed from Phase 5. No unresolved contradictions.

## Open Questions

None — all six validation decisions recorded above.

<!-- slug: scholarship-restoration-watchlist -->
