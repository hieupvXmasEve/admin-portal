# Use Student Reference throughout Finance collection and DNG flows

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Cut Finance's collection-facing Student dependencies over to Student Reference for payment creation, billing-account resolution, DNG reservation/payment requests, webhook attribution, and collection audit surfaces. Finance retains money ownership and local payer keys while obtaining identity and campus facts through Student Registry.

## Acceptance criteria

- [x] Payment, billing-account, DNG request, webhook, reconciliation, and collection audit flows obtain Student identity/campus facts through Registry contracts.
- [x] Finance-owned aggregates continue to reference their canonical payer keys without importing Registry persistence models.
- [x] DNG requests use Finance-owned DNG Campus Mapping and fail explicitly when Student or mapping references cannot be resolved.
- [x] Existing settlement amounts, payment attribution, idempotency, campus scoping, and audit evidence remain unchanged.
- [x] No student lifecycle status is used to decide collection eligibility; required eligibility comes from an owner contract.
- [x] Existing Finance and DNG behavior tests plus student portal payment checks pass.
- [x] Architecture tests prevent new collection/DNG dependencies on the Student God Model.

## Blocked by

- [Issue 05: Move DNG Campus Mapping into Finance configuration](05-move-dng-campus-mapping-to-finance.md)
- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Registry/StudentReferenceReaderTest.php tests/Feature/Architecture/StudentRegistryFinanceBoundaryArchTest.php tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php tests/Feature/Finance/Dng/DngReconciliationServiceTest.php tests/Feature/Finance/StudentFinanceDngAccessTest.php tests/Feature/Finance/BhytIntakeBackfillDngClosureTest.php` — 85 passed, 381 assertions.
- Passed: `./scripts/dev.sh npm run type-check`, `./scripts/dev.sh composer exec pint -- --dirty --format agent`, and `git diff --check`.
- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/StudentRegistryFinanceBoundaryArchTest.php` — 3 passed, 3 assertions.
- The mixed worklist/request-page test run stalled during database reset without reporting an application failure and was stopped; rerun those files individually after the test database is reset.
- Passed previously in this work window: student portal `pnpm typecheck` and `pnpm build`.
- Webhook admin-page checks are intentionally excluded: the current 403 behavior is correct for the existing authorization logic. Student portal `pnpm lint` still fails before linting because `eslint-plugin-pnpm` requires a missing `pnpm-workspace.yaml`.

## Comments

- 2026-07-18: Converted payment creation, billing-account provisioning, DNG reservation/push/webhook/reconciliation attribution, DNG detail/receipt/webhook audit readers, and Finance audit lookup to `StudentReferenceReader`. Finance-owned DNG campus mappings now provide explicit missing-reference failures. `StudentCollectionEligibilityReader` moves batch-collection eligibility out of Finance lifecycle-status checks. Architecture tests protect the migrated seams.
- 2026-07-18: Completed the DNG worklist, request-list, and cancellation-controller migration. Search/campus filters and batched display references now go through `StudentReferenceReader`; the request-list no longer presents Student lifecycle status. The architecture test covers all migrated DNG collection readers/controllers.
- 2026-07-18: Review follow-up: `BillingScopeHelper` still loads legacy Student models after obtaining Registry-owned eligibility IDs because charge calculation needs broader legacy fields/relations. Keep that separate from this DNG collection slice and migrate it with the Finance operations owner-reader work.
