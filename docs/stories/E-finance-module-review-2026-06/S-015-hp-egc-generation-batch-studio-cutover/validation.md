# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Proof Strategy

Prove that HP/Tuition and EGC charge generation are initiated through Batch
Studio as the primary workflow, and that old standalone routes cannot bypass the
Batch Studio preview-token safety contract.

Validation must separately report:

- Navigation and prefill cutover.
- Per-category authorization.
- HP/Tuition generation correctness through Batch Studio.
- EGC generation correctness through Batch Studio.
- Legacy direct-write bypass prevention.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Prefill parsing accepts only supported keys; category-specific authorization helper, if added, maps `major` to `create_finance_charges` and `egc` to `generate_egc_finance_charges`. |
| Integration | Batch Studio previews and commits HP/Tuition and EGC with valid tokens; EGC-only operator can commit EGC but cannot commit HP/Tuition; old standalone POST routes cannot create charges without Batch Studio token verification. |
| E2E | Sidebar and key shortcuts land in Batch Studio charges with the intended category selected; no primary navigation labels expose standalone HP/Tuition or EGC generation pages. |
| Platform | Targeted ESLint/Prettier on changed Vue/TS files; targeted Pint on changed PHP files; production build or documented reason if full build cannot run. |
| Logs/Audit | `finance:audit-invariants` records no new critical findings after write-path validation, or the story explains why validation was navigation-only. |

## Fixtures

- Finance user with `view_finance_batch_studio` and `create_finance_charges`.
- EGC-only user with `view_finance_batch_studio` and
  `generate_egc_finance_charges`.
- Restricted finance user without either generation permission.
- Student eligible for HP/Tuition generation.
- Student eligible for EGC generation with block count coverage.
- Student already charged, to prove skip/warning behavior remains intact.

## Commands

Executed:

```text
./scripts/dev.sh test tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php tests/Feature/Finance/Http/Web/EgcChargeGenerationStoreTest.php tests/Feature/Finance/Dng/DngWorklistControllerTest.php tests/Feature/Finance/Batch/BatchDngCommitTest.php
./scripts/dev.sh artisan route:list --name=finance.batch-studio
./scripts/dev.sh composer exec pint -- --test app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/Http/Web/Admin/MajorChargeGenerationController.php app/Modules/Finance/Http/Web/Admin/EgcChargeGenerationController.php app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php tests/Feature/Finance/Http/Web/EgcChargeGenerationStoreTest.php
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue resources/js/components/finance/batch/PreviewDiffTable.vue resources/js/types/finance.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts resources/js/components/finance/cockpit/PhaseShortcuts.vue resources/js/pages/Finance/Operations/Dashboard.vue resources/js/pages/Finance/EgcOperations/Dashboard.vue
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue resources/js/components/finance/batch/PreviewDiffTable.vue resources/js/types/finance.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts resources/js/components/finance/cockpit/PhaseShortcuts.vue resources/js/pages/Finance/Operations/Dashboard.vue resources/js/pages/Finance/EgcOperations/Dashboard.vue
./scripts/dev.sh artisan finance:audit-invariants
git diff --check
```

## Evidence

- `finance/batch-studio/dng` was checked first against
  `FIN-REV-013-dng-payment-request-batch-studio-cutover`; it already had the
  required DNG cutover behavior: business naming, safe prefill, old worklist GET
  redirect, old POST 410, and token-based Batch Studio commit. No DNG code path
  was expanded for FIN-REV-015.
- Targeted PHP tests passed: 34 tests / 153 assertions.
- Route list shows 10 Batch Studio routes, including charge preview/commit and
  DNG preview/commit.
- Targeted ESLint passed for touched Vue/TS files.
- Targeted Prettier check passed after formatting
  `ChargeGeneration.vue` and `PreviewDiffTable.vue`.
- Targeted Pint passed via `composer exec pint -- --test` for 12 PHP/test files.
  The documented `./scripts/dev.sh artisan pint --test` remains unavailable in
  this app (`Command "pint" is not defined`).
- `git diff --check` passed.
- `./scripts/dev.sh npm run type-check` was attempted twice, including
  `NODE_OPTIONS=--max-old-space-size=4096`; both runs were killed with exit 137
  before diagnostics.
- `finance:audit-invariants` completed read-only. Existing baseline findings:
  INV-6 has 28 duplicate invoice groups and INV-13 has 1 live installment on a
  voided charge. All other checked invariants, including DNG idempotency and
  amount/pivot invariants, reported 0 offending rows.

## Follow-up Fix: Non-Academic Charge Preview

User-reported bug on `finance/batch-studio/charges`:

```text
POST /api/v1/finance/batch-studio/charges/preview
{"fee_category":"non_academic","semester_id":3,"scope":{"filters":[]}}

Undefined array key "charge_types"
```

Fix evidence:

- Batch Studio validates non-academic `fee_type` and `amount` before preview;
  the later confirmed design removes charge-level `due_date` from this flow.
- The preview endpoint validates missing non-academic scope fields with 422
  field errors instead of reaching a server error.
- Non-academic preview no longer delegates to the HP/EGC legacy
  `PreviewChargeGenerationQuery`; it builds BHYT preview rows directly and marks
  duplicate active charges as skip rows.
