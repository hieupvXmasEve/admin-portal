---
title: "Scholarship Adjustment Deduction"
description: "Per-semester scholarship reduction workflow: Academic dossier (failed courses → interview → maker-checker decision) → Finance applies adjusted discount → portal confirmation → restoration next semester. PRD: docs/features/academic/scholarship-adjustment-deduction.md"
status: in-progress
priority: P1
effort: "3-4w"
tags: [academic, finance, scholarship]
created: 2026-07-31
blockedBy: []
blocks: []
---

# Scholarship Adjustment Deduction

## Overview

Implement "điều chỉnh học bổng theo học kỳ" per PRD [docs/features/academic/scholarship-adjustment-deduction.md](../../docs/features/academic/scholarship-adjustment-deduction.md). Original `StudentScholarshipAward` is never edited; a semester-scoped adjustment record changes the effective discount for one target semester only. Academic owns the dossier/decision; Finance owns the applied discount. Boundary via `App\Shared\Contracts\Finance\ScholarshipAdjustmentContract` + DTO (mirrors `FinanceIntakeContract` pattern), no cross-module model or action imports.

**Module structure (as built, 2026-08-01):** the Academic side lives in the **Progression** sub-module (`app/Modules/Academic/Progression/{Models,Queries,Policies,Actions/ScholarshipAdjustment,Http/Web,Http/Requests,Support}`) — sibling of StudentDecision/WarningCenter (decisions on a student's academic standing). Behavior is expressed as Progression **Actions** (`run()`), not Services (Progression has no Services dir). The Finance side stays in `app/Modules/Finance/`. The student-portal API (P4) is its own global bounded surface (`app/Http/Controllers/Api/V1/Student/*`). A placement arch test (`tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php`) enforces this. P4/P5 phase docs pin exact paths accordingly.

**Settled decisions (brainstorm 2026-07-31, refined by red-team 2026-08-01):**
1. `GIẢM TRỪ HỌC BỔNG` breakdown is **presentational only** — ledger keeps ONE adjusted `InvoiceDiscount` line; breakdown exposed as a separate `scholarship_breakdown` payload key (never injected into the settlement line collection — FeeTab sums those).
2. Confirmation deadline = **1 calendar day** (Asia/Saigon) → `confirmation_overdue`.
3. Manual-add exception approval = permission-gated (no extra approval entity).
4. Restoration = **proposal awaiting approval**, never auto-applied — ENFORCED by a generation-time gate (P5): prior applied adjustment without approved restoration ⇒ the ORIGINAL rate is never silently restored; instead the prior REDUCED rate carries forward provisionally + `finance_review_required` flag (validation 2026-08-01 — user chose carry-forward over full-suppression; supersedes the PRD draft line "không tự kéo dài quyết định cũ" for billing continuity, while the review flag still forces an explicit decision).
5. NO recalc of old `is_passed`. Source/target semester + campus are **explicit required inputs** on every command/UI action (no auto-derived "previous semester" — `semesters.start_date` nullable, no campus column, `is_active` non-unique). Records finalized before the is_passed gate fix (2026-07-04, c5cb7b23) get `needs_data_review = true` → mandatory manual verification, honoring "no recalc".
6. "Trượt môn" excludes EGC (`unit_type = 'egc'`), scope = intake_course students only. `is_passed IS NULL` = not evaluated → excluded and reported. Finalization = `grade_finalized_date IS NOT NULL` (there is no `grade_finalized` column). Appeal exclusion at component level (`assessment_component_detail_scores.appeal_status`) — no record-level appeal entity exists.
7. Status columns = varchar + backend allow-list, NOT DB enums. Scholarship type vocab = `percentage|fixed_amount` (matches definitions enum). Each status column has exactly ONE writing service.
8. Both new core tables carry `campus_id`; approvals re-verified server-side at the record's campus via `CampusPermissionReader` with non-null campus (`can:` alone returns the all-campus union when session campus is null).

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Close authorization drift on ALL scholarship routes incl. bulk financial imports | P1 |
| 2 | Finance: semester-aware scholarship discount across all 5 money surfaces + breakdown + DNG-aware timing guards | P1 |
| 3 | Academic: dossier lifecycle (identify → interview → maker-checker decision) with trustworthy data criteria | P1 |
| 4 | Student portal minutes confirmation (student-only auth, hold-exempt) + overdue handling | P2 |
| 5 | Post-semester restoration proposals with enforceable approval gate | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Authorization Hardening](./phase-01-start.md) | Done (2026-08-01) |
| 2 | [Phase 2: Finance Core Adjustment Model](./phase-02-finance-core-adjustment-model.md) | Done (2026-08-01) |
| 3 | [Phase 3: Academic Dossier Workflow](./phase-03-academic-dossier-workflow.md) | Done (2026-08-01) |
| 4 | [Phase 4: Portal Confirmation](./phase-04-portal-confirmation.md) | Pending |
| 5 | [Phase 5: Restoration Flow](./phase-05-restoration-flow.md) | Pending |

