---
phase: 3
title: "Phase 3: Academic Dossier Workflow"
status: todo
priority: P1
effort: "6d"
dependencies: [2]
---

# Phase 3: Academic Dossier Workflow

## Overview

Academic-owned dossier lifecycle: identify candidates from failed courses of an explicitly specified source semester, schedule/record interview, maker-checker decision, then hand approved reductions to Finance via the Shared contract. Staff UI included.

<!-- red-team 2026-08-01: dedicated campus-arg query (no session reuse); explicit semester args; grade_finalized_date + NULL policy; component-level appeal query; Shared contract call; single status owner; campus_id column -->

## Requirements

- Functional:
  - Candidate identification (system) + manual add by MSSV with exception reason (permission-gated).
  - One dossier per student per (source, target) pair — aggregated, failed-course list as evidence.
  - Interview record with reschedule, no-show, minutes + versioning.
  - Decision outcomes: keep | reduce | suspend_full | defer | cancel. Maker ≠ checker (UI-level; Finance re-verifies server-side per P2).
  - Approved reduce/suspend → call `App\Shared\Contracts\Finance\ScholarshipAdjustmentContract` (bound interface + DTO — mirror `AcademicFinanceChargeSourceGateway.php:20-22`). NEVER import `App\Modules\Finance\Actions\*` or Models from Academic.
- Non-functional: statuses varchar + allow-list; auditable base model; every query/route campus-scoped via explicit campus argument (never `session()` inside services/commands).

## Architecture

**Table `scholarship_adjustment_dossiers`** (Academic module):

```
id
student_id            FK students
campus_id             FK campuses    // snapshot from students.campus_id; policy checks compare against actor's campus permission
source_semester_id    FK semesters   // explicit input, never derived
target_semester_id    FK semesters   // explicit input
status                string(40)
source                string(20)     // system|manual
manual_exception_reason text nullable
failed_courses_snapshot json          // [{unit_code, unit_name, attempt_no, academic_record_id, grade_finalized_date}]
original_scholarship_code string(50)
original_type         string          // percentage|fixed_amount
original_amount       decimal(12,2)
needs_data_review     boolean default false  // true when any source record was finalized before the is_passed gate fix — routes to manual review, honoring "no recalc"
-- interview (single embedded record; reschedules audited via model history)
interview_status      string(30)     // not_scheduled|scheduled|completed|student_no_show|rescheduled|cancelled
interview_scheduled_at datetime nullable
interview_mode        string(20) nullable  // in_person|online
interview_location    string nullable
interview_staff_id    FK users nullable
interview_participants json nullable
interview_agenda      text nullable
minutes               text nullable
minutes_version       unsignedInteger default 0
-- decision
decision_type         string(30) nullable  // keep|reduce|suspend_full|defer|cancel
decision_adjusted_amount decimal(12,2) nullable
decision_reason       text nullable
decision_estimated_impact decimal(12,2) nullable
proposed_by_user_id, approved_by_user_id FK users nullable
decided_at, approved_at datetime nullable
created_by_user_id    FK users
timestamps
Index (student_id, source_semester_id, target_semester_id) + service invariant: one non-cancelled dossier per triple.
```

**Status machine** (single writer rule: ONLY `ScholarshipAdjustmentDecisionService` mutates `status`; other services — confirmation handler, overdue command — call into it, never write the column):

```
identified → interview_scheduled → interviewed → awaiting_student_confirmation
  → ready_for_decision → approved → applied → closed
branches: student_disputed, student_no_show, confirmation_overdue,
          no_adjustment, cancelled, finance_review_required
```

Guard: decision blocked until `interview_status = completed`, EXCEPT exception path requiring reason + `approve_scholarship_adjustment` holder.

**Identification service** `ScholarshipAdjustmentCandidateService` — DEDICATED query object, NOT a reuse of `FailedStudentsService` (that service reads `session('current_campus_id')` at :19 and hard-filters on it at :38 — under artisan the session is null and `campus_id = NULL` matches zero rows; it also lacks unit-type, finalization, and override predicates):

- Signature: `identify(int $campusId, int $sourceSemesterId, int $targetSemesterId): CandidateResult` — campus explicit, semesters explicit.
- Semester inputs validated: both exist, non-archived, `start_date IS NOT NULL`, source.start_date < target.start_date. NO auto-derivation of "previous semester" (`semesters.start_date` is nullable, no campus column, `is_active` non-unique — migration `2025_05_27_101351`:19-23).
- Criteria (single query, joins spelled out):
  - `academic_records.is_passed = false` AND `academic_records.is_passed IS NOT NULL` — NULL means not evaluated: excluded AND counted/reported in the run summary;
  - `academic_records.grade_finalized_date IS NOT NULL` (column verified `AcademicRecord.php:48` — there is no `grade_finalized`);
  - `academic_records.override_pass = false` (migration `2025_10_30_104153`);
  - join `units` → `unit_type != 'egc'`; student scope = intake_course;
  - `academic_records.campus_id = $campusId`;
  - active award: `student_scholarship_awards` row exists AND its `scholarship_definitions` is active/valid covering target semester `start_date` (validity columns exist on definitions; the global apply-time check is commented out at `CreateFinanceChargeAction.php:96-99` — this criterion is scoped to identification only, no behavior change elsewhere);
  - open-appeal exclusion at COMPONENT level (no record-level appeal entity exists): exclude the record if any related `assessment_component_detail_scores.appeal_status` is pending for the offering (columns: migration `2025_06_15_109000`:96-101). Spell out the join in the query object; excluded students reported.
  - continuation in target semester: enrollment/registration exists for target semester (use existing enrollment query).
