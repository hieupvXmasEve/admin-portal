---
phase: 1
title: "Enable soft-delete on Student model"
status: pending
priority: P1
effort: "4h"
dependencies: []
---

<!-- Updated: Red Team Session 1 - Findings 5, 10 -->


# Phase 1: Enable soft-delete on Student model

## Overview

`students.deleted_at` exists but is unused by Eloquent. Add `SoftDeletes` so `Student::delete()` becomes reversible and every Eloquent-based read (relations, `Student::query()`, form requests resolving route-model-binding) automatically excludes deleted rows.

## Requirements

- Functional: `Student::delete()` sets `deleted_at` instead of removing the row; `Student::query()` and all relations defined on `Student` exclude soft-deleted rows by default.
- Non-functional: the only other caller of `Student::delete()` (`RevokeStudentIdentityAction`) is switched to `->forceDelete()` in this same phase so its hard-delete semantics are preserved, not silently converted to soft-delete.

## Architecture

`app/Models/Student.php` extends `StudentAuditableModel` (which extends `Illuminate\Foundation\Auth\User as Authenticatable`, uses `LogsActivity`). `SoftDeletes` is compatible with `Authenticatable` and `LogsActivity` (Spatie Activitylog logs the delete event when the model uses both). Add the trait directly to `Student`, not the abstract base (`StudentAuditableModel` is also the base for other auditable models that should stay hard-delete unless individually opted in).

**Decided (was deferred, red-team Finding 5):** `RevokeStudentIdentityAction` switches to `forceDelete()`. It's the pre-admission revocation path (zero academic/financial activity, guarded by `RequireRevocableStudentIdentityAction`) feeding an Admissions revoke→re-approve loop (`RevokeApplicationAction` → `RegisterAdmittedStudentAction`). Leaving it as `->delete()` would make it soft, and the surviving row's unique `email`/`student_id` would collide when the same application is re-approved — this is not a "confirm with user" item, it's a correctness bug if left as-is.

**Pre-deploy gate (red-team Finding 10):** `students.deleted_at` has existed since table creation but was never Eloquent-managed — turning on the global scope retroactively affects any row that already has a non-null value from a legacy import or manual cleanup.

## Related Code Files

- Modify: `app/Models/Student.php` — add `use Illuminate\Database\Eloquent\SoftDeletes;` and the trait to the `use` statement.
- Modify: `app/Modules/StudentRegistry/Actions/RevokeStudentIdentityAction.php:14` — change `->delete()` to `->forceDelete()`.
- Check: `database/factories/StudentFactory.php` — soft-deleted factory states if tests need `withTrashed()`.

## Implementation Steps

1. **Pre-deploy check:** run `SELECT COUNT(*) FROM students WHERE deleted_at IS NOT NULL` against production before this ships. If non-zero, investigate each row (legacy artifact vs. intentional) before enabling the trait — those rows will disappear from every list/search the moment the scope is live. Document the count and decision in the PR description.
2. Add `use Illuminate\Database\Eloquent\SoftDeletes;` import and `SoftDeletes` to the trait list on `Student`.
3. Change `RevokeStudentIdentityAction::run()` from `->delete()` to `->forceDelete()`.
4. Run `./scripts/dev.sh artisan tinker` and confirm: create a student, `$s->delete()`, `Student::find($s->id)` is null, `Student::withTrashed()->find($s->id)` resolves, `students.deleted_at` is set.
5. Run existing Student-related test suites (`tests/Feature/Registry/*`, any `StudentServiceTest`, Admissions revoke/re-approve tests) to catch anything relying on old delete semantics.
6. Rollback note: reverting this phase after it's been live resurrects every soft-deleted student (the global scope disappears) with their Sanctum tokens already revoked (Phase 3) and identity fields already tombstoned (Phase 3, Finding 9) — not a clean revert. If rollback is needed, run a `WHERE deleted_at IS NOT NULL` audit before deciding whether to hard-delete or manually restore those rows.

## Success Criteria

- [ ] Pre-deploy `deleted_at` row count checked and documented (zero, or explicitly investigated)
- [ ] `Student` uses `SoftDeletes`
- [ ] `Student::find()` excludes soft-deleted rows; `withTrashed()`/`onlyTrashed()` work
- [ ] `RevokeStudentIdentityAction` uses `forceDelete()`, verified against an Admissions revoke→re-approve test
- [ ] Existing Student test suites pass unmodified (or only updated where they asserted hard-delete)

## Risk Assessment

The blast radius is every place that queries `Student`, in both directions — covered by Phase 2's raw-query audit and inbound-relation `withTrashed()` pass (red-team Finding 4). Rollback is asymmetric once Phase 3's token revocation and identity tombstoning are live (see step 6) — this is a soft one-way door, not a fully reversible additive change.
