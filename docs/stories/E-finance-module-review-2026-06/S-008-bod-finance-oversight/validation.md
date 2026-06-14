# Validation

## Proof Strategy

Prove the BOD page is read-only, permission-scoped, and uses aggregate formulas
consistent with the accepted Finance source of truth.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Aggregate formula helpers, charge type label mapping |
| Integration | Permission access, query filters, drill-down route generation |
| Integration | A student obligation visible on both invoice and DNG rails is counted once |
| E2E | Browser smoke for BOD allowed and non-BOD denied paths |
| Platform | Frontend build/type/lint |
| Performance | Aggregate query timing and no per-row PHP loops |
| Logs/Audit | No write audit because no mutations are allowed |

## Fixtures

- Multiple semesters.
- Multiple charge types.
- Paid, partially paid, overdue, and outstanding invoices.
- Student with both invoice debt and open DNG request for the same obligation.
- User with BOD permission and user without it.

## Commands

```text
./scripts/dev.sh test --filter=Bod
./scripts/dev.sh test --filter=Finance
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run build
./scripts/dev.sh artisan pint
git diff --check
```

## Acceptance Evidence

Add permission test output, aggregate examples, and browser smoke notes after
implementation.
