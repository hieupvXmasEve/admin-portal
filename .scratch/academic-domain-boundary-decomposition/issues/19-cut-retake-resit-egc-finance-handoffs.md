# Cut retake, resit, and EGC Finance handoffs over to owner contracts

Status: completed

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Remove the remaining direct Finance-to-Academic and Academic-to-Finance implementation calls in retake, exam-resit, and EGC flows. Finance consumes neutral Academic source facts and sends paid/settled outcomes through Academic-owned commands; Academic sends chargeable or changed lifecycle facts through Finance-owned contracts and durable handoffs.

## Acceptance criteria

- [x] Retake, exam-resit, and EGC source facts are provided by the owning Academic context through neutral DTOs and source references.
- [x] Finance pricing, obligations, discounts, charges, DNG, settlement, and paid evidence remain Finance-owned.
- [x] Paid retake/resit enrollment or progression is requested through an Academic-owned command rather than Finance importing an Academic Action.
- [x] Academic fee-state projections are updated idempotently from Finance outcomes and never treated as authoritative settlement evidence.
- [x] Missing obligations, duplicate handoffs, late paid evidence, cancellation, retries, and reconciliation retain existing behavior and audit evidence.
- [x] Neither context imports the other's Eloquent models, concrete Actions, services, or internal enums for these flows.
- [x] Retake, resit, EGC, Finance boundary, failure-recovery, and student portal tests pass.

## Blocked by

- [Issue 08: Use Student Reference throughout Finance collection and DNG flows](08-use-student-reference-in-finance-collection.md)
- [Issue 14: Manage Course Registration and roster through Course Delivery](14-manage-course-roster-through-delivery.md)
- [Issue 16: Commit Course Result and Transcript Entry atomically](16-commit-course-result-and-transcript-atomically.md)
- [Issue 18: Run EGC progression and Decisions inside Academic Progression & Lifecycle](18-run-egc-progression-and-decisions-in-progression.md)

## Comments

- Completed 2026-07-19. Academic EGC attendance remediation now calls the Finance-owned `EgcBlockResultReconciler` contract inside its surrounding transaction; the Finance reconciliation rolls back with the Academic correction on failure.
- DNG payment handling no longer imports Academic Actions. It invokes the existing Academic-owned retake/resit payment-sync contracts, treats returned failures as retryable webhook failures, and replays those idempotent projections for equivalent or already-progressed verified callbacks.
- Added architecture enforcement for all direct concrete Academic↔Finance namespace references, including fully qualified references, and a retry test for failed paid-retake projection handoffs.
- Verification: Pint; `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/AcademicFinanceBoundaryArchTest.php tests/Feature/Architecture/StudentLifecycleProgressionFinanceBoundaryArchTest.php tests/Feature/Academic/RetakeCourse/SyncPaidRetakeRegistrationsActionTest.php tests/Feature/Academic/ExamResit/SyncPaidExamResitAttemptsActionTest.php tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php tests/Feature/Finance/Egc/SyncEgcBlockResultsTest.php` (59 passed, 237 assertions); `./scripts/dev.sh npm run type-check`; `./scripts/dev.sh test`.
