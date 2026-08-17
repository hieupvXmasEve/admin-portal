---
phase: 1
title: "Flip at source: StudentRegistry store"
status: done
priority: P1
effort: 4h
dependencies: [0]
---

> **Done 2026-08-17.** H8 decision: option (a) — `findSerialized()` /
> `findManySerialized()` override the `status` key from the batch resolution;
> `findProfile()` needed no change (no `status` field). H3 delta measured
> before the change: `activeIdsForCampus(null)` **175 → 180** (+8 / −3), ids
> added `[431,523,533,595,600,617,619,638]`, removed `[466,469,657]` — matches
> the plan's predicted 175→180 exactly.
>
> One N+1 site not named in H7 turned up in testing:
> `EnrollStudentInCourseOfferingAction::run()` calls `find()` internally, and
> both its callers (`BulkRegisterCourseOfferingStudentsAction`,
> `RegisterStudentForActiveSemesterUnitsAction`) loop it per student. Fixed by
> adding an optional `?StudentReference $resolvedStudent` parameter (default
> null, fully backward compatible) so the bulk caller passes its
> already-batch-resolved reference instead of triggering a second per-row
> query. Only `BulkRegisterCourseOfferingStudentsAction` was updated to pass
> it — `RegisterStudentForActiveSemesterUnitsAction` is out of this phase's
> file ownership and left as-is (not flagged by any acceptance criterion).

# Phase 1: Flip at source (StudentRegistry store)

## Overview

`EloquentStudentRegistryStore` is the single implementation behind
`StudentReferenceReader`. Every `StudentReference.status` comes from
`reference()` or `joinedReference()`. Flipping those fixes ~20 consumers —
including all 5 Delivery registration gates — with zero edits to them.

Red-team additions: **serialized output** (H8), **bulk N+1 under an open
transaction** (H7), and a **rollback that does not roll back** (H3).

## Requirements

- [x] `StudentReference.status` resolves from `StudentLifecycleStatusReader`; the reader's own fallback handles no-enrollment students. Do not re-implement the fallback.
- [x] `statusLabel` from `Student::statusLabelFor()` on the **resolved** status.
- [x] **No N+1** on `findMany()`, `search()`, `stream()`, **or bulk loops calling `findByStudentCode()`** (H7).
- [x] `activeIdsForCampus()` uses the phase-0 Shared scope (not hand-rolled SQL).
- [x] Serialized readers decided explicitly (H8) — no silent split-brain.
- [x] `StudentReference` DTO shape unchanged.
- [x] Rollback plan accounts for the writes `activeIdsForCampus()` triggers (H3).

## Architecture

### Dependency direction

Constructor-inject `App\Shared\Contracts\Academic\StudentLifecycleStatusReader`.
Shared contract ⇒ passes `DomainBoundaryArchitectureTest` (its regex only
matches `App\Modules\…`). Binding exists at
`app/Modules/Academic/Providers/AcademicServiceProvider.php:161`; the store is
bound class-string at
`app/Modules/StudentRegistry/Providers/StudentRegistryServiceProvider.php:46-47,54-55`,
so Laravel auto-resolves. **No provider edit.** No cycle: the reader has no
constructor.

### Call paths and N+1 requirement

| Path | Rows | Required |
|---|---|---|
| `find()` :23, `findByStudentCode()` :166, `findByStudentCodeAnywhere()` :178 | 1 | 1 extra query. Accepted. |
| `findMany()` :142 | N | resolve all ids **once** before mapping |
| `search()` :251 | ≤ limit | resolve once before mapping |
| `stream()` :200 | unbounded, `lazy(500)` | switch to chunked iteration, resolve **per chunk** |

Signature change keeps one code path:

```
private function reference(Student $student, ?string $resolvedStatus = null): StudentReference
private function joinedReference(Student $student, ?string $resolvedStatus = null): StudentReference
```

`$status = $resolvedStatus ?? $this->lifecycleStatuses->statusesFor([(int) $student->id])[(int) $student->id] ?? $student->status;`
`statusLabel: Student::statusLabelFor($status)`

### H7 — bulk callers outside this file

Single-student `findByStudentCode()` is cheap **once**. It is called inside
`foreach` loops:

- `app/Modules/Academic/Delivery/Actions/BulkRegisterCourseOfferingStudentsAction.php:26-28` — **inside `DB::transaction`**. A 200-code paste goes 200 → 400 queries with the transaction held open.
- `app/Modules/Academic/Delivery/Actions/SearchCourseOfferingStudentsAction.php:20-21`

