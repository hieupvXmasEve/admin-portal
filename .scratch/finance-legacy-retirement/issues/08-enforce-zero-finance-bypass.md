# 08 — Remove cutover compatibility and enforce zero Finance bypass

Status: ready-for-human

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Finish the retirement after all consumers and writers are canonical. Remove cutover-only shadow reports, mismatch wrappers, DNG migration inventory/backfill code, and the temporary settlement bypass allowlist. Replace the migration-era guard with a permanent zero-bypass architecture rule.

The approved cutover evidence is the completed production backfill, manual verification, issue-level reconciliation, and final invariant suite. No additional seven-day shadow period is required.

## Acceptance criteria

- [x] Shadow runner/report/mismatch code with no production responsibility is removed after its final characterization tests are replaced by canonical contract tests.
- [x] DNG cutover inventory/backfill command and its migration-only dependencies are removed.
- [x] Settlement bypass allowlist is deleted or permanently empty; no production path is grandfathered.
- [x] Architecture tests fail on any new local settlement formula, direct protected-table writer, source-side Finance pointer, or legacy DNG creation path.
- [x] Repository-wide searches find no runtime reference to the retired routes, commands, services, constants, pointers, or compatibility classes.
- [ ] Final Finance invariant, focused feature, architecture, portal-impact, static-analysis, and formatting checks pass.
- [ ] Production canonical totals reconcile with the accepted post-backfill data truth, excluding only the approved issue-01 repairs.

## Verification

- Removed `finance:dng-cutover`, its inventory/backfill/report classes, the temporary DNG collection gate/config, the settlement shadow family, and the bypass allowlist. DNG creation remains at the guarded reservation or Finance cancellation-replacement seams.
- Canonical Settlement Position now supplies all remaining discount-capacity and post-reduction overpayment calculations. `VoidReleaseAllocationTest` fixtures now create canonical obligations with a VND currency; the void path defers its intermediate cache rebuild until the canonical surplus release is complete.
- Focused Finance proof: `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/MaterializerOnlyFinanceChargeArchTest.php tests/Feature/Architecture/SettlementBypassArchitectureTest.php tests/Feature/Architecture/RetiredFinanceMigrationToolsTest.php tests/Feature/Finance/Dng/DngPaymentServiceTest.php tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php tests/Feature/Finance/SettlementPosition/CurrentPayableSettlementPositionReaderTest.php tests/Feature/Finance/VoidReleaseAllocationTest.php` — 38 passed / 217 assertions.
- Full wrapper test command `./scripts/dev.sh test`, `./scripts/dev.sh npm run type-check`, and `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed. `git diff --check` passed. `./scripts/portal-status.sh` found clean student and lecturer nested repositories; no API payload or portal code changed.
- Runtime source/command scans report no retired runtime reference and no source-model `finance_charge_id` / `finance_obligation_id` pointer. Architecture and spec reviews found no unresolved code finding after the zero-formula guard was tightened.
- Local read-only `finance:audit-invariants --sample` remains non-zero only for existing INV-13 sample `1067` and INV-18 sample `1443`; INV-19 is zero and all other reported invariants are zero.

## Comments

- 2026-07-14: Code and local checks are ready for human release evidence review. Do not mark this issue completed yet: production canonical totals have not been reconciled from this workspace, the local invariant audit still needs an explicit disposition for INV-13 `1067` and INV-18 `1443`, and issue 04 remains `ready-for-agent` in this tracker.

## Blocked by

- [Issue 02 — Remove retired Finance and DNG surfaces](02-remove-retired-finance-dng-surfaces.md)
- [Issue 03 — Converge DNG creation on guarded reservations](03-converge-dng-creation-on-guarded-reservations.md)
- [Issue 04 — Converge settlement reads on Settlement Position](04-converge-settlement-reads.md)
- [Issue 05 — Route settlement mutations through the guard](05-route-settlement-mutations-through-guard.md)
- [Issue 06 — Remove legacy Finance source pointers](06-remove-legacy-source-pointers.md)
- [Issue 07 — Retire migration-only tools and legacy types](07-retire-migration-tools-and-legacy-types.md)
