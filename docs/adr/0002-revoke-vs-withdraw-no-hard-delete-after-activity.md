# Rollback uses two distinct actions: Revoke vs Withdraw

Rolling back an approval is split into two actions with different semantics, because `students` has no soft-deletes and ~41 tables reference `students.id` with `cascadeOnDelete` (finance charges, GPA, scores, wallets, scholarships…). Hard-deleting a Student that has any downstream activity would cascade-destroy academic and financial records.

- **Revoke** — for correcting a mistaken approval. Allowed **only within a safe window**: the created Student has zero downstream activity. It is a transactional teardown (delete Student + User + roles) that returns the Application to `pending`. Outside the window it is blocked.
- **Withdraw** — for removing a Student who has already studied. It **deletes nothing**: it reuses the existing `students.academic_status = 'withdrawn'` flow (with `status_reason` / `status_changed_by`), preserving all academic/financial history.

Scope note: only **Revoke** is part of the student-applications redesign. **Withdraw** is a Student-lifecycle capability (the logic already exists in `StudentStatusService::updateStatus`, lacking only an entry-point) and is built separately when needed — it is not triggered from the admissions flow. This ADR records *why* hard-delete-after-activity is forbidden and that withdrawal is the correct path; it does not put Withdraw in the applications redesign.
