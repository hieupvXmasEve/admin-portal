# Validation

## Proof Strategy

Prove Finance users receive semester context, non-Finance users do not, and
session selection persists after posting the update route.

## Test Plan

| Layer | Cases |
| --- | --- |
| Integration | `SemesterContextTest` covers shared prop, omitted prop, and session persistence. |
| Platform | Switcher lint/build covers frontend integration. |
| Logs/Audit | Not applicable; session-only change. |

## Fixtures

- Active semester.
- Non-active semester.
- User with Finance shell permission.
- User without Finance permission.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Shell/SemesterContextTest.php
./scripts/dev.sh npm run lint -- resources/js/components/finance/SemesterSwitcher.vue resources/js/types/index.ts
./scripts/dev.sh npm run build
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- `tests/Feature/Finance/Shell/SemesterContextTest.php` passed with 3 cases.
- Per-file frontend lint and production build were recorded successful.

No product tests were re-run during the story split.
