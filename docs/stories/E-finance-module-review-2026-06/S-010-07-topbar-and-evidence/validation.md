# Validation

## Proof Strategy

Prove the shell pieces work together and the milestone is honestly recorded.

## Test Plan

| Layer | Cases |
| --- | --- |
| Integration | Student360, Search, Shell, and Audit regression suites pass. |
| Platform | `AppSidebarHeader.vue` lint/build succeeds. |
| E2E | Manual smoke checks command palette, Student 360 navigation, invoice focus link, semester switcher, and sidebar IA. |
| Logs/Audit | Harness trace records no money state changed. |

## Fixtures

- Finance user with shell permissions.
- Known student code.
- Known invoice number.
- At least two semesters.

## Commands

```text
./scripts/dev.sh npm run lint -- resources/js/components/AppSidebarHeader.vue
./scripts/dev.sh test tests/Feature/Finance/Student360 tests/Feature/Finance/Search tests/Feature/Finance/Shell
./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php
./scripts/dev.sh npm run build
./scripts/harness trace --summary "S-010 Milestone 1: finance shell + Student 360 foundation" --story FIN-REV-010-07-topbar-and-evidence --actions "topbar mount, full milestone validation, browser smoke evidence" --changed "resources/js/components/AppSidebarHeader.vue; S-010 validation docs" --outcome completed --friction "no JS unit runner; vue-tsc OOM in dev container; manual browser smoke required"
```

## Acceptance Evidence

Recorded from S-010 Milestone 1 evidence:

- Student360/Search/Shell/Audit Pest suites passed: 15 tests, 92 assertions.
- Production build was recorded successful.
- Manual browser smoke remains listed as manual because no Playwright/headless
  harness is configured.
- No money state changed.

No product tests were re-run during the story split.
