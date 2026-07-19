# Serve Finance EGC and pricing worklists through Progression readers

Status: completed

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Cut Finance EGC generation, carry-forward, retake adjustment, tuition-term pricing, lifecycle exception, and related worklists over to Academic Progression & Lifecycle readers. Finance receives the minimum immutable academic facts required to price and classify while retaining all financial decisions and ledger writes.

## Acceptance criteria

- [x] Finance obtains Program Enrollment, Study Stage, Academic Period, EGC block/result, and course-stage facts through owner readers or accepted source DTOs.
- [x] Academic sends pricing facts only; Finance remains responsible for rules, amounts, obligation/entitlement type, discounts, and settlement effects.
- [x] Existing EGC generation, carry-forward, retake discount, tuition-term, and exception-classification behavior remains unchanged.
- [x] Finance worklists preserve filters, campus scope, totals, warnings, and actionable missing-data states.
- [x] No Finance EGC or pricing code queries Student, Transcript, Course Result, or EGC persistence directly.
- [x] Repeated reads/handoffs are idempotent and missing owner facts fail explicitly without partial money writes.
- [x] EGC Finance, pricing, invariant, architecture, and student portal checks pass.

## Blocked by

- [Issue 18: Run EGC progression and Decisions inside Academic Progression & Lifecycle](18-run-egc-progression-and-decisions-in-progression.md)
- [Issue 19: Cut retake, resit, and EGC Finance handoffs over to owner contracts](19-cut-retake-resit-egc-finance-handoffs.md)

## Verification

- Added an architecture guard for the Finance EGC and tuition-pricing paths; it rejects direct Student, transcript, result, EGC, and course-registration persistence reads.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- `./scripts/dev.sh npm run type-check`
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Egc`
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Major/TuitionTermIntakeTest.php`
- `./scripts/dev.sh artisan test --compact --filter="precondition" tests/Feature/Finance/Defer/DeferReEnrollmentTest.php`
- `./scripts/dev.sh artisan test --compact --filter="blocks EGC generation when defer logic" tests/Feature/Finance/Egc/GenerateEgcChargesTest.php`
- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/FinanceEgcPricingOwnerReadersArchTest.php`
- `./scripts/dev.sh artisan test --compact --filter="command reports all invariants passing on a clean dataset" tests/Feature/Finance/Integrity/AuditFinanceInvariantsParityTest.php`
- `pnpm typecheck` in `FE/student-nuxt` (no portal source change required).
