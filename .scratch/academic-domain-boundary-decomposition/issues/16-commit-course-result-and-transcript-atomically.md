# Commit Course Result and Transcript Entry atomically

Status: ready-for-human

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Create the explicit Course Result handoff from Course Delivery & Assessment to Academic Progression & Lifecycle. Finalize and Recalculate must convert Delivery-owned evidence into Course Result DTOs, synchronously commit Progression-owned Transcript Entries, and only then complete the Course Offering and publish post-commit events.

## Acceptance criteria

- [x] Delivery finalizes one Course Result per eligible Student attempt without exposing component-score persistence to Progression.
- [x] Progression creates or updates Transcript Entry idempotently from the Course Result contract.
- [x] Transcript Entry is the source for GPA, Academic Standing, best attempt, and later graduation decisions.
- [x] A Transcript failure rolls back Course Results, offering completion, and every related write in the transaction.
- [x] Recalculate uses the same boundary and updates corrected Transcript Entries without creating duplicate attempts.
- [x] Notifications and integrations are emitted after commit exactly once and are absent after rollback.
- [ ] Existing completion, recalculation, student-result, lecturer-gradebook, architecture, and both portal checks pass.

## Blocked by

- [Issue 01: Publish Academic lifecycle notifications through the shared Domain Event boundary](01-publish-academic-lifecycle-notifications-through-shared-boundary.md)
- [Issue 10: Materialize Program Enrollment and separate its state machines](10-materialize-program-enrollment.md)
- [Issue 15: Manage attendance and gradebook through Course Delivery](15-manage-attendance-and-gradebook-through-delivery.md)

## Verification

- Passed: focused Course Result/Transcript, completion, recalculation, GPA, Student Hub overview/graduation, and Delivery architecture suite — 59 tests, 306 assertions.
- Passed: PHP Pint and `git diff --check`.
- Blocked outside this slice: `./scripts/dev.sh test` exits 255 without diagnostics, and root `npm run type-check` does not complete in this workspace even with a 4 GB Node heap. Portal builds previously completed with pre-existing Nuxt warnings; no portal code or API response shape changed in this slice.

## Comments

- 2026-07-19: Added the Delivery-owned Course Result DTO handoff and a Progression-owned, idempotent `transcript_entries` projection keyed by `course_result_id`. Completion and recalculation commit it inside the existing transaction before the offering state transition; the existing Domain Event publisher remains post-commit, so a transaction rollback persists no outbox work.
- 2026-07-19: Moved GPA calculation, student GPA fallback/distribution, curriculum best-attempt reads, and graduation credit tracking to Transcript Entries while retaining existing portal response shapes. The issue awaits human verification only for root checks that are currently unable to complete in the workspace.
