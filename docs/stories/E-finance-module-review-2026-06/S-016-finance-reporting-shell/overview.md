# FIN-REV-016 - Finance Reporting Shell

## Status

implemented

## Lane

normal

## Product Contract

Create the first runtime Finance Reporting surface as a read-only Finance Office
page. The shell owns the route, permission, sidebar entry, account-campus
context, shared Finance semester context, freshness conventions, and URL-backed
active view state for the three accepted reporting lenses.

This story must not build the final reporting metrics or worklists. It may show
view-specific empty/loading states and static lens metadata only. Each real data
lens is implemented in a later story so query correctness and validation stay
small.

## Relevant Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-012-fee-tracking-reporting-requirements/overview.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-cutover-and-uat/`
- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Portal Impact

none

This is an admin/staff Finance Office web UI surface. It must not change
student or lecturer portal API contracts.

## Acceptance Criteria

- Add a read-only `finance.reporting.index` route at `/finance/reporting`.
- Add a thin `view_finance_reporting` permission and map it to finance-capable
  staff roles that can already use the new Finance Office workspace.
- Add a Finance Office sidebar entry for Reporting.
- Render one Inertia page with three views: `fee-monitor`,
  `collection-progress`, and `dng-lifecycle`.
- Persist the active view in URL/navigation state, for example
  `/finance/reporting?view=collection-progress`.
- Default the first visit to `collection-progress`.
- Keep campus scope implicit from the authenticated account/current session.
- Use the shared Finance semester prop/context for the shell; do not add a local
  semester selector.
- Show operational freshness conventions on the shell, even if lens data is
  still empty in this story.
- Do not expose export, mutation, void, cancel, retry, allocation, or fee
  generation actions on the Reporting page.

## Design Notes

- Commands: none.
- Queries: shell metadata only; real query classes belong to lens stories.
- API: read-only Inertia page.
- Tables: no new tables.
- Domain rules: campus is implicit; selected semester is shell context; DNG lens
  will not be hard-filtered by semester in its later story.
- UI surfaces: `resources/js/pages/Finance/Reporting/Index.vue`.

## Validation

| Layer       | Expected proof                                                                                 |
| ----------- | ---------------------------------------------------------------------------------------------- |
| Unit        | Permission config/seeder parity if existing tests cover permission declarations.                |
| Integration | Route registration and access test denies without `view_finance_reporting` and renders with it. |
| E2E         | Not required for shell-only story unless browser tooling is already available.                  |
| Platform    | Sidebar/route constants lint and formatting checks for touched files.                           |
| Release     | Harness matrix row with proof and no portal impact.                                             |

## Harness Delta

Register this story as the first implementation slice after `FIN-REV-012`.

## Evidence

Implemented on 2026-06-18.

- Added `finance.reporting.index` at `/finance/reporting` with
  `can:view_finance_reporting`.
- Added thin `view_finance_reporting` permission and seeded it for
  finance-capable staff roles.
- Added Finance Office sidebar entry and typed route helper.
- Added `Finance/Reporting/Index.vue` shell with three URL-backed views,
  default `collection-progress`, shared semester context, freshness timestamp,
  and no export/mutation actions.
- Targeted RED confirmed first:
  `./scripts/dev.sh test tests/Feature/Finance/Reporting/FinanceReportingShellTest.php`
  failed because `finance.reporting.index` was missing.
- Targeted GREEN:
  `./scripts/dev.sh test tests/Feature/Finance/Reporting/FinanceReportingShellTest.php`
  passed, 5 tests / 58 assertions.
- `./scripts/dev.sh artisan route:list --path=finance/reporting` passed and
  showed the Reporting route.
- `./scripts/dev.sh composer exec pint -- app/Modules/Finance/Http/Web/Admin/FinanceReportingController.php tests/Feature/Finance/Reporting/FinanceReportingShellTest.php config/permission.php database/seeders/InitialSetup/RoleAndPermissionSeeder.php app/Http/Middleware/HandleInertiaRequests.php app/Modules/Finance/routes/web.php`
  passed.
- `./scripts/dev.sh npm exec eslint resources/js/pages/Finance/Reporting/Index.vue resources/js/constants/finance-routes.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts`
  passed.
- `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Finance/Reporting/Index.vue resources/js/constants/finance-routes.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts`
  passed.
