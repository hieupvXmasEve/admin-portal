# Validation

## Proof Strategy

Prove the endpoint resolves valid identifiers, maps non-student targets to the
owning Student 360 URL, filters cross-campus identifiers, and denies users
without the overview permission.

## Test Plan

| Layer | Cases |
| --- | --- |
| Integration | `FinanceGlobalSearchTest` covers student code, invoice mapping, cross-campus empty result, and forbidden access. |
| Platform | Palette lint/build covers frontend API integration. |
| Logs/Audit | No audit log required; endpoint is read-only. |

## Fixtures

- Student in current campus.
- Student in other campus.
- Student invoice owned by the current-campus student.
- Finance user with and without overview permission.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Search/FinanceGlobalSearchTest.php
./scripts/dev.sh npm run lint -- resources/js/components/finance/FinanceCommandPalette.vue
./scripts/dev.sh npm run build
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php` passed with 4
  cases.
- Per-file frontend lint and production build were recorded successful.

No product tests were re-run during the story split.