- Data-trust flag: if any qualifying record's `grade_finalized_date` < 2026-07-04 (`is_passed` gate fix, commit c5cb7b23) → set `needs_data_review = true` on the dossier; decision UI shows a mandatory manual verification banner. Honors settled decision "no recalc" while not deciding money on known-suspect flags.
- Aggregate per student → one dossier; snapshot failed courses. Idempotent re-runs.
- Command: `academic:identify-scholarship-adjustment-candidates --campus= --source-semester= --target-semester=` (all required) + staff UI action with the same explicit inputs. Manual trigger ONLY for the pilot — no scheduler (validation session 1).
- Manual add: MSSV input; auto-criteria unmet → require `manual_exception_reason`, gate `manage_scholarship_adjustment_candidate`.

**Decision flow:** maker (`decide_scholarship_adjustment`) records decision → checker (`approve_scholarship_adjustment`, different user, service-enforced) approves → for reduce/suspend_full build `ScholarshipAdjustmentData` DTO (includes dossier id, maker/checker ids, campus_id, award fingerprint inputs) → call contract. **Finance first, then Academic**: dossier status is written FROM the contract's returned outcome (`applied` | `finance_review_required`) — an approval can never be rolled back by a ledger refusal (P2 handles `InstallmentReconciliationException` internally). keep/cancel → `no_adjustment`/`cancelled`. Approved decisions immutable — corrections via reversal decision calling Finance reversal.

**Arch test:** extend `tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php` — current regex (:36) only forbids `App\Modules\Finance\Models\`; broaden to forbid ANY `App\Modules\Finance\` import from Academic dossier code (contract namespace `App\Shared\Contracts\` allowed), and add `App\Models\AcademicRecord` to the Finance-side forbidden list (:56-57) so Phase 5 cannot invert the boundary.

**Notifications:** domain events (`EnrollmentConfirmed` pattern) → `NotificationMessage`: interview scheduled, minutes ready, decision approved.

**UI (staff, Inertia):** dossier list (status, semester, campus filters) + detail (evidence incl. `needs_data_review` banner, interview panel, minutes editor, decision panel). Routes `routes/web/scholarship-adjustments.php` gated with Phase 1 permissions; record-level campus policy per `StudentApplicationPolicy.php:34` pattern (non-null campus required).

## Related Code Files

- Create: migration `create_scholarship_adjustment_dossiers_table`
- Create: `app/Modules/Academic/Models/ScholarshipAdjustmentDossier.php`
- Create: `app/Modules/Academic/Services/ScholarshipAdjustmentCandidateService.php` (+ dedicated query object)
- Create: `app/Modules/Academic/Services/ScholarshipAdjustmentDecisionService.php` (single status writer)
- Create: `app/Console/Commands/IdentifyScholarshipAdjustmentCandidates.php`
- Create: controllers + FormRequests + `routes/web/scholarship-adjustments.php` + policy
- Create: Inertia pages `resources/js/pages/scholarship-adjustments/*`
- Modify: `tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php` — broadened regexes
- Reuse: `ScholarshipAdjustmentContract` (P2), notification event pattern

## Implementation Steps

1. Migration + model + allow-lists + factory.
2. Candidate query object + service + command; tests: EGC excluded, NULL is_passed excluded+reported, unfinalized excluded, override excluded, component-appeal excluded, no-scholarship excluded, campus isolation, explicit-args validation (null start_date rejected), idempotency, aggregation, `needs_data_review` flag for pre-fix records, runs under artisan with NO session.
3. Interview lifecycle + minutes version++ on edit.
4. Decision service: guards, maker≠checker, exception path, contract call, Finance-first outcome mapping; tests incl. maker==checker rejection and `finance_review_required` mapping.
5. Routes + policy + FormRequests.
6. Inertia UI.
7. Arch test broadening + notifications.

## Todo

- [ ] Migration/model
- [ ] Candidate query + command + tests (sessionless)
- [ ] Interview lifecycle
- [ ] Decision + contract handoff
- [ ] Routes/policy/UI
- [ ] Arch test + notifications

## Success Criteria

- [ ] Identification correct + deterministic with explicit (campus, source, target); zero-session artisan run produces identical results to UI run.
- [ ] NULL `is_passed`, pre-finalization, override, component-appeal, EGC, no-scholarship all excluded — each with a test.
- [ ] Pre-fix-date records flagged `needs_data_review`; decision UI enforces manual verification banner.
- [ ] Cannot decide before interview completed (except documented exception path).
- [ ] Maker ≠ checker enforced; broadened arch test proves no `App\Modules\Finance\` import in Academic (and no `AcademicRecord` in Finance).
- [ ] Dossier status has exactly one writer (grep-able assertion or arch test).

## Risk Assessment

- Grade appeal succeeding after apply → `cancel` + Finance reversal path; component-level exclusion narrows the window.
- Semester misconfiguration (null start_date shells) → rejected at input validation, not silently ordered.
- Status drift between dossier and adjustment → dossier status derives from contract outcome; single-writer rule.
