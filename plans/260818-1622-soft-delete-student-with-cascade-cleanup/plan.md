---
title: "Soft-delete student with cascade cleanup"
description: "Add a permission-gated soft-delete for students on /students, hiding the student and its directly-owned data without destroying financial/academic history."
status: pending
priority: P1
effort: "2-3d"
tags: [students, student-registry, permissions, soft-delete]
created: 2026-08-18
---

# Soft-delete student with cascade cleanup

## Overview

`students.deleted_at` exists (migration `2025_05_28_120000_create_students_table.php:61`) but `Student` model has no `SoftDeletes` trait — `Student::delete()` today is a hard delete. No `destroy` route/controller method exists on `/students`. `config/permission.php:170` already defines `delete_student`, but it is unused (orphan permission, wired nowhere).

There is also a dead, dangerous method: `App\Services\StudentService::deleteStudent()` (`app/Services/StudentService.php:546`) that **force-deletes** the student and a partial list of 8 relation types, swallows every failure with `Log::warning` (silent-failure anti-pattern), and has **zero callers** (grep confirmed — not wired to any route, not tested). It must be removed, not extended.

`students.id` is referenced by ~42 tables across Academic/Finance/Engagement/Merchandise modules (scouted via migration grep). Only 5 of them are themselves soft-deletable (`academic_records`, `assessment_component_detail_scores`, `attendances`, `gpa_calculations`, `responses`); the rest (payments, finance_charges, billing_accounts, student_invoices, scholarship_*, defer_cases, egc_blocks, gold_transactions, dng_payment_requests, voucher_*, student_action_logs, ...) have no soft-delete column at all. Per this repo's own doctrine (immutable finance ledger, audit trail — see `docs/`), those rows must never be hard-deleted and should not be mass-mutated either.

**Design decision (validated + red-teamed):** soft-delete the `Student` row only. "Related data cascade" = *logical* hiding via the parent's `deleted_at`, not physical mutation of 42 child tables. Any read path that queries students outside Eloquent (raw `DB::table('students')`) must be audited to exclude soft-deleted rows, matching the one place that already does this manually (`EloquentCurriculumStudentSummaryReader`).

<!-- Updated: Red Team Session 1 - Finding 4 -->
**Correction from red-team:** the naive version of this design is wrong in one direction. `SoftDeletes`' global scope doesn't just hide the student from `Student::query()` — it also nulls the *inbound* side (`$payment->student`, `$attendance->student`, ...) across 44 `belongsTo(Student::class)` relations, breaking 117+ call sites that render a deleted student's historical records (payment lists, attendance sheets, grade reports). Phase 2 now adds `->withTrashed()` to those 44 relation definitions so history keeps rendering for anyone who already has the child record open, while directory/list/search (which go through `Student::query()` directly) still correctly excludes the deleted student.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | `Student` model supports real soft-delete; all directory/search/lookup read paths hide soft-deleted students | P1 |
| 2 | New `delete_student` permission (already defined) enforced on a new destroy route, following the `can:<code>` middleware pattern | P1 |
| 3 | Soft-delete goes through a StudentRegistry module Action (not the legacy `StudentService` god-service), in a DB transaction, with an activity/financial guard decision point | P1 |
| 4 | `/students` page gets a permission-gated Delete row action with confirm dialog, using existing `useGlobalConfirmDialog` + `router.delete` convention | P1 |
| 5 | Dead `StudentService::deleteStudent()` force-delete method removed | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Enable soft-delete on Student model](./phase-01-start.md) | Pending |
| 2 | [Phase 2: Audit and fix raw read paths for deleted_at](./phase-02-enable-soft-delete-on-student-model.md) | Pending |
| 3 | [Phase 3: Delete action, permission, guard rails](./phase-03-delete-action-permission-guard-rails.md) | Pending |
| 4 | [Phase 4: Frontend delete action on students page](./phase-04-frontend-delete-action-on-students-page.md) | Pending |
| 5 | [Phase 5: Remove dead force-delete code and tests](./phase-05-remove-dead-force-delete-code-and-tests.md) | Pending |

## Success Criteria