**Fix in this phase:** hoist to a batch lookup above the loop (add a
`findManyByStudentCode(array $codes, int $campusId)` to the store, or resolve
ids first then `findMany()`). Do not leave the loop calling a per-code method
that now costs two queries.

### H8 — serialized readers split-brain

`findSerialized()` :33, `findManySerialized()` :38, `findProfile()` :47 return
`toArray()` / hand-built arrays. `Student.php:142` sets `$hidden = []`, so the
**raw column** is emitted. Consumers:
`app/Modules/Academic/Delivery/Support/CourseRegistrationPresenter.php:38`,
`app/Modules/Academic/Delivery/Queries/ListCourseRegistrationsQuery.php:59`.

Post-flip without a decision: the roster screen (via `StudentReference`) and the
registration list (via serialized) show **different statuses for the same
student**.

**Decide and record one of:**
(a) override the `status` key from the batch resolution in `findSerialized()` /
`findManySerialized()`, and set `status` in `findProfile()`'s DTO; or
(b) declare serialized output legacy-by-contract, whitelist it in phase 5, and
fix the two consumers to use `StudentReferenceReader` instead.

**(a) is the smaller diff** and keeps consumers untouched. Prefer it unless
`findProfile()`'s `StudentProfile` DTO has no `status` field — it currently does
not, so `findProfile()` needs no change; only the two `*Serialized` methods do.

### H3 — `activeIdsForCampus()` has a write consequence

`EloquentStudentRegistryStore.php:225-233` → sole caller
`app/Modules/Engagement/Actions/Forms/GenerateStudentAssignmentsAction.php:86-89`
→ `StudentFormAssignment::upsert(...)` **with no delete arm**.

Measured set delta of the flip: **old 175 → new 180 (8 added, 3 removed).**

Reverting the store file does **not** remove the 8 new mandatory form
assignments nor restore the 3 dropped. Use the phase-0 Shared scope here, and
carry the compensating cleanup in the rollback section below.

## Related Code Files

**Modified (exclusive):**

- `app/Modules/StudentRegistry/Support/EloquentStudentRegistryStore.php`
  — constructor; `findSerialized()` :33; `findManySerialized()` :38;
  `findMany()` :142-164; `stream()` :200-220; `activeIdsForCampus()` :225-233;
  `search()` :251-267; `reference()` :269-294 (`status` :286, `statusLabel` :289);
  `joinedReference()` :296-321 (`status` :313, `statusLabel` :317);
  new `findManyByStudentCode()` if that shape is chosen for H7

**Modified (H7 call sites — coordinate, they live in Academic/Delivery):**

- `app/Modules/Academic/Delivery/Actions/BulkRegisterCourseOfferingStudentsAction.php:26-28`
- `app/Modules/Academic/Delivery/Actions/SearchCourseOfferingStudentsAction.php:20-21`

**Read-only:**

- the phase-0 Shared projection scope
- `app/Modules/Academic/Progression/Support/StudentLifecycleStatusReader.php:13-37,65-75`
- `app/Models/Student.php:620` — `statusLabelFor()` (note: `'active'` is commented out; phase 3 fixes that)
- `app/Shared/Contracts/StudentRegistry/DTO/StudentReference.php:24` — the `status` field
- `app/Modules/Engagement/Actions/Forms/GenerateStudentAssignmentsAction.php:86-89`
- `app/Modules/Academic/Delivery/Support/CourseRegistrationPresenter.php:38`, `app/Modules/Academic/Delivery/Queries/ListCourseRegistrationsQuery.php:59`

**Benefits with zero edits** (verified: consume `StudentReference`, not the model):
`CheckCourseRegistrationEligibilityQuery.php:32`,
`RegisterStudentForActiveSemesterUnitsAction.php:41`,
`CreateRetakeCourseRegistrationAction.php:63`,
`BulkRegisterCourseOfferingStudentsAction.php:35`,
`SearchCourseOfferingStudentsAction.php:62-63`.

> **Not** in this set: `GetStudentStatusBySemesterQuery` — it takes the `Student`
> **model**. The advisory's "benefits automatically" claim is false for it.
> Classified in phase 0.

**Tests:**

- `tests/Feature/Registry/StudentReferenceReaderTest.php` — extend (exists)
- `tests/Feature/Registry/StudentReferenceLifecycleStatusTest.php` — new

