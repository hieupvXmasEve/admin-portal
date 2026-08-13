---
phase: 1
title: "Backend: remove SyllabusTemplate.exam_resit_fee, catalog-driven validation"
status: completed
priority: P1
effort: "4h"
dependencies: []
---

# Phase 1: Backend — remove SyllabusTemplate.exam_resit_fee, catalog-driven validation

## Overview

Drop the dead `exam_resit_fee` column from `syllabus_templates`, remove every
backend reference to it, and replace the validation gate in
`CreateExamResitAttemptAction` with a check against the real price source
(`FinancePricingCatalog`, via the existing eager Finance Intake call).

## Requirements

- Functional: creating an exam-resit attempt for a unit with no matching
  catalog rule fails with `ValidationException` message
  `"Chưa cấu hình giá thi lại cho môn này. Vui lòng cấu hình tại Pricing Operations."`
  instead of a raw `RuntimeException`.
- Functional: `ExamResitAttempt.fee_amount` is populated from the Finance
  intake result (the catalog-resolved amount), not from
  `SyllabusTemplate.exam_resit_fee`.
- Non-functional: migration is reversible (`down()` re-adds the column
  nullable) in case of rollback need, per repo migration convention.

## Architecture

Current flow (`CreateExamResitAttemptAction.php`):
```
resolveSyllabus() → read $syllabus->exam_resit_fee → assertPolicyAllowsExamResit()
  throws if <= 0
→ create ExamResitAttempt (fee_amount = syllabus snapshot)
→ call Finance Intake (facts WITHOUT amount — Finance re-prices via catalog)
```

New flow:
```
resolveSyllabus() → (no exam_resit_fee read)
→ create ExamResitAttempt (fee_amount = null initially)
→ call Finance Intake inside try/catch
    catch RuntimeException (no catalog rule) → rollback, throw ValidationException
    on success → read resolved amount from Finance intake response
→ update ExamResitAttempt.fee_amount = resolved amount
```

Both the attempt creation and the Finance Intake call already happen in one
DB transaction per ADR-0026 §5.1 (eager cutover) — wrapping the intake call in
try/catch and letting the ValidationException propagate naturally rolls back
the transaction; no new transaction handling needed.

## Related Code Files

- Modify: `database/migrations/2026_06_17_100000_add_retake_resit_source_foundation.php`
  — do NOT edit an already-applied migration in place. Instead:
- Create: `database/migrations/{today}_drop_exam_resit_fee_from_syllabus_templates.php`
  — `Schema::table('syllabus_templates', fn ($t) => $t->dropColumn('exam_resit_fee'))`;
  `down()` re-adds `decimal('exam_resit_fee', 15, 2)->nullable()`.
- Modify: `app/Models/SyllabusTemplate.php`
  — remove `exam_resit_fee` from `$fillable` (line 28), `$casts` (line 52),
  audit-log attribute list (line 167).
- Modify: `app/Http/Requests/SyllabusTemplate/StoreSyllabusTemplateRequest.php`
  — remove `'exam_resit_fee' => [...]` rule (line 32).
- Modify: `app/Http/Requests/SyllabusTemplate/UpdateSyllabusTemplateRequest.php`
  — remove same rule (line 38).
- Modify: `app/Modules/Academic/Delivery/Actions/CreateExamResitAttemptAction.php`
  — remove read of `$syllabus->exam_resit_fee` (lines 70, 73, 187, 193, 201);
  remove `assertPolicyAllowsExamResit()` `<= 0` throw (lines 205-207);
  wrap Finance Intake call (lines 81-104) in try/catch for `RuntimeException`,
  rethrow as `ValidationException::withMessages(['policy' => [...]])`;
  after successful intake, read the resolved amount from the intake response
  and pass it to `markFinanceObligationCreated()` (line 106) per the extended
  signature below — this is now a required signature change, confirmed via
  `/ak:plan validate` (see plan.md Validation Log, Decision 2), not optional.
- Modify: `app/Models/ExamResitAttempt.php`
  — extend `markFinanceObligationCreated(int $userId): void` (line 222) to
  `markFinanceObligationCreated(int $userId, float $feeAmount): void`; set
  `$this->fee_amount = $feeAmount` alongside the existing `hq_fee_status`
  transition, same save. Check for other existing callers of this method
  (grep `markFinanceObligationCreated`) and update each call site to pass the
  new required param — do not add a default/optional param that would let
  callers silently skip setting the real amount.