Dependencies: P2 ← P1; P3 ← P2; P4 ← P3; P5 ← P3 (P5 also modifies the P2 apply path via the generation-time gate). P1 shippable alone.

## Key Codebase Anchors (verified 2026-07-31, extended by red-team 2026-08-01)

- `app/Modules/Finance/Support/ScholarshipDiscountResolver.php:29` — `resolve()`, single source of truth, clamps [0, base]; branches `=== 'percentage'`, else flat.
- `app/Modules/Finance/Actions/CreateFinanceChargeAction.php:86-122` — `applyScholarship()`; `<=0` early return at :109; award fetched by student_id only; validity check commented out :96-99.
- `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php:87,379-384,433-441` — whole-loop transaction with swallowed per-student exceptions; scholarship existence short-circuit.
- `app/Modules/Finance/Services/SettlementService.php:445-458` — `createOrRefreshInvoiceDiscount` firstOrNew + amount overwrite.
- `app/Modules/Finance/Services/InvoiceGenerationService.php:208-247` — `applyInvoiceDiscount` transaction + installment reconcile; `ReconcileChargeInstallmentsAction` throws `InstallmentReconciliationException` (:81-103).
- `app/Modules/Finance/Dng/Services/DngReservationLifecycle.php:100-163` — DNG reservations key on invoice lines + settlement position, NOT invoice status; `student_invoices.status` enum = draft|pending|paid|partial|overdue|cancelled (no `issued`, FIN-25).
- `app/Modules/Finance/Models/InvoiceDiscount.php:44-47` — `scholarship()` relation miswired (joins definition id against stored award id) — use adjustment snapshot columns instead.
- `app/Modules/Finance/Queries/GetStudentFeeSummaryQuery.php:274-289` + `:687-720` — TWO discount renderers (`mapInvoiceLines`, `mapInvoiceDiscounts`); `resources/js/pages/Students/AcademicSummary/FeeTab.vue:329` sums `Math.abs` of affects_payable lines.
- Permission registry: `config/permission.php` (`view_scholarship`/`assign_scholarship` at :357-358, `import_student_financial` :359) + `php artisan permissions:sync` + `role_permissions` grants; gates from config iteration (`IdentityServiceProvider.php:65-77`); NO `Gate::before`; campus union when session null (`EloquentCampusPermissionReader.php:24-26`); record-campus policy pattern `StudentApplicationPolicy.php:34`.
- `routes/web/student-scholarships.php:23-35` — ungated imports sub-group; `app/Services/StudentFinancialImportService.php:282-312` updates `scholarship_code` in place.
- `app/Services/FailedStudentsService.php:19,38` — session-campus dependent, NOT reusable in artisan context; write dedicated query.
- `app/Models/AcademicRecord.php:48` — `grade_finalized_date`; `is_passed` nullable (migration 2025_10_30_104153); only writer `CourseCompletionService.php:316`; EGC at :197.
- Appeal columns: `assessment_component_detail_scores` (migration 2025_06_15_109000:96-101) — component grain only.
- Student portal auth: `routes/api/v1/student.php:35` uses `either:parent.student.access,student.api.auth`; `ParentStudentAccess.php:36-57` parent binding; `StudentApiAuthorization.php:100-114` hold-blocks non-profile routes.
- Boundary pattern: `App\Shared\Contracts\Finance\*` + `AcademicFinanceChargeSourceGateway.php:20-22`; arch test `AcademicFinanceBoundaryArchRedProofTest.php:36` (regex too narrow — broadened in P3).
- Workflow template: `app/Models/ExamResitAttempt.php:11-25`. Awards: migration 2025_10_07_100001 — `unique(student_id)`, no status/validity columns; definitions type enum `percentage|fixed_amount`.

