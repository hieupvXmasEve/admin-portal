# Validation

## Proof Strategy

Prove Student 360 renders for visible students, denies unauthorized users, hides
cross-campus students, and accepts valid focus links.

## Test Plan

| Layer | Cases |
| --- | --- |
| Integration | `StudentOverviewShellTest` covers render, focus, permission denial, campus 404, and all-campus override. |
| Platform | Page lint/build covers Vue/Inertia integration. |
| Performance | Ledger is deferred and not eagerly loaded in initial page props. |
| Logs/Audit | No audit log required; route is read-only. |

## Fixtures

- Current campus.
- Other campus.
- Student in each campus.
- Finance user with and without overview/all-campus permissions.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php
./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Student360/Show.vue resources/js/types/finance.ts
./scripts/dev.sh npm run build
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- `tests/Feature/Finance/Student360/StudentOverviewShellTest.php` passed with 5
  cases.
- Production build was recorded successful.
- No money state changed.

No product tests were re-run during the story split.