- [ ] `Student` uses `SoftDeletes`; `Student::find($id)` returns null after delete, `Student::withTrashed()->find($id)` still resolves
- [ ] `DELETE /students/{student}` requires `delete_student` permission (403 without it) **and** matches the acting user's session campus (404 on cross-campus attempt), soft-deletes in a transaction, redirects back with flash message
- [ ] Guard blocks deletion when the student's finance position is missing, invalid, or has a non-zero balance in either direction (owed or overpaid) — fails **closed**, never open
- [ ] No raw/non-Eloquent student query surfaces a soft-deleted student in directory, search, dropdown, or report read paths (except audit/integrity commands, which intentionally keep seeing trashed students)
- [ ] The 44 `belongsTo(Student::class)` relations resolve `withTrashed()` so historical child records (payments, attendance, grades) keep rendering after a student is deleted
- [ ] `/students` index shows a Delete action only for users with `delete_student`, gated behind a confirm dialog
- [ ] `StudentService::deleteStudent()` removed; no remaining references
- [ ] `RevokeStudentIdentityAction` switched to `forceDelete()` — pre-admission revocation stays a true hard delete, unaffected by the new soft-delete trait
- [ ] Deleting a student tombstones `email`/`student_id`/`national_id` so the unique indexes don't permanently block re-creation or Admissions re-approval
- [ ] Deleting a student revokes both the `Student`'s own Sanctum tokens and the linked `users` account's access (`StudentAccessWriter::revoke`)
- [ ] Feature tests (in Phase 3, not deferred) cover: permission denial, cross-campus denial, successful soft-delete, guard-block (outstanding balance, missing billing account, invalid position), post-delete invisibility in list/search, token+access revocation, tombstoned identity allows re-creation

## Open Questions

None — resolved in `/ak:plan validate` session 1 below.

## Validation Log

### Session 1 — 2026-08-18
**Trigger:** initial plan review before implementation
**Questions asked:** 5

#### Questions & Answers

1. **[Risk]** Guard rail: block soft-delete when student has unresolved financial obligations or academic activity, or allow delete regardless (just warn in UI)?
   - Options: Block if outstanding balance (Recommended) | Block on any activity | No guard, warn only
   - **Answer:** Block if outstanding balance
   - **Rationale:** Blocking on *any* academic/financial activity (the `RequireRevocableStudentIdentityAction` precedent) would make delete unusable for real enrolled students — that precedent exists for zero-activity pre-admission records, not this feature. Blocking only on an unpaid balance protects the one thing that's actually dangerous to lose (money owed) while allowing normal admin cleanup of students with academic history.
2. **[Scope]** Which roles get `delete_student` by default in `RoleAndPermissionSeeder`?
   - **Answer:** None via seeder — user will assign the permission to roles manually through the admin UI after the permission exists.
   - **Rationale:** Permission code already exists in `config/permission.php:170` and syncs into the `permissions` table via `UpdatePermissionsSeeder` regardless of role assignment; role-to-permission grants are an operational/admin-UI concern, not a code change.
3. **[Tradeoff]** Cascade depth: student row only vs also cascading into the 5 soft-deletable child tables?
   - **Answer:** Student row only
   - **Rationale:** Smallest blast radius, fully reversible, matches this plan's original recommendation.
4. **[Scope]** Restore action / trashed-students view in this plan, or defer?
   - **Answer:** No restore UI for v1
   - **Rationale:** YAGNI — DB-level recovery via `withTrashed()`/tinker is sufficient until restore is actually needed.
