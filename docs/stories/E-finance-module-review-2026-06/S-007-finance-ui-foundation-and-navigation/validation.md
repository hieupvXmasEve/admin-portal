# Validation

## Proof Strategy

Prove touched Finance UI pages follow current frontend rules and no longer block
transaction tracing through missing links or runtime errors.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Shared formatter/status helpers |
| Integration | Backend props include route/link ids for touched pages |
| E2E | Browser smoke for each touched page group and dangerous modal |
| Platform | Vite build and Docker-wrapper npm checks |
| Performance | Inertia partial/deferred payloads stay bounded |
| Logs/Audit | No runtime console errors for touched pages |

## Fixtures

- Charge with invoice line.
- Payment with linked invoice/application.
- DNG request with linked charge/payment.
- Overpaid/credit display case.

## Commands

```text
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run build
./scripts/dev.sh test --filter=Finance
./scripts/dev.sh artisan pint
git diff --check
```

## Acceptance Evidence

Add check output and screenshots/smoke notes after implementation.
