---
phase: 4
title: "Test suite updates"
status: completed
priority: P1
effort: "3h"
dependencies: [1, 2, 3]
---

# Phase 4: Test suite updates

## Overview

Update every test that references `SyllabusTemplate.exam_resit_fee` or
relies on the old syllabus-based validation gate, and add coverage for the
new catalog-price-existence check and the Unit-picker facts_match flow.

## Requirements

- Functional: no test asserts on `SyllabusTemplate.exam_resit_fee` existing.
- Functional: new test proves exam-resit creation fails cleanly (422 +
  Vietnamese message) when no catalog rule matches the unit.
- Functional: new test proves exam-resit creation succeeds and
  `ExamResitAttempt.fee_amount` matches the catalog-resolved amount when a
  matching rule exists.
- Functional: Pricing Operations feature test covers creating a rule via the
  new unit-scoped `facts_match_json` shape end-to-end.

## Related Code Files

- Modify: `tests/Feature/Academic/SyllabusTemplateEditLockTest.php:56,88`
  — drop `exam_resit_fee` from create payload/assertions.
- Modify: `tests/Feature/Academic/ExamResit/helpers.php:65,90`
  — stop setting syllabus `exam_resit_fee`; ensure test setup instead seeds a
  `FinancePricingCatalogItem` row for the test unit (or relies on the
  baseline catch-all rule already seeded by `BaselinePricingCatalog`).
- Modify: `tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php:51,75`
  — update policy-snapshot assertions to not reference the removed field.
- Modify: `tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php:53,69,125,139`
  — update snapshot assertions; ADD a new test case:
  "throws validation error when no catalog pricing rule exists for the unit"
  (no `FinancePricingCatalogItem` row, no catch-all) → expects
  `ValidationException` with the friendly message, not a raw `RuntimeException`.
- Modify: `tests/Feature/Academic/ExamResit/CompleteExamResitAttemptActionTest.php:47,108`
  — update setup/assertions on `fee_snapshot` to source from catalog fixture.
- Modify: `tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php:57,206`
  — update policy-snapshot setup.
- Modify: `database/factories/SyllabusTemplateFactory.php:48`
  — drop the `exam_resit_fee` key (also touched in Phase 1, verify here).
- Check, likely no change: `tests/Feature/Finance/CancellationOperationTest.php:289,311`,
  `tests/Feature/Finance/ExamResitChargeActionTest.php:38,62`,
  `tests/Feature/Finance/ManualFeeIntakeCutoverTest.php:144,156`,
  `tests/Feature/Finance/FinanceDebitIntakeContractTest.php:51`,
  `tests/Feature/Finance/Operations/exam_resit_due_helpers.php:68,87,102`,
  `tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php:268` — these
  reference the `exam_resit_fee` **charge_type/obligation_type** string, not
  the syllabus column; confirm each still passes unmodified, do not edit
  unless a specific one turns out to depend on the removed syllabus field.
- Add: `tests/Feature/Finance/Pricing/PricingOperationsTest.php`
  — new test case creating a `retake_fee`/`exam_resit_fee` catalog rule via
  the unit-scoped facts_match_json shape (`{"unit_id": N}`), asserting the
  stored row and that a subsequent price lookup with matching facts resolves
  to it over the catch-all rule; ALSO a test hitting the new Finance proxy
  unit-search route (Phase 3, Decision 4) confirming it returns results
  without requiring Academic's `can:view_unit` permission.
- Add/modify: test coverage for `ExamResitAttempt::markFinanceObligationCreated(int $userId, float $feeAmount): void`
  (Phase 1, Decision 2 — signature now requires `$feeAmount`) — update any
  existing test calling this method with the old 1-arg signature; add an
  assertion that `fee_amount` is persisted correctly after the call.

## Implementation Steps

1. Run `./scripts/dev.sh artisan test tests/Feature/Academic/ExamResit tests/Feature/Academic/SyllabusTemplateEditLockTest.php` first — establish the current red/green baseline before editing (per memory: some Academic/Finance suites have pre-existing unrelated failures — don't mistake those for regressions caused by this plan).
2. Update fixtures/helpers first (`helpers.php`, `SyllabusTemplateFactory.php`)
   since most test files depend on them.
3. Update assertions file-by-file per the list above.
4. Add the two new Phase-1-driven test cases (catalog-rule-missing failure,
   catalog-rule-present success-with-correct-amount).
5. Add the Phase-3-driven Pricing Operations unit-scoped rule test.
6. Run full touched-directory suite again; compare against the Step 1
   baseline — new failures must be attributable to this plan's changes only.

## Success Criteria

- [ ] `./scripts/dev.sh artisan test tests/Feature/Academic/ExamResit tests/Feature/Academic/SyllabusTemplateEditLockTest.php tests/Feature/Finance/Pricing` green (excluding any pre-existing baseline failures confirmed in step 1)
- [ ] New "no catalog rule" test case exists and passes
- [ ] New "catalog rule present, correct fee_amount" test case exists and passes
- [ ] New Pricing Operations unit-scoped facts_match test exists and passes

## Risk Assessment

- **Risk:** `tests/Feature/Academic/ExamResit/helpers.php` is shared across
  many test files — a wrong fixture change breaks all of them at once.
  Change it first and in isolation, run the full `ExamResit` directory
  immediately after, before touching individual test files.
- **Risk:** pre-existing baseline failures in Academic/Finance suites (see
  project memory) could be misattributed to this plan. Step 1's baseline run
  exists specifically to prevent that.