5. **[Risk]** On delete, revoke the student's portal login (Sanctum tokens) immediately, or leave untouched?
   - **Answer:** Revoke immediately
   - **Rationale:** `Student` itself uses `HasApiTokens` (it's the portal-login authenticatable), so a deleted student could otherwise keep an active session/API token indefinitely.

#### Confirmed Decisions
- Guard: block delete only when the student's billing account has an outstanding (unpaid) balance — checked via `SettlementPositionReader::forBillingAccount()` (`app/Shared/Contracts/Finance/SettlementPositionReader.php`), not the all-or-nothing activity check.
- `RoleAndPermissionSeeder` is **not** touched by this plan — permission sync only, role grants done manually post-deploy.
- Cascade stays parent-only (no change from original plan default).
- No restore/trashed UI in this plan.
- `SoftDeleteStudentAction` also revokes the student's Sanctum tokens (`$student->tokens()->delete()`) in the same transaction.

#### Impact on Phases
- Phase 3: guard implementation now concrete (SettlementPositionReader-based balance check), role-seeder step removed, token revocation added to the action.

### Verification Results
- Claims checked: `config/permission.php:170` (delete_student exists), `app/Services/StudentService.php:546` (deleteStudent method + zero callers), `app/Modules/StudentRegistry/Actions/RequireRevocableStudentIdentityAction.php` (activity guard precedent), `app/Models/Student.php` (HasApiTokens, no SoftDeletes), `students` migration `deleted_at` column, `app/Shared/Contracts/Finance/SettlementPositionReader.php::forBillingAccount()` — all confirmed by direct `Read`/`grep` during research, not inferred.
- Verified: 6 | Failed: 0 | Unverified: 0 (SettlementPosition's exact balance field name not yet inspected — flagged in Phase 3 as an implementation-time check, not a planning blocker)
- Tier: Full (5 phases)

### Whole-Plan Consistency Sweep
- Re-read `plan.md` + all 5 phase files after propagation: no stale references to the removed "block on any activity" option, no leftover mentions of role-seeder changes, no restore-UI scope creep. Zero unresolved contradictions.

## Red Team Review

### Session 1 — 2026-08-18
**Findings:** 15 (15 accepted, 0 rejected)
**Severity breakdown:** 5 Critical, 6 High, 4 Medium
**Reviewers:** Security Adversary (Fact Checker), Failure Mode Analyst (Flow Tracer), Assumption Destroyer (Scope Auditor) — 3 parallel hostile reviews, evidence deduplicated across all three.

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | `destroy()` has no campus check — route-model binding resolves any campus's student | Critical | Accept | Phase 3 |
| 2 | Balance guard fails open: no `billing_accounts` row for pre-Jul-2026 students → guard skipped | Critical | Accept | Phase 3 |
| 3 | `SettlementPosition::outstandingAmount()` doesn't exist; real accessor (`amounts->remaining`) is nullable | Critical | Accept | Phase 3 |
| 4 | `SoftDeletes` global scope nulls inbound `belongsTo(Student::class)` across 44 models / 117 sites | Critical | Accept | Phase 1, Phase 2 |
| 5 | `RevokeStudentIdentityAction` silently becomes soft-delete, breaks Admissions revoke→re-approve | Critical | Accept | Phase 1 |
| 6 | Token revocation misses the real login path — `users`-table `AuthController` login survives | High | Accept | Phase 3 |
| 7 | Phase 2's raw-query audit greps miss ~10-15 real sites | High | Accept | Phase 2 |
| 8 | `deleted_at` conflicts with existing `students.status` lifecycle, no precedence documented | High | Accept | Phase 2, Phase 3 |
| 9 | Unique indexes (`student_id`/`email`/`national_id`) survive soft-delete, block re-creation | High | Accept | Phase 3 |
| 10 | No pre-deploy check for existing non-null `deleted_at` rows, no rollback note | High | Accept | Phase 1 |
| 11 | Tests deferred to P2 Phase 5, which cites a nonexistent test directory | High | Accept | Phase 3, Phase 5 |
| 12 | Guard is check-then-act with no row lock (`SettlementMutationGuard` convention not followed) | Medium | Accept | Phase 3 |
| 13 | Guard only blocks debt owed by the student, ignores credit owed to them | Medium | Accept | Phase 3 |
| 14 | `destroy()` response contract contradictory between Phase 3 (redirect) and Phase 4 (partial reload) | Medium | Accept | Phase 3, Phase 4 |
| 15 | `catch(\Exception)` pattern leaks raw Finance-layer exception detail to the browser | Medium | Accept | Phase 3 |

### Whole-Plan Consistency Sweep
- Files reread: `plan.md`, `phase-01` through `phase-05` after applying all 15 findings.
- Decision deltas checked: guard redesigned fail-closed (Findings 2/3/12/13), campus check added (1), inbound-relation `withTrashed()` policy added (4), `RevokeStudentIdentityAction` pinned to `forceDelete()` (5), token+access revocation corrected (6), Phase 2 grep broadened + file list embedded (7), status-lifecycle interaction documented as orthogonal + fee-generation verification step added (8), identity tombstoning added (9), pre-deploy data check added (10), tests moved into Phase 3 + path corrected to `tests/Feature/Registry/` (11), response contract pinned to `back()` + flash (14), typed exception for guard errors (15).
- Reconciled stale references: removed the `outstandingAmount()` sketch and "Open Question 1" phrasing from Phase 3 (already resolved in Validation Session 1, now further corrected by red-team); removed "matching store/update conventions" redirect wording from Phase 3; fixed Phase 5's test path.
- Unresolved contradictions: 0

<!-- slug: soft-delete-student-with-cascade-cleanup -->
