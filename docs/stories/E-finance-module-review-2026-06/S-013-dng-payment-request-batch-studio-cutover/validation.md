# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Proof Strategy

Prove that the old standalone DNG Worklist no longer acts as a separate bulk
write surface, while Batch Studio still creates DNG payment requests with the
same authorization, preview-token, charge-linkage, and audit guarantees.

Validation must separately report:

- Navigation/copy cutover.
- Authorization and direct-write bypass prevention.
- DNG creation correctness through Batch Studio.
- Audit/read page preservation.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Candidate mapping still returns the same student/charge rows for HP/HL/other supported fee types; naming helper/copy constants, if added, produce the canonical labels. |
| Integration | `GET finance.operations.dng-worklist` redirects/forwards to `finance.batch-studio.dng`; `POST finance.operations.dng-worklist.store` cannot create DNG requests; Batch Studio DNG preview/commit require the existing permissions and a valid preview token; commit still links charges to created DNG requests. |
| E2E | Sidebar, Cockpit shortcuts, and Lookup bulk action route to Batch Studio DNG; user-facing labels show `Lập yêu cầu thanh toán DNG` / `Yêu cầu thanh toán DNG`; no primary menu/page title says `DNG Worklist` or `Push DNG`. |
| Platform | Targeted eslint/prettier on changed Vue/TS files; production build or documented reason if full build cannot run. |
| Performance | Candidate query remains paginated/bounded; prefill with selected ids must not load unbounded all-student rows. |
| Logs/Audit | Created DNG payment requests retain request/response audit, linked charge pivots, and webhook compatibility; `finance:audit-invariants` records no new critical findings after exercised write path. |

## Fixtures

- Finance user with `view_finance_batch_studio`, `create_finance_payments`, and
  `void_finance_charges`.
- Finance user with `create_finance_payments` but missing
  `void_finance_charges`.
- Restricted finance user without DNG creation permission.
- Student with eligible HP charges and no active DNG.
- Student with eligible charges and an active unpaid DNG to exercise
  rerun-cancels-old-DNG warnings.
- Student/charge case requiring `dng_payment_request_charges` linkage.
- Optional retake/resit fee-type fixture only if the current DNG candidate query
  already supports it without expanding business scope.

## Commands

Add exact commands during implementation. Expected minimum:

```text
./scripts/dev.sh test tests/Feature/Finance/Dng/DngWorklistControllerTest.php
./scripts/dev.sh test tests/Feature/Finance/Batch/BatchDngCommitTest.php
./scripts/dev.sh test tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run build
./scripts/dev.sh artisan finance:audit-invariants
```

If repo-wide frontend checks fail from known baseline drift, record the filtered
touched-file diagnostics and the baseline failure separately.

## Acceptance Evidence

Implemented and verified on 2026-06-17.

Passing checks:

```text
./scripts/dev.sh test tests/Feature/Finance/Dng/DngWorklistControllerTest.php
PASS: 6 tests, 23 assertions

./scripts/dev.sh test tests/Feature/Finance/Batch/BatchDngCommitTest.php
PASS: 3 tests, 5 assertions

./scripts/dev.sh test tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
PASS: 15 tests, 37 assertions

./scripts/dev.sh test tests/Feature/Finance/Batch/BatchStudioAuthzTest.php
PASS: 3 tests, 21 assertions

./scripts/dev.sh test tests/Feature/Finance/Cockpit/CockpitOverviewTest.php
PASS: 4 tests, 34 assertions

./scripts/dev.sh test tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php
PASS: 6 tests, 55 assertions

./scripts/dev.sh npm exec eslint -- resources/js/pages/Finance/BatchStudio/DngPush.vue resources/js/pages/Finance/BatchStudio/Hub.vue resources/js/constants/menu-sidebar.ts resources/js/components/finance/cockpit/PhaseShortcuts.vue resources/js/components/finance/lookup/SendToBatchBar.vue
PASS

./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/BatchStudio/DngPush.vue resources/js/pages/Finance/BatchStudio/Hub.vue resources/js/constants/menu-sidebar.ts resources/js/components/finance/cockpit/PhaseShortcuts.vue resources/js/components/finance/lookup/SendToBatchBar.vue
PASS

./scripts/dev.sh composer exec pint -- --test app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/Http/Web/Admin/DngWorklistController.php app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php app/Modules/Finance/routes/web.php tests/Feature/Finance/Dng/DngWorklistControllerTest.php tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php tests/Feature/Finance/Cockpit/CockpitOverviewTest.php
PASS: 7 files

git diff --check -- <story touched files>
PASS

./scripts/dev.sh npm run build
PASS

./scripts/dev.sh artisan finance:audit-invariants
PASS command exit: DNG-specific invariants INV-12, INV-14, INV-15 all show 0 offending rows.
```

Baseline / environment notes:

```text
./scripts/dev.sh npm run lint
FAIL baseline: eslint scans ignored/retired worktrees, FE/student-nuxt, docs-site, and existing app files; 5157 unrelated errors reported.

./scripts/dev.sh npm run type-check
FAIL environment: vue-tsc killed with exit 137.

./scripts/dev.sh npm exec env NODE_OPTIONS=--max-old-space-size=4096 vue-tsc --noEmit
FAIL environment: vue-tsc killed with SIGKILL before diagnostics.

./scripts/dev.sh npm run format:check
FAIL baseline/environment: many unrelated resources formatting warnings, then killed with exit 137.

./scripts/dev.sh artisan pint --test ...
FAIL command availability: artisan command "pint" is not defined; Composer Pint was used instead.

./scripts/dev.sh artisan finance:audit-invariants
Existing data baseline still reports INV-6 duplicate invoice groups = 28 and INV-13 live installment on voided charge = 1; DNG amount/linkage invariants remain 0.
```

Testing caveat: running multiple Pest files in a single command corrupted/raced the
testing database in this local Docker setup, so the final verification above ran
each targeted file sequentially. The testing DB was repaired with
`./scripts/dev.sh artisan migrate:fresh --env=testing` before the final green
sequential run.