## Success Criteria (from PRD acceptance + red-team)

- [ ] Cannot apply adjustment without source semester, target semester, reason, and valid approved decision (server-side re-authorized at campus).
- [ ] Max ONE active adjustment per (student, target semester) — lockForUpdate invariant.
- [ ] Applied value ∈ [0, original]; fingerprint mismatch (award mutated) rejected; no scholarship → no dossier.
- [ ] Full suspension (0) actually zeroes the ledger discount — invoice total changes.
- [ ] Student confirms exact minutes version; edit invalidates; parent tokens rejected; held students not locked out.
- [ ] Approved decisions immutable — corrections via reversal records.
- [ ] Preview == generation across single/batch/major paths, incl. multi-charge invoices.
- [ ] `scholarship_breakdown` renders; settlement line totals unchanged (FeeTab regression).
- [ ] Invoices with active DNG requests or payments never auto-modified → `finance_review_required`; reconciliation refusal never destroys an approval.
- [ ] Restoration enforceable: no approval ⇒ original rate NOT restored (reduced rate carries provisionally + review flag); late charges on old semester keep adjusted amount.
- [ ] Every route/action permission-checked; record-level campus scope on both new tables; import routes gated.
- [ ] Maker ≠ checker enforced UI-side and re-verified Finance-side.

## Testing Notes

- Feature tests need CSRF `_token` (project gotcha).
- `tests/Feature/Finance` baseline: 26 pre-existing failures (2026-06-14) — not regressions.
- Never `--env=testing` for db-mutating artisan (hits dev db `asia`).
- Candidate/evaluation commands must be tested WITHOUT session (artisan context).

## Red Team Review

### Session — 2026-08-01
**Findings:** 15 after dedup from 30 raw (3 reviewers: Security Adversary/Fact Checker, Failure Mode Analyst/Flow Tracer, Assumption Destroyer/Scope Auditor) — 15 accepted, 0 rejected.
**Severity breakdown:** 7 Critical, 6 High, 2 Medium. Fact-check: 22 claims sampled, 7 failed (all corrected).

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | Permission registry path wrong; slugs pre-exist; no role grants → 403 all staff | Critical | Accept | Phase 1 |
| 2 | Import routes ungated; import mutates award in place | Critical | Accept | Phase 1, 2 |
| 3 | `can:` not campus-scoped; null session = all-campus union; tables lacked campus_id | Critical | Accept | Phase 1, 2, 3 |
| 4 | Portal auth: either-middleware admits parents; financial holds lock out target cohort | Critical | Accept | Phase 4 |
| 5 | Suspension=0 never writes ledger (all paths early-return on ≤0) | Critical | Accept | Phase 2 |
| 6 | Timing guard keyed on nonexistent `issued` status; DNG independent of invoice status | Critical | Accept | Phase 2 |
| 7 | `InstallmentReconciliationException` rolls back approval | Critical | Accept | Phase 2, 3 |
| 8 | Restoration approval decorative; `restored` status breaks old-semester lookup | High | Accept | Phase 5, 2 |
| 9 | `is_passed` nullable/stale pre-fix; `grade_finalized` column doesn't exist | High | Accept | Phase 3, plan |
| 10 | "Previous semester" not derivable from schema | High | Accept | Phase 3, 5, plan |
| 11 | Resolver-grep discovery insufficient (batch short-circuit, 2nd mapper, FeeTab double-count) | High | Accept | Phase 2 |
| 12 | Batch swallow-and-commit transaction | High | Accept | Phase 2 |
| 13 | Boundary contract must be Shared\Contracts; arch test regex too narrow | High | Accept | Phase 2, 3 |
| 14 | Grade appeal signal only exists at component level | Medium | Accept | Phase 3, 5 |
| 15 | Data-model vocab: `fixed_amount`, no "active award" state, unique(student_id), miswired relation, multi-charge overwrite, status single-owner | Medium | Accept | Phase 2, 3, 4 |