- Non-academic commit now uses `GenerateNonAcademicChargesAction`, preserving
  the preview-token safety contract while using the existing phi hoc vu write
  path.

Additional commands executed:

```text
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
./scripts/dev.sh composer exec pint -- --test app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php app/Modules/Finance/Http/Web/Admin/BatchStudioController.php tests/Feature/Finance/Batch/helpers.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue resources/js/utils/batchStudioDisplay.ts
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue resources/js/utils/batchStudioDisplay.ts
git diff --check
```

Additional result:

- Targeted Batch Studio charge tests passed: 13 tests / 42 assertions.
- Targeted Pint, ESLint, Prettier, and `git diff --check` passed.
- `./scripts/dev.sh npm run type-check` was attempted again and was killed with
  exit 137 before diagnostics.

## Follow-up Fix: Stale Non-Academic Scope on HP Preview

User-reported bug on `finance/batch-studio/charges`:

```text
POST /api/v1/finance/batch-studio/charges/preview
{"fee_category":"major","semester_id":3,"scope":{"filters":[],"fee_type":"bhyt","amount":"","due_date":"2026-07-18","note":""}}

scope.amount: validation.numeric, validation.min.numeric
```

Fix evidence:

- Added a failing regression test first for a `major` preview carrying stale
  non-academic fields with `amount=""`; verified it failed with 422.
- `PreviewBatchChargesRequest` now excludes non-academic-only fields unless
  `fee_category=non_academic`, so hidden/stale UI state cannot invalidate HP or
  EGC preview requests.
- `ChargeGeneration.vue` now trims scope back to `{ filters }` before previewing
  HP/EGC, so the client no longer sends hidden non-academic fields for those
  categories.

Additional commands executed:

```text
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php --filter "ignores stale non-academic scope fields"
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
./scripts/dev.sh composer exec pint -- --test app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
./scripts/dev.sh composer exec php -- -l app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php
git diff --check
```

Additional result:

- Regression test passed after the fix.
- Targeted Batch Studio charge tests passed: 14 tests / 47 assertions.
- Targeted Pint, ESLint, Prettier, PHP syntax check, and `git diff --check`
  passed.
- `./scripts/dev.sh npm run type-check` was attempted again and was killed with
  exit 137 before diagnostics.

HP/Tuition logic review notes from this pass:

- Current HP/Tuition preview path is
  `BatchStudioPreviewController -> PreviewBatchChargesRequest -> AssembleBatchChargePreviewQuery -> PreviewMajorChargeGenerationQuery`.
- Current HP/Tuition commit path is
  `BatchStudioController::commitCharges -> recomputeOrFail -> GenerateMajorChargesAction`.
- Preview and commit both restrict HP/Tuition candidates to
  `students.status = intake_course`, block an existing active
  `tuition_term` charge for the same semester, and use
  `StudentChargeTimingResolver` to derive term number and `TuitionPlanTerm.amount`.
- Scholarship and voucher preview/commit both go through the shared finance
  resolvers; commit creates the gross tuition charge and applies scholarship or
  voucher as invoice discounts.
- Resolved by the confirmed rules below: Batch Studio charge generation should
  not expose an operator due-date field; DNG owns the real due date.
- Resolved by the confirmed rules below: HP/Tuition candidate scope should
  include both `intake_course` and `intake_major` students.

## Follow-up Fix: Confirmed Due-Date and Intake-Major Rules

Business decisions confirmed on 2026-06-18:

- Batch Studio charge generation does not collect or use an operator payment
  due date. Charges are internal fee accrual; DNG owns the real payment due
  date. Legacy invoice rows still receive an internal non-null placeholder date
  because the existing invoice schema requires it.
- HP/Tuition generation includes both `intake_course` and `intake_major`
  students.

Fix evidence:

- `PreviewBatchChargesRequest` no longer validates `scope.due_date` for
  non-academic charge preview.
- `CommitBatchChargesRequest` no longer accepts `due_date`; commit ignores any
  stale due-date payload and uses the internal invoice placeholder only for
  existing writer compatibility.
- `ChargeGeneration.vue` no longer renders or sends a charge due-date field for
  non-academic charges.
- HP/Tuition preview and commit both filter students with
  `status in (intake_course, intake_major)`.

Additional commands executed:

```text
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php --filter "includes intake-major students"
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php --filter "without requiring a charge due date"
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargeCommitTest.php --filter "intake-major"
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargeCommitTest.php --filter "non-academic"
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
./scripts/dev.sh composer exec pint -- --test app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php app/Modules/Finance/Actions/Major/GenerateMajorChargesAction.php tests/Feature/Finance/Batch/helpers.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
./scripts/dev.sh npm exec eslint resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
git diff --check
./scripts/dev.sh npm run type-check
```

Additional result:

- Focused regression tests passed after the fix.
- Targeted Batch Studio charge tests passed: 16 tests / 54 assertions.
- Targeted Pint, ESLint, Prettier, and `git diff --check` passed.
- `./scripts/dev.sh npm run type-check` was attempted again and was killed with
  exit 137 before diagnostics.