- Modify: `app/Modules/Academic/Catalog/Http/Requests/ListSyllabusTemplatesRequest.php`
  — remove `exam_resit_fee` from valid sort keys (line 23).
- Check, no change expected: `app/Modules/Academic/Catalog/Http/Web/SyllabusTemplateController.php:120-126`
  — `show()` returns `$template` directly; once column is dropped Eloquent
  simply stops serializing it, no code change needed, just confirm no
  `->exam_resit_fee` access elsewhere in that controller.
- Check, no change expected: `database/factories/SyllabusTemplateFactory.php:48`
  — remove the `exam_resit_fee` key from the factory's default array once
  column is gone (factory would error otherwise).

## Implementation Steps

1. Read `CreateExamResitAttemptAction.php` in full to confirm current method
   boundaries (`resolveSyllabus`, `assertPolicyAllowsExamResit`, the Finance
   intake call site, and how `ExamResitAttempt.fee_amount` / `hq_fee_status`
   are currently set) before editing — signatures may differ slightly from
   the line numbers cited above (confirm against current file).
2. Add the column-drop migration; run it locally
   (`./scripts/dev.sh artisan migrate`) against the dev DB, never `--env=testing`
   (see memory: `swinx-env-testing-targets-dev-db` — that flag hits the dev DB,
   not a test DB).
3. Remove `exam_resit_fee` from `SyllabusTemplate` model, both FormRequests,
   and the sort-key list.
4. Edit `CreateExamResitAttemptAction` per the Architecture section above.
5. Update `SyllabusTemplateFactory` to drop the `exam_resit_fee` key.
6. Run `./scripts/dev.sh artisan test --filter=ExamResit` and
   `--filter=SyllabusTemplate` to catch immediate breakage before Phase 4's
   full test pass.

## Success Criteria

- [ ] Migration applied; `syllabus_templates` has no `exam_resit_fee` column
- [ ] No PHP file references `$syllabus->exam_resit_fee` or
      `SyllabusTemplate::exam_resit_fee` (grep confirms zero hits outside
      migrations/tests not yet touched)
- [ ] Exam-resit creation for a unit with no catalog rule returns a 422 with
      the friendly Vietnamese message, not a 500
- [ ] Exam-resit creation for a unit with a catalog rule succeeds and
      `ExamResitAttempt.fee_amount` matches the catalog amount

## Risk Assessment

- **Risk:** `assertPolicyAllowsExamResit()` may check other policy fields
  besides `exam_resit_fee` (attempts remaining, attendance, etc per earlier
  scout). Only remove the fee check, not the whole method.
- **Risk:** `markFinanceObligationCreated()` signature change is a breaking
  contract change — grep found the intake call site
  (`CreateExamResitAttemptAction.php:106`) as the only caller of
  `ExamResitAttempt::markFinanceObligationCreated()`. **Name collision
  warning:** `app/Models/CourseRetakeRegistration.php:200` has an unrelated
  method of the SAME name, called from
  `CreateRetakeCourseRegistrationAction.php:201` for the `retake_fee` flow.
  Do NOT change `CourseRetakeRegistration::markFinanceObligationCreated()` —
  out of scope (plan.md Non-Goals), different model, different flow. Only
  `ExamResitAttempt`'s method changes.
- **Mitigation:** run Phase 1's scoped tests (step 6) before moving to
  Phase 2/3 — catches signature drift early, cheaper to fix in isolation.

## Accepted Data Risk (explicit user decision, not an oversight)

`syllabus_templates` has exactly 1 row with a non-default `exam_resit_fee`
(`id=149, unit_id=42, fee=3,000,001₫` vs the 750,000₫ catch-all catalog
rule). User decision (2026-08-13, plan.md Validation Log): **do not migrate**
this value into a catalog rule before dropping the column. After this phase
ships, unit 42's exam-resit fee silently becomes 750,000₫ (the catch-all)
unless/until staff manually create a `{"unit_id": 42}` catalog rule via
Phase 3's new UI. No migration step required in this phase.
