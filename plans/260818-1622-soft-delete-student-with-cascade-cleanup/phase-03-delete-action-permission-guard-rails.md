---
phase: 3
title: "Delete action, permission, guard rails"
status: pending
priority: P1
effort: "1.5d"
dependencies: [1, 2]
---

<!-- Updated: Red Team Session 1 - Findings 1, 2, 3, 6, 9, 11, 12, 13, 14, 15 -->

# Phase 3: Delete action, permission, guard rails

## Overview

Add the actual delete endpoint: a StudentRegistry Action (owner module — per `.claude/rules/development-rules.md` file-placement rule, this is new HTTP-adjacent behavior for the Student feature and must live under `app/Modules/StudentRegistry/`, not the legacy global `app/Services/StudentService.php`), wired to `DELETE /students/{student}` guarded by the `delete_student` permission, a same-campus check, and a fail-closed finance guard.

This phase absorbed most of red-team session 1's findings — the original design (permission-only auth, a raw `BillingAccount` + `SettlementPositionReader` guard sketch, token-only revocation) had five Critical/High gaps. What follows is the corrected version, not the original.

## Requirements

- Functional: `DELETE /students/{student}` soft-deletes the student in a transaction; returns `back()` with a flash message, preserving the index's current filter/pagination state (not a redirect to the now-404 student detail route).
- Functional: permission `delete_student` (already in `config/permission.php:170`) enforced via `->middleware('can:delete_student')`, matching every other route in `app/Modules/StudentRegistry/routes/web.php`.
- Functional: **campus check** — the target student's `campus_id` must match the acting user's session campus, or the request is rejected. Route-model binding resolves globally; nothing else in this endpoint enforces campus isolation (`edit`/`update` have the same gap today, but a destructive, unrestorable action can't inherit it silently).
- Functional: **guard fails closed** — deletion is blocked unless the student's finance position is confirmed clean. "Confirmed clean" means: a billing account exists, `SettlementPositionReader::forBillingAccount()` returns a *valid* position, and `amounts->remaining` is zero. Any of {no billing account, invalid position, non-zero remaining in either direction} blocks the delete. This inverts the original "block only if a balance is found" design — that version defaulted to *allow* whenever the finance data was absent or unreadable, which is backwards for a destructive action.
- Functional: deleting a student revokes **both** the `Student`'s own Sanctum tokens (`$student->tokens()->delete()`) and the linked `users` account's portal access (`StudentAccessWriter::revoke($student->user_id)`) — the student's real login path is `AuthController` against the `users` table, not just `Student`'s own `HasApiTokens`.
- Functional: deleting a student tombstones `email`, `student_id`, and `national_id` (in the same transaction, before setting `deleted_at`) so the unique indexes on those columns don't permanently block re-creating the student or re-approving the originating Admissions application.
- Non-functional: no silent failure — guard-block raises a dedicated typed exception with a user-facing message; any other failure is caught broadly, logged with full detail server-side, and shown to the user as a generic error — never `$e->getMessage()` verbatim, which would leak Finance-layer/SQL detail to a `delete_student`-only user.

## Architecture

New Action class: `app/Modules/StudentRegistry/Actions/SoftDeleteStudentAction.php`, following the shape of `RevokeStudentIdentityAction`/`RequireRevocableStudentIdentityAction`:

```php
final class StudentDeletionBlockedException extends RuntimeException {}

final class SoftDeleteStudentAction
{
    public static function run(Student $student, int $actingCampusId): void
    {
        if ((int) $student->campus_id !== $actingCampusId) {
            abort(404); // cross-campus: do not leak existence via a 403
        }

        DB::transaction(function () use ($student) {
            $billingAccount = BillingAccount::query()
                ->where('student_id', $student->id)
                ->lockForUpdate() // matches SettlementMutationGuard's convention — no check-then-act race
                ->first();

            if ($billingAccount === null) {
                throw new StudentDeletionBlockedException(
                    'Student has no billing account on record and cannot be deleted until finance data is confirmed clean.'
                );
            }

            $position = app(SettlementPositionReader::class)->forBillingAccount($billingAccount->id);

            // Fail CLOSED: any invalid/unreadable position blocks deletion. Do not delete
            // on "no balance found" when the reason is "couldn't compute the balance".
            if (! $position->isValid()) {
                throw new StudentDeletionBlockedException(
                    'Student\'s finance position could not be verified and cannot be deleted.'
                );
            }

            // NOTE: use amounts->remaining, NOT amounts->netDue() — netDue() is gross minus
            // discount only and ignores cash/credit already applied; remaining is the actual
            // outstanding-after-payments figure used by production consumers (e.g.
            // StudentInvoiceResource, GetStudent360LedgerQuery). Block on non-zero in EITHER
            // direction: positive = student owes money, negative = student is owed a refund/credit.
            if (! $position->amounts->remaining->isZero()) {
                throw new StudentDeletionBlockedException(
                    'Student has a non-zero finance balance and cannot be deleted.'
                );
            }

            $student->tokens()->delete(); // Student's own Sanctum tokens (HasApiTokens)

            if ($student->user_id !== null) {
                app(StudentAccessWriter::class)->revoke($student->user_id); // real portal login path
            }

            $tombstone = 'deleted-'.now()->timestamp.'-';
            $student->forceFill([
                'email' => $tombstone.$student->email,
                'student_id' => $tombstone.$student->student_id,
                'national_id' => $student->national_id === null ? null : $tombstone.$student->national_id,
            ])->save();

            $student->delete(); // soft-delete via Phase 1's SoftDeletes trait
        });
    }
}
```

**Do not** re-implement the 8-relation force-delete loop from the dead `StudentService::deleteStudent` — Phase 1+2 already make the parent soft-delete sufficient (children stay, hidden via the parent join for forward queries, `withTrashed()` for inbound relations per Phase 2). Cascade stays parent-only per validation decision — do not extend to child tables.

**Do not** modify `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` in this phase — role-to-permission grants are handled manually post-deploy (validation decision), not seeded.

**Verify before implementing:** `SettlementPositionReader::forBillingAccount()` may itself be the wrong entry point — red-team evidence points at `app/Modules/Finance/Support/StudentFinanceSettlementPositionReader.php` as an existing adapter that already handles "no billing account" and "unmaterialized payable lines" (returns a blocking issue / downgrades to invalid rather than silently reporting zero). If a narrower `Shared/Contracts/Finance/*` reader wraps that adapter, prefer it over composing `BillingAccount` + the raw `SettlementPositionReader` ledger contract directly — the latter's own docblock says other contexts should receive narrower adapters, not the raw contract (`app/Shared/Contracts/Finance/SettlementPositionReader.php:10-13`).

## Related Code Files

- Create: `app/Modules/StudentRegistry/Actions/SoftDeleteStudentAction.php` (and `StudentDeletionBlockedException`, colocated or in `app/Modules/StudentRegistry/Exceptions/`)
- Modify: `app/Modules/StudentRegistry/Http/Web/StudentController.php` — add:
  ```php
  public function destroy(Student $student): RedirectResponse
  {
      try {
          SoftDeleteStudentAction::run($student, (int) session('current_campus_id'));
      } catch (StudentDeletionBlockedException $e) {
          return back()->withErrors(['error' => $e->getMessage()]);
      } catch (\Throwable $e) {
          Log::error('Failed to delete student', ['student_id' => $student->id, 'error' => $e->getMessage()]);
          return back()->withErrors(['error' => 'Unable to delete this student. Please try again or contact support.']);
      }

      return back()->with('success', 'Student deleted successfully');
  }
  ```
- Modify: `app/Modules/StudentRegistry/routes/web.php` — add:
  ```php
  Route::delete('{student}', [StudentController::class, 'destroy'])
      ->middleware('can:delete_student')
      ->name(StudentRoutes::DESTROY);
  ```
- Modify: `app/Constants/StudentRoutes.php` — add `DESTROY` constant.
- Check before coding: `app/Modules/Finance/Support/SettlementPosition/SettlementPosition.php` (confirm `isValid()` + `amounts->remaining` API), `app/Modules/Finance/Support/StudentFinanceSettlementPositionReader.php` (confirm it's the right entry point per the note above), `app/Modules/Identity/Support/EloquentStudentAccessWriter.php` / `RevokeStudentAccessAction` (confirm `StudentAccessWriter::revoke()` signature), `app/Modules/Finance/Support/SettlementMutationGuard.php:53` (confirm `lockForUpdate()` usage matches this module's convention).
- Add tests here (moved from Phase 5, red-team Finding 11) — see Success Criteria.

## Implementation Steps

1. Read `SettlementPosition` and `StudentFinanceSettlementPositionReader`'s real public APIs; confirm the guard's entry point and field names before writing code.
2. Confirm `StudentAccessWriter::revoke()`'s exact signature (used today by `RevokeApplicationAction`).
3. Create `StudentDeletionBlockedException` and `SoftDeleteStudentAction` with: campus check, row-locked fail-closed guard, token + access revocation, identity tombstoning, soft-delete — all in one transaction.
4. Add `destroy()` to `StudentController` with the three-tier catch (typed guard exception → generic throwable → success), route + `StudentRoutes::DESTROY` constant.
5. Run `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder` to confirm `delete_student` is present in the `permissions` table (syncs automatically from `config/permission.php:170` — no seeder code change). Role assignment is manual, post-deploy.
6. Write feature tests in `tests/Feature/Registry/` (correct path — `tests/Feature/StudentRegistry/` does not exist): permission denial (403), cross-campus denial (404), successful delete with clean finance position, guard-block on missing billing account, guard-block on invalid position, guard-block on non-zero balance (both directions), token + `users` access revocation, tombstoned identity allows creating a new student with the original email, transaction rollback on guard failure (no partial mutation).
7. Manual check via tinker/Postman for the same matrix, cross-checking against the automated tests.

## Success Criteria

- [ ] `SoftDeleteStudentAction` exists under StudentRegistry, transactional, no silent catch
- [ ] Route + controller method wired with `can:delete_student`, plus an explicit campus check (404 on mismatch)
- [ ] Guard fails **closed**: blocks on missing billing account, invalid position, or non-zero `amounts->remaining` in either direction — verified against the real `SettlementPosition`/`StudentFinanceSettlementPositionReader` API, not the sketch above
- [ ] Guard read is row-locked (`lockForUpdate()`) before evaluating the balance
- [ ] Student's Sanctum tokens **and** linked `users` account access both revoked in the same transaction
- [ ] `email`/`student_id`/`national_id` tombstoned before `deleted_at` is set, verified by a test that re-creates a student with the original email afterward
- [ ] `destroy()` returns `back()` with flash — no redirect to a route that 404s on the just-deleted model
- [ ] Guard-block errors show a specific, safe message; all other failures show a generic message and log full detail server-side
- [ ] Feature tests (in this phase, not deferred) pass for the full matrix in step 6
- [ ] No `RoleAndPermissionSeeder` changes made — permission exists via config sync only

## Risk Assessment

Highest-risk phase: this is the actual mutation + authorization boundary, and red-team found it was under-specified in five separate ways (campus scope, fail-open guard, nonexistent API, wrong revocation target, unique-constraint lockout). All five are now pinned above. Remaining implementation risk is API-accuracy: `StudentFinanceSettlementPositionReader`'s exact shape and `StudentAccessWriter::revoke()`'s signature must be confirmed by reading the actual code (step 1-2), not assumed from this plan's sketch.