## Implementation Steps

1. **Confirm phase 0 delivered** the Shared scope and the highest-id tie-break. Do not start otherwise.
2. **Baseline** (record failing **names**): `tests/Feature/Registry/`, `tests/Feature/Academic/RetakeCourse/`, `tests/Feature/Academic/Delivery/`.
3. **Record the `activeIdsForCampus()` delta** before changing it: old id set vs new id set, via tinker. Expect 175 → 180. Paste into the PR.
4. **RED** — `tests/Feature/Registry/StudentReferenceLifecycleStatusTest.php`. **Every fixture must materialize a `program_enrollments` row** — a factory-default student tests only the fallback:
   - drifted student (column `deferred`, enrollment `active`/`intake_course`) → `find()->status === 'intake_course'`;
   - no-enrollment student (column `deferred`) → `'deferred'` (fallback);
   - `withdrawn` enrollment → `'dropout'` (accepted precision loss);
   - `statusLabel` equals `Student::statusLabelFor($resolved)`;
   - **query count**: `findMany()` over 10 students issues exactly **1** `program_enrollments` query (`DB::listen`);
   - **query count**: bulk register with ~20 codes issues exactly **1** (H7).
5. Constructor + signature change on the two private methods.
6. Pre-resolve in `findMany()`, `search()`; chunk `stream()`.
7. Hoist the two bulk loops (H7).
8. `activeIdsForCampus()` → phase-0 Shared scope.
9. Decide + apply H8 (prefer option (a): override `status` in the two `*Serialized` methods).
10. **GREEN.** Re-run all three baselines.
11. `./scripts/dev.sh composer exec -- pint <modified files>`

## Success Criteria

- `./scripts/dev.sh artisan test tests/Feature/Registry/` → **green** (no known baseline failures here).
- `./scripts/dev.sh artisan test tests/Feature/Academic/RetakeCourse/` → same failing names as baseline.
- `./scripts/dev.sh artisan test tests/Feature/Academic/Delivery/` → same failing names as baseline.
- Query-count assertions: **exactly 1** for `findMany()` (10 students) **and** bulk register (~20 codes).
- `activeIdsForCampus()` delta recorded in the PR as two integers + the added/removed id lists (expect 175 → 180, +8/−3).
- H8 decision recorded in the PR; if option (a), a test asserts `findSerialized($id)['status']` equals `find($id)->status` for a drifted student.
- `git diff app/Shared/Contracts/StudentRegistry/DTO/StudentReference.php` → empty.
- `rg -n "students\.status|'status'" app/Modules/StudentRegistry/Support/EloquentStudentRegistryStore.php` → hits only in `referenceColumns()` and inside the Shared scope call.

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **N+1 on stream / list screens** | High × High | Query-count assertions written before the change. `stream()` must not keep `lazy()` with per-row resolution. |
| **N+1 inside an open `DB::transaction`** (H7) | High × High | Bulk register holds a transaction; doubling queries lengthens lock hold under a 200-row paste. Batch hoist + dedicated query-count test. |
| **Serialized vs reference split-brain** (H8) | High × High | Step 9 forces a recorded decision. The equality test in Success Criteria is the mechanical check. |
| **Rollback does not undo form-assignment writes** (H3) | Med × High | See rollback below. The delta is recorded *before* the change so compensation is possible. |
| **Container resolution loop** | Low × High | Reader has no constructor deps. Smoke-check: `./scripts/dev.sh artisan tinker --execute="var_dump(get_class(app(App\Shared\Contracts\StudentRegistry\StudentReferenceReader::class)));"` |
| **Fixtures without enrollments prove nothing** | High × High | Step 4 requires a materialized enrollment per fixture. A green test built on a bare `Student::factory()` tests the fallback branch only. |

### Rollback

`git checkout --` the store file + the two bulk call sites, delete the new test.

**Not sufficient on its own (H3).** `GenerateStudentAssignmentsAction` upserts
with no delete arm, so the flip's 8 added assignments persist after a code
revert. Compensating cleanup:

1. Take the added-id list recorded in step 3.
2. Delete `StudentFormAssignment` rows for those student ids **where status is `not_started`**, scoped per `form_target`. Do not delete started/submitted rows.
3. The 3 removed students keep their existing rows — no restore needed (upsert never deleted them).

If that cleanup is unacceptable, move the `activeIdsForCampus()` change out of
this phase and behind the drift sign-off instead. The rest of phase 1 is
revert-clean.
