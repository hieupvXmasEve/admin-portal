# Close Finance obligation, settlement and collection Migration Debt

Status: ready-for-human

Portal impact: student

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Complete the supported Finance cutover for obligation intake, entitlements, settlement, invoicing, collection, DNG, operations, reporting, and student-facing Finance reads. Consume the existing Finance migration trackers as the detailed source of truth and close their remaining runtime bypasses without inventing a second ledger or local balance formula.

## Acceptance criteria

- [ ] Every supported debit, credit, discount, payable, settlement, invoice, and collection path uses the accepted Finance-owned aggregate and ledger boundaries.
- [ ] Academic, Registry, Institution, and Progression facts enter through approved contracts/events/references rather than persistence coupling.
- [ ] Billing Exceptions, settlement worklists, DNG, reporting, student Finance APIs, and scheduled reconciliation remain behaviorally compatible.
- [ ] Verified cash and raw provider evidence are never discarded, guessed, clamped, or silently rewritten.
- [ ] Any backfill, invariant correction, source-pointer cleanup, or schema drop stops for the parent issue's explicit data approval gate.

## Blocked by

- [Migrate Institution & Organization reference management](05-migrate-institution-organization-references.md)
- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md)

## Authoritative Finance evidence

- [Finance Legacy Retirement — issue 08](../../finance-legacy-retirement/issues/08-enforce-zero-finance-bypass.md) remains `ready-for-human` pending final release checks and production canonical-total reconciliation.
- [Finance Legacy Retirement — issue 18](../../finance-legacy-retirement/issues/18-reconcile-invariants-and-close-release-evidence.md) remains `ready-for-human` pending an approved disposition and before/after reconciliation for recorded INV-13 charge `#1067` / `AUH120849` and INV-18 invoice `#1443` / `AUH111841`.
- The Finance Obligation v2 and Finance Legacy Retirement trackers above remain the implementation source of truth; this issue records only repository-migration closure evidence.

## Verification

- 2026-07-24 read-only local verification: `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` passed without increasing any approved baseline; `SettlementBypassArchitectureTest`, `MaterializerOnlyFinanceChargeArchTest`, and `RetiredFinanceMigrationToolsTest` passed (14 tests, 68 assertions); the scoped Finance settlement, DNG, invariant, and void-release suite passed (47 tests, 267 assertions); Pint, `git diff --check`, and root TypeScript type-check passed.
- `./scripts/dev.sh artisan finance:audit-invariants` is read-only and reported zero offending rows for INV-1 through INV-19 on the currently configured local development dataset. This observation does not supersede the two unresolved exception records in Finance Legacy Retirement issue 18; no write or approved reconciliation explains the different local result. Student and lecturer portal working trees were clean; no API contract or portal source changed.
- The required full-suite run was initiated on 2026-07-24, but the wrapper did not return a final result and no in-container test process remained when checked. This unresolved environment-level verification does not authorize a Financial data correction or release decision.

## Comments

- 2026-07-24: The supported Finance code cutover is covered by the existing Finance Obligation v2 and Finance Legacy Retirement trackers. This issue remains `ready-for-human` rather than completed because production canonical-total reconciliation and any data-affecting exception disposition require the parent issue's explicit approval gate. No backfill, invariant correction, source-pointer cleanup, schema mutation, cash allocation, or provider-evidence rewrite was attempted here. The current local audit is evidence only and cannot substitute for the required production reconciliation, explicit disposition of issue 18's recorded exceptions, and human approval.