User decisions preserved: "no recalc is_passed" (guarded via `grade_finalized_date` cutoff + `needs_data_review`, not reversed); "restoration chờ duyệt" (mechanism changed to make it enforceable, intent unchanged).

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01 … phase-05 (all rewritten this session)
- Decision deltas checked: 15 (all findings)
- Reconciled stale references: `grade_finalized`→`grade_finalized_date` (P3, plan anchors); `fixed`→`fixed_amount` (P2, P3, plan); `restored` status removed from P2 lifecycle (P2 status list, P5); "reuse FailedStudentsService" → dedicated query (P3, plan anchors); auto-derived previous semester → explicit args (P3, P5, settled decision 5); ExamResitAttempt anchor retained (template only); permission table split new-vs-existing (P1); `scholarship_code` dropped from uniqueness invariant (P2); dossier ref now required `academic_dossier_id` (P2)
- Unresolved contradictions: 0

## Validation Log

### Session 1 — 2026-08-01
**Trigger:** user-selected `/ak:plan validate` after red-team. Verification pass skipped per guard (Red Team Review already carries 22-claim fact-check).
**Questions asked:** 4

#### Questions & Answers

1. **[Architecture]** P5 generation-time gate: sinh học phí kỳ kế tiếp khi adjustment kỳ trước chưa có quyết định khôi phục được duyệt — xử lý học bổng thế nào?
   - Options: Không áp học bổng + flag review (Recommended) | Tạm áp mức ĐÃ GIẢM + flag review | Chặn sinh invoice
   - **Answer:** Other — "vẫn giữ quyết định giảm trừ" (= carry forward the reduced rate)
   - **Rationale:** billing continuity for students; original rate still cannot silently return; review flag forces explicit close-out. Supersedes PRD draft "không tự kéo dài quyết định cũ" — explicit user decision.
2. **[Risks]** P2 import guard khi import sửa scholarship_code của SV có adjustment active?
   - Options: Từ chối row (Recommended) | Cho sửa + flag finance_review
   - **Answer:** Từ chối row.
3. **[Scope]** P1 role grants cho 4 permission hiện hữu?
   - Options: Mirror role Finance liền kề (Recommended) | Chỉ super_admin trước | Danh sách role cụ thể
   - **Answer:** Chỉ super_admin trước — chấp nhận staff hiện tại bị 403 cho tới khi được grant thêm; cần thông báo rollout.
4. **[Scope]** P3 identification tự động hay thủ công?
   - Options: Thủ công cho pilot Fall 2026 (Recommended) | Scheduled
   - **Answer:** Thủ công cho pilot; propagated tới P5 evaluation (cũng manual-first, scheduler để sau).

#### Confirmed Decisions
- Gate P5 = carry-forward reduced rate + `finance_review_required` — implement as provisional resolution from the prior adjustment's `adjusted_amount`, discount memo references source adjustment id.
- Import row hard-refused when active adjustment exists.
- Pilot grants: super_admin only (UpdatePermissionsSeeder already covers); staff roles granted later by explicit request.
- All identification/evaluation runs manual with explicit args during pilot.

#### Impact on Phases
- Phase 5: gate semantics rewritten (carry-forward), evaluation command manual-first.
- Phase 1: role-grant step reduced to super_admin + rollout-comms note; smoke test expectation changed (staff 403 is EXPECTED post-P1).
- Phase 3: explicit "no scheduler in pilot" note.
- Phase 2: import guard fixed to hard-refuse (was refuse-or-flag).

### Whole-Plan Consistency Sweep (validation session 1)
- Files reread: plan.md, phase-01, phase-02, phase-03, phase-04, phase-05
- Decision deltas checked: 4
- Reconciled stale references: suppress-scholarship gate wording (plan settled decision 4, success criteria, phase-05 overview/requirements/steps/criteria); mirror-role grant step (phase-01); refuse-or-flag import (phase-02); P5 scheduler (phase-05)
- Unresolved contradictions: 0

## Open Questions

None.

<!-- slug: scholarship-adjustment-deduction -->
