# Validation

## Proof Strategy

This story is mostly route-contract and navigation-role work. Proof must show
that the backend detail routes remain stable while the primary Finance Office
experience stays task-first.

Validation must prove:

- Charge, invoice, payment, and DNG request detail route names exist and resolve
  to their current URLs.
- Each detail route still requires its current read permission.
- Repair actions under detail pages keep stricter write permissions.
- Lookup, Audit Workspace, Student 360/Cockpit/DNG, and webhook surfaces still
  produce valid named-route deep links.
- Sidebar/task-first navigation does not expose record-specific detail pages as
  primary destinations.
- No money, DNG, settlement, or audit invariant changes are introduced by this
  route-role story.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Route helper/constant tests for detail route names if finance route helpers are touched |
| Integration | `FinanceDetailRouteContractTest`: `finance.charges.show`, `finance.invoices.show`, `finance.payments.show`, `finance.dng.payment-requests.show` existence, `200/403` permission matrix, model binding |
| Integration | Repair-route permission checks for charge void, invoice line void, payment allocate, DNG cancel impact/review |
| Integration | Audit Workspace source-route map contains valid named routes for invoice, charge, payment, DNG |
| E2E | Manual smoke: Lookup row action opens detail; Audit graph link opens detail; DNG webhook/request cross-link opens detail; back/source navigation remains coherent |
| Platform | Route-list check for exact route names and targeted eslint if Vue/TS files change |
| Logs/Audit | `finance:audit-invariants` read-only sanity if implementation touches any repair surface |

## Fixtures

- One finance charge with a linked invoice line.
- One invoice with at least one active line.
- One payment with application/unapplied context.
- One DNG payment request with linked charge(s) and, when possible, linked
  webhook/payment evidence.
- Users with only read permissions, users with repair permissions, and users
  without Finance permissions.

## Commands

Add exact commands after implementation selects the touched files. Expected
starting point:

```text
./scripts/dev.sh artisan route:list --name=finance.charges.show
./scripts/dev.sh artisan route:list --name=finance.invoices.show
./scripts/dev.sh artisan route:list --name=finance.payments.show
./scripts/dev.sh artisan route:list --name=finance.dng.payment-requests.show
./scripts/dev.sh test tests/Feature/Finance/DetailRouteContractTest.php
./scripts/dev.sh test tests/Feature/Finance/Lookup
./scripts/dev.sh test tests/Feature/Finance/Audit
./scripts/dev.sh artisan finance:audit-invariants
```

If Vue/TS link sources change, also run targeted lint for the changed files:

```text
./scripts/dev.sh npm run lint -- <changed finance Vue/TS paths>
```

## Acceptance Evidence

### FIN-REV-014 — Detail / Deep-Link / Audit / Repair Routes

Scope: keep charge, invoice, payment, and DNG request backend detail routes
intact while classifying them as secondary detail/deep-link/audit/repair pages.

Portal impact: none.

Status: implemented — automated evidence recorded 2026-06-17.

#### Red / Green

- RED: `./scripts/dev.sh test tests/Feature/Finance/DetailRouteContractTest.php`
  failed on the missing frontend route helper contract:
  `CHARGES_SHOW: 'finance.charges.show'`.
- GREEN: `./scripts/dev.sh test tests/Feature/Finance/DetailRouteContractTest.php`
  passed: 5 tests, 68 assertions.

#### Automated acceptance

- [x] `./scripts/dev.sh test tests/Feature/Finance/DetailRouteContractTest.php`
  — 5 passed, 68 assertions.
- [x] `./scripts/dev.sh artisan route:list --name=finance.charges.show`
  — admin `finance/charges/{charge}` route present.
- [x] `./scripts/dev.sh artisan route:list --name=finance.invoices.show`
  — admin `finance/invoices/{invoice}` route present.
- [x] `./scripts/dev.sh artisan route:list --name=finance.payments.show`
  — admin `finance/payments/{payment}` route present.
- [x] `./scripts/dev.sh artisan route:list --name=finance.dng.payment-requests.show`
  — admin `finance/dng/payment-requests/{dngPaymentRequest}` route present.
- [x] `./scripts/dev.sh npm exec eslint -- <changed finance Vue/TS paths>`
  — no errors.
- [x] `./scripts/dev.sh npm exec prettier --check <changed finance Vue/TS paths>`
  — all matched files use Prettier style.
- [x] `./scripts/dev.sh composer exec pint -- --test tests/Feature/Finance/DetailRouteContractTest.php`
  — 1 file passed.
- [x] `git diff --check -- <changed story/test/frontend files>`
  — no whitespace errors.

#### Type-check / broader suite notes

- `./scripts/dev.sh npm run type-check` was attempted and killed with exit 137.
- `./scripts/dev.sh npm exec vue-tsc --noEmit --pretty false` was attempted and
  killed with SIGKILL.
- `./scripts/dev.sh npm exec node --max-old-space-size=4096 node_modules/vue-tsc/bin/vue-tsc --noEmit --pretty false`
  was attempted and killed with SIGKILL.
- `tests/Feature/Finance/Lookup` and `tests/Feature/Finance/Audit` were attempted
  concurrently and both collided on shared `db_test` `RefreshDatabase`
  migrations before assertions. These runs are not counted as product evidence.

#### What the implementation changed

- Added `tests/Feature/Finance/DetailRouteContractTest.php` to prove detail route
  stability, repair-route permissions, Audit Workspace source route mapping,
  frontend route helper presence, and primary-sidebar non-promotion.
- Added detail route constants/helpers for charge, invoice, payment, and DNG
  request detail pages.
- Switched the main lookup/DNG list deep-link sources to the finance route
  helpers instead of ad hoc detail route strings.
- Updated the Finance UX design doc and epic README to classify these pages as
  secondary detail/deep-link/audit/repair surfaces.
