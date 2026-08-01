---
phase: 3
title: "Phase 3: Academic Dossier Workflow"
status: done
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
- Create: `app/Modules/Academic/Http/Web/ScholarshipAdjustmentDossierController.php` (Academic-owned — module HTTP, NOT global `app/Http/Controllers`)
- Create: `app/Modules/Academic/Http/Requests/ScholarshipAdjustment/*.php` (6 FormRequests, module namespace)
- Create: `app/Modules/Academic/Policies/ScholarshipAdjustmentDossierPolicy.php`
- Register routes in `app/Modules/Academic/routes/web.php` (module route file — NOT a global `routes/web/*` shell)
- Create: Inertia pages `resources/js/pages/ScholarshipAdjustments/*`
- Modify: `tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php` — broadened regexes
- Create: `tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php` — HTTP + routes stay in the module (placement guard)
- Reuse: `ScholarshipAdjustmentContract` (P2), notification event pattern

> Placement note (2026-08-01): HTTP layer was initially mislanded in global
> `app/Http/Controllers` + `app/Http/Requests` by name-matching the pre-existing
> global `ScholarshipController`. Moved into the Academic module; a placement
> arch test now enforces it. See `.claude/rules/development-rules.md` →
> "File Placement & Module Ownership".

## Implementation Steps

1. Migration + model + allow-lists + factory.
2. Candidate query object + service + command; tests: EGC excluded, NULL is_passed excluded+reported, unfinalized excluded, override excluded, component-appeal excluded, no-scholarship excluded, campus isolation, explicit-args validation (null start_date rejected), idempotency, aggregation, `needs_data_review` flag for pre-fix records, runs under artisan with NO session.
3. Interview lifecycle + minutes version++ on edit.
4. Decision service: guards, maker≠checker, exception path, contract call, Finance-first outcome mapping; tests incl. maker==checker rejection and `finance_review_required` mapping.
5. Routes + policy + FormRequests.
6. Inertia UI.
7. Arch test broadening + notifications.

## Todo

- [x] Migration/model
- [x] Candidate query + command + tests (sessionless) — 13 tests
- [x] Interview lifecycle — 6 tests (incl. reopen/frozen-minutes guards added post-review)
- [x] Decision + contract handoff — 15 tests (incl. terminal-status/defer/pending_apply-mapping guards added post-review)
- [x] Routes/policy/UI — minimal Inertia pages (Index/Show); 3 HTTP authorization tests
- [x] Arch test broadened (both AcademicFinanceBoundaryArchRedProofTest and the pre-existing AcademicFinanceBoundaryArchTest verified consistent)
- [ ] Notifications — DEFERRED (see Completion Notes)

## Completion Notes (2026-08-01)

35 tests green. Code review (code-reviewer subagent) found 2 CRITICAL + 6 HIGH + several MEDIUM issues, all fixed except where noted:

**Fixed:**
- CRITICAL: `index()` was not campus-scoped (cross-campus dossier listing) → added `where('campus_id', $campusId)` from `app('campus')`/session, 403 on no campus.
- CRITICAL: `campus_id` (identify) / `student_code`-derived campus (addManually) were trusted from the request without re-verifying the permission AT that specific campus → both FormRequests now call `CampusPermissionReader` at the real campus, not session campus.
- HIGH: `decide()` had no terminal-status guard — an applied/approved/cancelled dossier could be re-decided and re-approved → added `TERMINAL_DECISION_STATUSES` guard.
- HIGH: Interview service could reopen a decided dossier (schedule/no-show/complete had no status guard) → added `guardPreDecision()`; `editMinutes` frozen past the decision stage.
- HIGH: Finance's `pending_apply` (no invoice yet — nothing actually applied) was mapped to dossier status `applied` → now maps to `approved` (money not yet moved); only Finance's literal `applied` status maps to `STATUS_APPLIED`.
- HIGH: Finance rejection reason/message was discarded on handoff failure → now logged and appended to `decision_reason`.
- MEDIUM: `approve()` had no row lock → wrapped in `DB::transaction` + `lockForUpdate()`.
- MEDIUM: `handOffToFinance` used `firstOrFail()` after the `approved` write (could 404-strand an approved dossier) → `first()` + review-status fallback.
- MEDIUM: `addManually` mislabeled ALL academic records (including passed ones) as "failed courses" evidence → filtered to `is_passed = false`; also `firstOrFail()` → `DomainException` for missing student/award.
- MEDIUM: unbounded global scan of pending component appeals → scoped to the run's candidate course-offering IDs.
- MEDIUM/LOW: duplicated is_passed-gate-fix cutoff date → `addManually` now reuses `ScholarshipAdjustmentCandidateQuery::needsDataReview()`.
- `defer` decision type: no terminal state was defined for it → explicitly rejected at `decide()` until a future phase defines its resolution (was silently resolving to `no_adjustment`).
- Added 6 HTTP-layer authorization tests + additional service-level guard tests to actually exercise the fixed paths (review noted zero HTTP tests existed).

**Deferred (recorded, not built this phase):**
- Notification wiring (`EnrollmentConfirmed`-pattern events for interview scheduled / minutes ready / decision approved) — plan calls for it but out of the money-adjacent critical path; add as a follow-up.
- Sidebar menu entry for the new routes.
- UI gaps: `Show.vue` has no `exception_override_reason` input (exception path unreachable from UI, reachable via service/API) and no `mode`/`location` fields beyond a hardcoded default; server-side errors aren't rendered in the Vue pages (flash/error prop not read). Backend enforcement is complete and tested; these are UX follow-ups.
- No unique DB index on `(student_id, source_semester_id, target_semester_id)` — the one-dossier-per-triple invariant is service-level check-then-create, matching Phase 2's pattern for the Finance table (MySQL has no partial unique index); a race on `identify()`/`addManually()` could theoretically double-create. Accepted at pilot scale, same risk class as Phase 2's adjustment invariant.

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
