# S-001 Sidebar Group Labels

## Status

implemented

## Lane

normal

## Product Contract

Staff sidebar navigation is grouped by operational area with visible sidebar group labels, while existing screen links and permission-aware visibility remain unchanged.

## Relevant Product Docs

- `docs/design-guidelines.md`
- `resources/js/constants/menu-sidebar.ts`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/NavMain.vue`

## Portal Impact

none

## Acceptance Criteria

- Sidebar renders visible group labels that separate current features by staff-facing operational area.
- Existing leaf `href` values remain unchanged.
- Existing `requiredPermissions` checks continue to hide unavailable menu items and empty groups.
- `Reports & Analytics` is no longer the catch-all navigation bucket for academic/student reports.

## Design Notes

- Commands:
- Queries:
- API:
- Tables:
- Domain rules:
- UI surfaces: main app sidebar only.

## Validation

| Layer       | Expected proof                                                                      |
| ----------- | ----------------------------------------------------------------------------------- |
| Unit        | Not required for static navigation grouping.                                        |
| Integration | Not required; no backend/API contract changes.                                      |
| E2E         | Rendered sidebar smoke check when app can be launched.                              |
| Platform    | `./scripts/dev.sh npm run type-check`; `./scripts/dev.sh npm run lint` if feasible. |
| Release     | Not required.                                                                       |

## Harness Delta

No Harness changes expected.

## Evidence

- `./scripts/dev.sh npm exec prettier --check resources/js/constants/menu-sidebar.ts resources/js/components/NavMain.vue resources/js/components/AppSidebar.vue resources/js/composables/usePermissions.ts resources/js/types/index.d.ts docs/stories/E-navigation/S-001-sidebar-group-labels.md` passed.
- `./scripts/dev.sh npm exec eslint resources/js/constants/menu-sidebar.ts resources/js/components/NavMain.vue resources/js/components/AppSidebar.vue resources/js/composables/usePermissions.ts` passed.
- `./scripts/dev.sh npm run build` passed.
- `./scripts/dev.sh npm run type-check` failed with Node heap OOM at the default 2GB heap.
- `./scripts/dev.sh npm exec env NODE_OPTIONS=--max-old-space-size=4096 vue-tsc --noEmit` completed but failed on existing repo-wide type diagnostics; no diagnostics were reported for the touched sidebar files.
- Static href comparison between `HEAD:resources/js/constants/menu-sidebar.ts` and the updated file found 84 non-placeholder hrefs before and 84 after, with no diff.
- Rendered browser screenshot was not captured because Browser plugin is absent and Playwright/Puppeteer/Chrome are not installed in the local/runtime environment.
