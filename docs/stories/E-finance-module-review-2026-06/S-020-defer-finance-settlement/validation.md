# Validation

## Proof Strategy

Prove that defer Finance behavior preserves existing ledger truth:

- item-level defer evidence exists for old and new defer cases;
- deferred original registrations are non-billable in expected-fee logic;
- paid cash is either preserved or consumed according to policy;
- `FORFEIT` and `PARTIAL` never create debt beyond real paid cash;
- future re-enrollment creates a normal charge and uses available cash through
  the normal allocation path;
- DNG-linked cases do not bypass provider lifecycle safety.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Defer policy amount capping: preserve, forfeit, partial, unpaid, overpaid, partial amount greater than paid. |
| Unit | Charge mapping classifier: course-linked charge, full-semester charge, missing charge, voided charge, multi-course ambiguous charge, discount present, live DNG present. |
| Integration | Backfill creates missing `defer_case_items` for full-scope cases idempotently. |
| Integration | Runtime full-scope defer creates item-level records and marks affected registrations `defer`. |
| Integration | Billing exception and Fee Monitor expected-fee queries exclude `registration_status = defer`. |
| Integration | Preserve with paid charge voids/releases source obligation with auto-reallocation disabled and leaves cash available. |
| Integration | Preserve with unpaid charge does not create credit or debt. |
| Integration | Forfeit consumes only paid cash, capped by paid amount, and does not create unpaid debt. |
| Integration | Partial consumes only the paid portion selected by policy and leaves the remainder available. |
| Integration | Future re-enrollment generates a normal charge; existing available cash can allocate normally; preserve policy does not skip the charge. |
| Integration | Live DNG-linked charge blocks direct defer settlement and requires DNG cancellation flow first. |
| E2E | Optional admin smoke only if an implementation slice changes web pages. |
| Platform | Targeted Pint, PHP tests, any touched frontend checks, and `git diff --check`. |
| Performance | Backfill dry-run is bounded and does not load unbounded Finance history per row. |
| Logs/Audit | Finance mutation rows include actor/reason/source evidence; invariant audit shows no new money/DNG regressions. |

## Fixtures

- Student with full-scope defer and multiple course registrations.
- Student with course-scope defer and a charge linked to one registration.
- Student with full-semester tuition charge and full-scope preserve.
- Student with semester-level tuition charge and only some deferred courses
  requiring review.
- Paid preserve case.
- Unpaid preserve case.
- Paid forfeit case.
- Unpaid forfeit case.
- Partial case where paid amount is below, equal to, and above consumed amount.
- Charge with scholarship/discount allocation.
- Charge with active DNG pending/pushed request.
- Already-voided source charge.
- Future re-enrollment registration with `original_registration_id` or the
  current lineage mechanism used in the repo.

## Commands

Expected minimum commands for implementation evidence:

```text
./scripts/dev.sh test tests/Unit/Finance/Defer*
./scripts/dev.sh test tests/Feature/Finance/**/*Defer*
./scripts/dev.sh test tests/Feature/Finance/Reporting/FeeMonitorViewTest.php
./scripts/dev.sh test tests/Feature/Finance/Operations/*Billing*Exception*.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh composer exec pint -- <touched PHP files>
git diff --check
```

If frontend/admin display changes are added:

```text
./scripts/dev.sh npm exec eslint -- <touched Vue/TS files>
./scripts/dev.sh npm exec prettier --check <touched Vue/TS files>
./scripts/dev.sh npm run type-check
```

Record known repo-wide baseline failures separately from story regressions.

## Acceptance Evidence

Add results after implementation.
