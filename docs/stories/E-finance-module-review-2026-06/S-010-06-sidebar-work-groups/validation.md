# Validation

## Proof Strategy

Prove the sidebar compiles, preserves route destinations and permission gates,
and reflects the menu migration table.

## Test Plan

| Layer | Cases |
| --- | --- |
| Platform | `menu-sidebar.ts` lint/build succeeds. |
| E2E | Manual smoke checks five work groups plus Discounts & Funding. |
| Logs/Audit | Not applicable; navigation-only change. |

## Fixtures

- Finance user with broad permissions for sidebar smoke.
- User with limited permissions for hidden-item smoke.

## Commands

```text
./scripts/dev.sh npm run lint -- resources/js/constants/menu-sidebar.ts
./scripts/dev.sh npm run build
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- Finance Office menu migration table is recorded in umbrella validation.
- Per-file frontend lint and production build were recorded successful.
- Manual UI smoke remains listed as manual because no Playwright/headless
  harness is configured.

No product tests were re-run during the story split.
